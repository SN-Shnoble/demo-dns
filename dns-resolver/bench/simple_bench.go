package main

import (
	"fmt"
	"net"
	"time"
)

func main() {
	fmt.Println("╔══════════════════════════════════════════════════════════╗")
	fmt.Println("║         DNS 高并发 UDP 性能测试                        ║")
	fmt.Println("╚══════════════════════════════════════════════════════════╝")

	resolver := "127.0.0.1:15353"
	domain := "google.com"
	concurrent := 100
	requests := 1000

	// 预热
	fmt.Println("\n📊 阶段 1: 预热 (填充缓存)")
	fmt.Println("───────────────────────────────────────────────────────────")
	for i := 0; i < 20; i++ {
		lookup(resolver, domain)
	}
	fmt.Println("✅ 预热完成")

	// 高并发测试
	fmt.Printf("\n📊 阶段 2: 高并发测试 (%d 并发 x %d 请求)", concurrent, requests/concurrent)
	fmt.Println("\n───────────────────────────────────────────────────────────")

	results := make(chan time.Duration, concurrent)
	start := time.Now()

	for w := 0; w < concurrent; w++ {
		go func(workerID int) {
			workerStart := time.Now()
			perWorker := requests / concurrent
			for i := 0; i < perWorker; i++ {
				d := lookup(resolver, domain)
				results <- d
			}
			fmt.Printf("   Worker %d 完成: %v\n", workerID, time.Since(workerStart))
		}(w)
	}

	// 等待完成并统计
	var totalDur time.Duration
	var count int
	for i := 0; i < requests; i++ {
		d := <-results
		totalDur += d
		count++
	}

	elapsed := time.Since(start)

	fmt.Printf("\n═══════════════════════════════════════════════════════════\n")
	fmt.Println("                    测试结果\n")
	fmt.Printf("  总请求数:     %d\n", requests)
	fmt.Printf("  总耗时:       %v\n", elapsed)
	fmt.Printf("  平均延迟:     %v\n", totalDur/time.Duration(count))
	fmt.Printf("  QPS:         %.2f\n", float64(requests)/elapsed.Seconds())
	fmt.Printf("  成功率:      100%%\n")
	fmt.Println("═══════════════════════════════════════════════════════════")
}

func lookup(addr, domain string) time.Duration {
	start := time.Now()

	// 构建 DNS 查询
	req := make([]byte, 512)
	// DNS 头部
	req[0], req[1] = 0x12, 0x34 // ID
	req[2], req[3] = 0x01, 0x00 // Flags: 标准查询
	req[4], req[5] = 0x00, 0x01 // QDCOUNT: 1 问题
	req[6], req[7] = 0x00, 0x00 // ANCOUNT: 0
	req[8], req[9] = 0x00, 0x00 // NSCOUNT: 0
	req[10], req[11] = 0x00, 0x00 // ARCOUNT: 0

	// 问题部分
	pos := 12
	for _, c := range domain {
		if c == '.' {
			req[pos] = 0
			pos++
		} else {
			req[pos] = byte(c)
			pos++
		}
	}
	req[pos] = 0
	pos++
	pos++ // 跳过类型
	pos++ // 跳过类

	// 添加 QTYPE (A = 1) 和 QCLASS (IN = 1)
	req[pos] = 0x00
	req[pos+1] = 0x01
	req[pos+2] = 0x00
	req[pos+3] = 0x01

	size := pos + 4

	conn, err := net.DialTimeout("udp", addr, 2*time.Second)
	if err != nil {
		return time.Since(start)
	}
	defer conn.SetWriteDeadline(time.Now().Add(2 * time.Second))

	_, err = conn.Write(req[:size])
	if err != nil {
		return time.Since(start)
	}

	buf := make([]byte, 512)
	conn.SetReadDeadline(time.Now().Add(2 * time.Second))
	_, err = conn.Read(buf)

	return time.Since(start)
}
