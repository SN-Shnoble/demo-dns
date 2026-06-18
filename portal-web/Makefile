# portal-web — Makefile
# ============================================================
# Phase C requirement: 构建脚本

.PHONY: install dev build test lint clean

install: ## 安装 PHP + Node 依赖
	composer install --prefer-dist --no-interaction
	cp -n .env.example .env 2>/dev/null || true
	php artisan key:generate
	cd web && npm install

dev: ## 启动本地开发服务（PHP + Vite）
	php artisan serve --host=0.0.0.0 --port=8080 & \
	cd web && npx vite --host 0.0.0.0

build: ## 构建前端
	cd web && npm run build

test: ##