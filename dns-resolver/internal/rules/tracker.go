package rules

import (
	"strings"
)

// CNAMETrackerResult 标识一个 CNAME 目标域名是否属于已知跟踪服务
type CNAMETrackerResult struct {
	Blocked  bool
	Reason   string
	Provider string
	CNAME    string
}

// knownTrackingDomains 是已知的跟踪/分析服务域名列表。
// 这些域名常作为 CNAME 目标出现在第一方 DNS 响应中，
// 用于绕过浏览器 ITP（智能跟踪防护）机制。
var knownTrackingDomains = []string{
	// 主流分析平台
	"google-analytics.com",
	"googletagmanager.com",
	"doubleclick.net",
	"googlesyndication.com",
	// Facebook/Meta
	"facebook.com",
	"fbcdn.net",
	"connect.facebook.net",
	"facebook.net",
	// 广告网络
	"criteo.net",
	"criteo.com",
	"casalemedia.com",
	"sovrn.com",
	"adnxs.com",
	"rubiconproject.com",
	"pubmatic.com",
	"openx.net",
	"appnexus.com",
	"bluekai.com",
	"exelator.com",
	"demdex.net",
	"adsrvr.org",
	"agkn.com",
	"rlcdn.com",
	"bidswitch.net",
	// 营销自动化
	"pardot.com",
	"hubspot.com",
	"marketo.com",
	"mailchimp.com",
	"salesforce.com",
	// 热力图/会话录制
	"hotjar.com",
	"sessioncam.com",
	"fullstory.com",
	"mouseflow.com",
	"crazyegg.com",
	// 混合分析/跟踪
	"mixpanel.com",
	"amplitude.com",
	"segment.io",
	"segment.com",
	"heapanalytics.com",
	"woopra.com",
	"optimizely.com",
	"kissmetrics.com",
	"newrelic.com",
	// A/B 测试
	"optimize.google.com",
	"vwo.com",
	"convert.com",
	// 受众/重定向
	"adroll.com",
	"quantserve.com",
	"scorecardresearch.com",
	"comscore.com",
	"krxd.net",
}

// CheckCNAMETracker 检查 CNAME 目标是否指向已知的跟踪/分析服务。
func CheckCNAMETracker(cnameTarget string) CNAMETrackerResult {
	target := strings.TrimSuffix(strings.ToLower(cnameTarget), ".")
	if target == "" {
		return CNAMETrackerResult{}
	}

	for _, tracker := range knownTrackingDomains {
		if target == tracker || strings.HasSuffix(target, "."+tracker) {
			return CNAMETrackerResult{
				Blocked:  true,
				Reason:   "cname-tracker",
				Provider: tracker,
				CNAME:   cnameTarget,
			}
		}
	}

	return CNAMETrackerResult{}
}
