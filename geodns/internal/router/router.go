package router

import "ocer-dns/geodns/internal/healthview"

type Router struct {
	// 节点健康由 (now - last_heartbeat_at) <= 阈值 的简单超时判断
	// 这里只负责从 healthview 中筛选 status=online 的节点 + 按 region/weight 选优
	// 不再基于 QPS/CPU/MEM/DISK 任何"健康"信息做降权/过载判定
}

func New() *Router {
	return &Router{}
}

func (r *Router) Pick(region string, nodes []healthview.Node) *healthview.Node {
	var best *healthview.Node
	bestWeight := -1

	for i := range nodes {
		if !r.isEligible(nodes[i]) {
			continue
		}
		if nodes[i].Region == region {
			weight := r.effectiveWeight(nodes[i])
			if weight > bestWeight {
				bestWeight = weight
				best = &nodes[i]
			}
		}
	}

	if best != nil {
		return best
	}

	for i := range nodes {
		if !r.isEligible(nodes[i]) {
			continue
		}
		weight := r.effectiveWeight(nodes[i])
		if weight > bestWeight {
			bestWeight = weight
			best = &nodes[i]
		}
	}

	return best
}

func (r *Router) isEligible(node healthview.Node) bool {
	if node.Status != "online" {
		return false
	}
	return r.effectiveWeight(node) > 0
}

func (r *Router) effectiveWeight(node healthview.Node) int {
	return node.Weight
}
