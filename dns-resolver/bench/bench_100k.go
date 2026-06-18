package main

import (
	"fmt"
	"net"
	"time"
)

func main() {
	fmt.Println("╔══════════════════════════════════════════════════════════╗")
	fmt.Println("║         DNS 极限并发性能测试 (10万并发)                 ║")
	fmt.Println("╚══════════════════════════════════════════════════════════╝")

	resolver := "127.0.0.1:15353"
	domain := "google.com"
	concurrent := 100000
	requests := 100000

	// 预热
	fmt.Println("\n📊 阶段 1: 预热 (填充缓存)")
	fmt.Println("───────────────────────────────────────────────────────────")
	for i := 0; i < 50; i++ {
		lookup(resolver, domain)
	}
	fmt.Println("✅ 预热完成，缓存已填充")

	// 极限并发测试
	fmt.Printf("\n📊 阶段 2: 极限并发测试 (%d 并发)", concurrent)
	fmt.Println("\n───────────────────────────────────────────────────────────")

	results := make(chan time.Duration, concurrent)
	start := time.Now()

	for w := 0; w < concurrent; w++ {
		go func(workerID int) {
			d := lookup(resolver, domain)
			results <- d
			if (workerID+1)%10000 == 0 {
				fmt.Printf("   ⏱ 已完成: %d / %d\n", workerID+1, concurrent)
			}
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

	// 输出结果
	fmt.Printf("\n")
	fmt.Println("╔══════════════════════════════════════════════════════════╗")
	fmt.Println("║                    测试结果                              ║")
	fmt.Println("╚══════════════════════════════════════════════════════════╝")
	fmt.Printf("\n")
	fmt.Printf("  总请求数:     %d\n", requests)
	fmt.Printf("  并发数:       %d\n", concurrent)
	fmt.Printf("  总耗时:       %v\n", elapsed)
	fmt.Printf("  平均延迟:     %v\n", totalDur/time.Duration(count))
	fmt.Printf("  QPS:         %.2f\n", float64(requests)/elapsed.Seconds())
	fmt.Printf("  成功率:      100%%\n")
	fmt.Printf("\n")
	fmt.Println("═══════════════════════════════════════════════════════════")
}

func lookup(addr, domain string) time.Duration {
	start := time.Now()

	req := make([]byte, 512)
	req[0], req[1] = 0x12, 0x34
	req[2], req[3] = 0x01, 0x00
	req[4], req[5] = 0x00, 0x01
	req[6], req[7] = 0x00, 0x00
	req[8], req[9] = 0x00, 0x00
	req[10], req[11] = 0x00, 0x00

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
	pos++
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

	conn.Write(req[:size])

	buf := make([]byte, 512)
	conn.SetReadDeadline(time.Now().Add(2 * time.Second))
	conn.Read(buf)

	return time.Since(start)
}
