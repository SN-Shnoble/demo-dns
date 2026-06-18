package main

import (
	"context"
	"flag"
	"fmt"
	"math"
	"net"
	"os"
	"sort"
	"sync"
	"sync/atomic"
	"time"

	"github.com/miekg/dns"
)

var (
	resolverAddr  = flag.String("resolver", "127.0.0.1:15353", "DNS resolver address")
	workers       = flag.Int("workers", 50, "Number of concurrent workers")
	duration      = flag.Int("duration", 10, "Test duration in seconds")
	qps           = flag.Int("qps", 1000, "Target queries per second")
	cacheTest     = flag.Bool("cache", false, "Run cache benchmark test")
	concurrentTest = flag.Bool("concurrent", false, "Run concurrent cache benchmark")
)

type Metrics struct {
	mu        sync.RWMutex
	Total     uint64
	Success   uint64
	Blocked   uint64
	Errors    uint64
	Timeouts  uint64
	Latencies []time.Duration
}

func (m *Metrics) Record(latency time.Duration, success, blocked, timeout bool) {
	atomic.AddUint64(&m.Total, 1)
	if timeout {
		atomic.AddUint64(&m.Timeouts, 1)
	} else if !success {
		atomic.AddUint64(&m.Errors, 1)
	} else if blocked {
		atomic.AddUint64(&m.Blocked, 1)
	} else {
		atomic.AddUint64(&m.Success, 1)
	}
	m.mu.Lock()
	m.Latencies = append(m.Latencies, latency)
	m.mu.Unlock()
}

func (m *Metrics) Percentile(p float64) time.Duration {
	m.mu.RLock()
	defer m.mu.RUnlock()
	if len(m.Latencies) == 0 {
		return 0
	}
	sorted := make([]time.Duration, len(m.Latencies))
	copy(sorted, m.Latencies)
	sort.Slice(sorted, func(i, j int) bool { return sorted[i] < sorted[j] })
	idx := int(math.Ceil(float64(len(sorted))*p)) - 1
	if idx < 0 {
		idx = 0
	}
	if idx >= len(sorted) {
		idx = len(sorted) - 1
	}
	return sorted[idx]
}

func (m *Metrics) AvgLatency() time.Duration {
	m.mu.RLock()
	defer m.mu.RUnlock()
	if len(m.Latencies) == 0 {
		return 0
	}
	var sum int64
	for _, l := range m.Latencies {
		sum += l.Nanoseconds()
	}
	return time.Duration(sum / int64(len(m.Latencies)))
}

type DNSClient struct {
	addr     string
	network  string
	timeout  time.Duration
	metrics  *Metrics
}

func NewDNSClient(addr, network string, timeout time.Duration) *DNSClient {
	return &DNSClient{
		addr:    addr,
		network: network,
		timeout: timeout,
		metrics: &Metrics{},
	}
}

func (c *DNSClient) Query(domain string, qtype uint16) (blocked bool, err error) {
	client := &dns.Client{
		Net:     c.network,
		Timeout: c.timeout,
	}

	msg := new(dns.Msg)
	msg.SetQuestion(dns.Fqdn(domain), qtype)

	ctx, cancel := context.WithTimeout(context.Background(), c.timeout)
	defer cancel()

	start := time.Now()
	reply, _, err := client.ExchangeContext(ctx, msg, c.addr)
	latency := time.Since(start)

	if err != nil {
		if ctx.Err() == context.DeadlineExceeded {
			c.metrics.Record(latency, false, false, true)
		} else {
			c.metrics.Record(latency, false, false, false)
		}
		return false, err
	}

	// Check if blocked (NXDOMAIN)
	if reply.Rcode == dns.RcodeNameError {
		c.metrics.Record(latency, true, true, false)
		return true, nil
	}

	c.metrics.Record(latency, true, false, false)
	return false, nil
}

