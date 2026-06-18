# 07-CHANGE-LOG.md — OcerDNS Portal-Web 变更记录

## 格式

```text
| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| YYYY-MM-DD | code/docs/test | 简要描述 | file1, file2 | ok/pending |
```

---

## 变更记录

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-17 | code | 登录参数 email → name，支持用户名或邮箱登录 | AuthService.php, Login.vue | ok |
| 2026-06-17 | code | APP_DEBUG=true → false，.env.example 安全加固 | .env.example | ok |
| 2026-06-17 | code | Token 存储从 localStorage 统一改为 sessionStorage，router/index.js auth_token → user_token | router/index.js, Layout.vue, AdminLayout.vue, QueryLogs.vue | ok |
| 2026-06-17 | docs | 补充缺失功能实施方案审查：团队管理、审计日志、RBAC 后端实现完整，ko.js 翻译补全 28 个缺失 key | web/src/locales/ko.js | ok |
| 2026-06-17 | docs | 更新架构文档，新增第 14 节完整项目目录结构及统计汇总 | ai-doc-v1/project-doc/01-ARCHITECTURE.md | ok |
| 2026-06-17 | code | 重置 admin 密码，username 大小写兼容 | AuthController.php, Admin.php | ok |
| 2026-06-17 | code | 多语言刷新按钮修复 | en.js, zh-CN.js | ok |
| 2026-06-17 | code | 财务表格宽度 100% 修复 | Bill.vue, Balance.vue, Recharge.vue, RefundRecords.vue | ok |
| 2026-06-17 | code | Admin 菜单配置页面优化：树形菜单、图标移除 | MenuConfig.vue, AdminLayout.vue | ok |
| 2026-06-17 | code | 修复 `.env` 泄露风险，改为安全本地模板并补充测试环境 APP_KEY / ClickHouse 隔离 | .env, phpunit.xml, ClickHouseClient.php | ok |
| 2026-06-17 | code | 修复 `name -> username` 迁移后的兼容性断层：注册、模型、seed、工厂、成员默认配置、管理员用户管理统一到 `username` | User.php, Admin.php, AuthController.php, AuthService.php, DatabaseSeeder.php, UserFactory.php, MemberWorkspaceService.php, AdminUserController.php | ok |
| 2026-06-17 | code | 修复 Sanctum / RBAC / 审计链路：细化后台权限路由、修复路由参数与审计 actor 来源、修复审计日志列名漂移 | routes/v1/admin.php, routes/v1/member.php, AdminRbacController.php, AdminAuditLog.php, 2026_06_17_100000_fix_admin_audit_logs_and_create_alerts_table.php | ok |
| 2026-06-17 | code | 修复账单闭环：Sanctum 表名、余额字段持久化、充值/退款/发票真实落库、导出限流 | config/sanctum.php, BillingService.php, AdminBillingController.php, AdminFinanceController.php, User.php | ok |
| 2026-06-17 | code | 将告警接口从占位实现补齐为真实落库 CRUD | Alert.php, AdminAlertController.php, 2026_06_17_100000_fix_admin_audit_logs_and_create_alerts_table.php | ok |
| 2026-06-17 | test | 同步 HMAC 与鉴权测试到当前生产契约，并完成后端全量回归 | AgentHmacSignatureTest.php, ApiTest.php, MemberWorkspaceTest.php, ProfilePublishTest.php | ok |
| 2026-06-17 | code | 统一前端设计系统与壳层：新增认证壳、成员中心侧边栏、后台运营侧边栏、共享页头和页面原语 | web/src/components/AuthShell.vue, web/src/components/PageHeader.vue, web/src/components/Layout.vue, web/src/components/AdminLayout.vue, web/src/views/Login.vue, web/src/views/Register.vue, web/src/views/admin/AdminLogin.vue, web/src/views/Dashboard.vue, web/src/views/admin/Dashboard.vue, web/src/assets/theme.css | ok |
| 2026-06-17 | code | 引入 Application 层承接编排逻辑，收敛配置 ACK、成员中心总览、配置发布、规则更新的控制器职责 | app/Application/Agent/ConfigAcknowledgementService.php, app/Application/Member/MemberCenterOverviewService.php, app/Application/Member/ProfilePublishApplicationService.php, app/Application/Member/WorkspaceRuleService.php, app/Http/Controllers/Api/V1/Agent/ConfigAckController.php, app/Http/Controllers/Api/V1/Member/MemberCenterController.php, app/Http/Controllers/Api/V1/Member/ProfilePublishController.php, app/Http/Controllers/Api/V1/Member/MemberWorkspaceController.php | ok |
| 2026-06-17 | test | 前端生产构建通过，后端全量测试回归通过 | web, php artisan test | ok |
| 2026-06-18 | code | 会员中心恢复顶部导航并补齐头像下拉入口，新增 Profiles/Teams/Membership 快捷入口 | web/src/components/Layout.vue | ok |
| 2026-06-18 | code | 修复会员端关键交互：DNS 端点字段映射、黑白名单状态改为开关并支持即时保存、隐私拦截列表弹窗补齐、家长监护分类与安全搜索字段持久化、移除安全设置自动保存成功弹窗、日志筛选高度统一 | web/src/views/Devices.vue, web/src/views/Allowlist.vue, web/src/views/Denylist.vue, web/src/views/Privacy.vue, web/src/views/ParentalControl.vue, web/src/views/Security.vue, web/src/views/Logs.vue, app/Http/Controllers/Api/V1/Member/MemberWorkspaceController.php, app/Domain/Profile/MemberWorkspaceService.php | ok |
| 2026-06-18 | code | 后台列表页统一搜索区高度并修正关键页面表格布局：查询日志列宽防换行、用户列表增加充值列、GeoDNS 搜索区高度统一 | web/src/components/ListPage.vue, web/src/views/admin/QueryLogs.vue, web/src/views/admin/Users.vue, web/src/views/admin/GeoDNS.vue | ok |
| 2026-06-18 | code | 优化后台财务与日志页面：修复账单金额 NaN 风险，统一财务金额格式、列表宽度与状态展示，并收敛 Alerts/AuditLogs 过滤区与表格列宽 | web/src/views/admin/Bill.vue, web/src/views/admin/Recharge.vue, web/src/views/admin/RefundRecords.vue, web/src/views/admin/Balance.vue, web/src/views/admin/Billing.vue, web/src/views/admin/Alerts.vue, web/src/views/admin/AuditLogs.vue | ok |
| 2026-06-18 | test | 会员工作区回归测试通过，前端生产构建通过 | tests/Feature/MemberWorkspaceTest.php, web | ok |