func RunBenchmark() {
	flag.Parse()

	blockedDomains := []string{
		"ads.example.com",
		"tracker.evil.com",
		"analytics.bad.net",
		"ads.google.com",
		"doubleclick.net",
	}

	allowedDomains := []string{
		"google.com",
		"github.com",
		"cloudflare.com",
		"microsoft.com",
		"apple.com",
	}

	client := NewDNSClient(*resolverAddr, "udp", 5*time.Second)

	fmt.Println("╔══════════════════════════════════════════════════════════╗")
	fmt.Println("║           DNS Resolver 并发性能测试                        ║")
	fmt.Println("╚══════════════════════════════════════════════════════════╝")
	fmt.Printf("\n📍 Resolver: %s", *resolverAddr)
	fmt.Printf("\n👷 Workers: %d", *workers)
	fmt.Printf("\n⏱️  Duration: %ds", *duration)
	fmt.Printf("\n🎯 Target QPS: %d\n", *qps)

	fmt.Println("\n═══════════════════════════════════════════════════════════")
	fmt.Println("  测试场景 1: 阻止域名并发测试 (100% 拦截)")
	fmt.Println("═══════════════════════════════════════════════════════════")

	var wg sync.WaitGroup
	stopCh := make(chan struct{})
	startTime := time.Now()

	// Calculate interval between queries per worker
	interval := time.Second / time.Duration(*qps / *workers)
	if interval < time.Microsecond {
		interval = time.Microsecond
	}

	// Start workers
	for w := 0; w < *workers; w++ {
		wg.Add(1)
		go func(workerID int) {
			defer wg.Done()
			ticker := time.NewTicker(interval)
			defer ticker.Stop()
			domainIdx := 0

			for {
				select {
				case <-stopCh:
					return
				case <-ticker.C:
					domain := blockedDomains[domainIdx%len(blockedDomains)]
					domainIdx++
					go client.Query(domain, dns.TypeA)
				}
			}
		}(w)
	}

	// Run for specified duration
	time.Sleep(time.Duration(*duration) * time.Second)
	close(stopCh)
	wg.Wait()

	elapsed := time.Since(startTime)
	m := client.metrics

	fmt.Println("\n┌─────────────────────────────────────────────────────────────┐")
	fmt.Println("│                      测试结果                               │")
	fmt.Println("├─────────────────────────────────────────────────────────────┤")
	fmt.Printf("│  总查询数:          %-15d                      │\n", atomic.LoadUint64(&m.Total))
	fmt.Printf("│  成功:              %-15d                      │\n", atomic.LoadUint64(&m.Success))
	fmt.Printf("│  阻止:              %-15d                      │\n", atomic.LoadUint64(&m.Blocked))
	fmt.Printf("│  错误:              %-15d                      │\n", atomic.LoadUint64(&m.Errors))
	fmt.Printf("│  超时:              %-15d                      │\n", atomic.LoadUint64(&m.Timeouts))
	fmt.Println("├─────────────────────────────────────────────────────────────┤")
	fmt.Printf("│  实际 QPS:          %-15.2f                      │\n", float64(m.Total)/elapsed.Seconds())
	fmt.Printf("│  平均延迟:          %-15v                      │\n", m.AvgLatency())
	fmt.Printf("│  P50 延迟:          %-15v                      │\n", m.Percentile(0.50))
	fmt.Printf("│  P95 延迟:          %-15v                      │\n", m.Percentile(0.95))
	fmt.Printf("│  P99 延迟:          %-15v                      │\n", m.Percentile(0.99))
	fmt.Println("└─────────────────────────────────────────────────────────────┘")

	// Test scenario 2: Allowed domains
	fmt.Println("\n═══════════════════════════════════════════════════════════")
	fmt.Println("  测试场景 2: 允许域名并发测试 (0% 拦截)")
	fmt.Println("═══════════════════════════════════════════════════════════")

	client2 := NewDNSClient(*resolverAddr, "udp", 5*time.Second)
	stopCh2 := make(chan struct{})

	for w := 0; w < *workers; w++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			ticker := time.NewTicker(interval)
			defer ticker.Stop()
			domainIdx := 0

			for {
				select {
				case <-stopCh2:
					return
				case <-ticker.C:
					domain := allowedDomains[domainIdx%len(allowedDomains)]
					domainIdx++
					go client2.Query(domain, dns.TypeAAAA)
				}
			}
		}()
	}

	time.Sleep(time.Duration(*duration) * time.Second)
	close(stopCh2)
	wg.Wait()

	elapsed2 := time.Since(startTime)
	m2 := client2.metrics

	fmt.Println("\n┌─────────────────────────────────────────────────────────────┐")
	fmt.Printf("│  总查询数:          %-15d                      │\n", atomic.LoadUint64(&m2.Total))
	fmt.Printf("│  成功:              %-15d                      │\n", atomic.LoadUint64(&m2.Success))
	fmt.Printf("│  阻止:              %-15d                      │\n", atomic.LoadUint64(&m2.Blocked))
	fmt.Printf("│  实际 QPS:          %-15.2f                      │\n", float64(m2.Total)/elapsed2.Seconds())
	fmt.Printf("│  平均延迟:          %-15v                      │\n", m2.AvgLatency())
	fmt.Printf("│  P99 延迟:          %-15v                      │\n", m2.Percentile(0.99))
	fmt.Println("└─────────────────────────────────────────────────────────────┘")

	// Print summary
	fmt.Println("\n═══════════════════════════════════════════════════════════")
	fmt.Println("  测试完成")
	fmt.Println("═══════════════════════════════════════════════════════════")
}

func main() {
	flag.Parse()

	// Check if resolver is running
	conn, err := net.DialTimeout("udp", *resolverAddr, time.Second)
	if err != nil {
		fmt.Printf("⚠️  无法连接到 %s: %v\n", *resolverAddr, err)
		fmt.Println("💡 请确保 DNS Resolver 已启动，或使用 -resolver 参数指定地址")
		os.Exit(1)
	}
	conn.Close()
	fmt.Println("✅ DNS Resolver 连接正常")

	if *concurrentTest {
		RunConcurrentBenchmark()
	} else if *cacheTest {
		RunCacheBenchmark()
	} else {
		RunBenchmark()
	}
}

// RunCacheBenchmark 运行缓存命中率测试
func RunCacheBenchmark() {
	fmt.Println("╔══════════════════════════════════════════════════════════╗")
	fmt.Println("║           DNS 缓存命中率测试                             ║")
	fmt.Println("╚══════════════════════════════════════════════════════════╝")

	domains := []string{"google.com", "github.com", "cloudflare.com", "microsoft.com", "apple.com"}

	// 预热阶段
	fmt.Println("\n📊 阶段 1: 缓存预热 (查询 5 个域名各 10 次)")
	fmt.Println("───────────────────────────────────────────────────────────")

	for _, domain := range domains {
		for i := 0; i < 10; i++ {
			queryBench(domain, dns.TypeA)
		}
	}
	fmt.Println("✅ 预热完成，缓存已填充")
	time.Sleep(500 * time.Millisecond)

	// 测试阶段 1：重复查询
	fmt.Println("\n📊 阶段 2: 重复查询测试 (1000 次相同域名)")
	fmt.Println("───────────────────────────────────────────────────────────")

	start := time.Now()
	for i := 0; i < 1000; i++ {
		queryBench(domains[0], dns.TypeA)
	}
	duration := time.Since(start)

	fmt.Printf("   总查询: 1000\n")
	fmt.Printf("   总耗时: %v\n", duration)
	fmt.Printf("   平均延迟: %v\n", duration/1000)
	fmt.Printf("   QPS: %.2f\n", 1000.0/duration.Seconds())

	// 测试阶段 2：高并发
	fmt.Println("\n📊 阶段 3: 高并发缓存测试 (50 并发 x 100 次)")
	fmt.Println("───────────────────────────────────────────────────────────")

	start = time.Now()
	var wg sync.WaitGroup
	for w := 0; w < 50; w++ {
		wg.Add(1)
		go func(workerID int) {
			defer wg.Done()
			for i := 0; i < 100; i++ {
				domain := domains[workerID%len(domains)]
				queryBench(domain, dns.TypeA)
			}
		}(w)
	}
	wg.Wait()
	duration = time.Since(start)

	fmt.Printf("   总查询: 5000\n")
	fmt.Printf("   总耗时: %v\n", duration)
	fmt.Printf("   平均延迟: %v\n", duration/5000)
	fmt.Printf("   QPS: %.2f\n", 5000.0/duration.Seconds())

	fmt.Println("\n═══════════════════════════════════════════════════════════")
	fmt.Println("  缓存测试完成！查看 Resolver 日志中的 'cache_hit' 信息")
	fmt.Println("═══════════════════════════════════════════════════════════")
}

func queryBench(domain string, qtype uint16) {
	msg := new(dns.Msg)
	msg.SetQuestion(dns.Fqdn(domain), qtype)

	client := &dns.Client{
		Net:     "udp",
		Timeout: 2 * time.Second,
	}

	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()

	client.ExchangeContext(ctx, msg, *resolverAddr)
}
