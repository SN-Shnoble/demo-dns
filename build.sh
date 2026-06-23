#!/usr/bin/env bash
# ============================================================
# ocer-dns 编译构建脚本
# 功能：
#   1. 自动递增版本号（从 VERSION 文件读取）
#   2. 编译 dns-resolver 和 geodns
#   3. 显示编译版本和时间
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VERSION_FILE="${SCRIPT_DIR}/VERSION"

# 颜色输出
if [[ -t 1 ]]; then
    C_RED='\033[0;31m'; C_GREEN='\033[0;32m'; C_YELLOW='\033[0;33m'
    C_BLUE='\033[0;34m'; C_BOLD='\033[1m'; C_RESET='\033[0m'
else
    C_RED=''; C_GREEN=''; C_YELLOW=''; C_BLUE=''; C_BOLD=''; C_RESET=''
fi

log_info()  { printf "${C_BLUE}[INFO]${C_RESET}  %s\n" "$*"; }
log_ok()    { printf "${C_GREEN}[OK]${C_RESET}    %s\n" "$*"; }
log_title() { printf "\n${C_BOLD}== %s ==${C_RESET}\n" "$*"; }

# 读取当前版本号
read_version() {
    if [[ -f "${VERSION_FILE}" ]]; then
        cat "${VERSION_FILE}"
    else
        echo "1.0.0"
    fi
}

# 递增版本号（补丁版本 +1）
increment_version() {
    local version="$1"
    # 解析版本号：major.minor.patch
    IFS='.' read -r major minor patch <<< "$version"
    patch=$((patch + 1))
    echo "${major}.${minor}.${patch}"
}

# 写入新版本号
write_version() {
    echo "$1" > "${VERSION_FILE}"
}

# 主构建函数
main() {
    log_title "OCER-DNS 构建脚本"
    
    # 获取当前版本并递增
    local current_version=$(read_version)
    local new_version=$(increment_version "$current_version")
    local build_time=$(date '+%Y-%m-%dT%H:%M:%S')
    
    log_info "当前版本: ${current_version}"
    log_info "新版本号:  ${new_version}"
    log_info "构建时间:  ${build_time}"
    
    # 写入新版本号
    write_version "${new_version}"
    log_ok "版本号已更新"
    
    # 创建输出目录（portal-web 静态文件目录，供安装脚本下载）
    local outdir="${SCRIPT_DIR}/portal-web/public/build"
    mkdir -p "${outdir}"

    # 定义目标平台
    declare -a PLATFORMS=(
        "linux/amd64"
        "linux/arm64"
        "darwin/amd64"
        "darwin/arm64"
    )

    local ldflags="-X main.version=${new_version} -X main.buildTime=${build_time}"

    # 编译 dns-resolver（多平台交叉编译）
    log_title "编译 dns-resolver"
    cd "${SCRIPT_DIR}/dns-resolver"
    for platform in "${PLATFORMS[@]}"; do
        IFS='/' read -r goos goarch <<< "$platform"
        local outfile="${outdir}/dns-resolver-${goos}-${goarch}"
        printf "  ${C_YELLOW}%-20s${C_RESET} → %s\n" "${goos}/${goarch}" "${outfile##*/}"
        GOOS="${goos}" GOARCH="${goarch}" CGO_ENABLED=0 go build \
            -ldflags "${ldflags}" -o "${outfile}" ./cmd/dns-resolver/
    done
    log_ok "dns-resolver 全部平台编译完成"

    # 编译 geodns（多平台交叉编译）
    log_title "编译 geodns"
    cd "${SCRIPT_DIR}/geodns"
    for platform in "${PLATFORMS[@]}"; do
        IFS='/' read -r goos goarch <<< "$platform"
        local outfile="${outdir}/geodns-${goos}-${goarch}"
        printf "  ${C_YELLOW}%-20s${C_RESET} → %s\n" "${goos}/${goarch}" "${outfile##*/}"
        GOOS="${goos}" GOARCH="${goarch}" CGO_ENABLED=0 go build \
            -ldflags "${ldflags}" -o "${outfile}" ./cmd/geodns/
    done
    log_ok "geodns 全部平台编译完成"

    log_title "构建完成"
    printf "\n${C_BOLD}版本信息${C_RESET}\n"
    printf "┌─────────────────────────────────────\n"
    printf "│ 版本号:    %s\n" "${new_version}"
    printf "│ 构建时间:  %s\n" "${build_time}"
    printf "│\n"
    printf "│ dns-resolver:\n"
    for platform in "${PLATFORMS[@]}"; do
        IFS='/' read -r goos goarch <<< "$platform"
        printf "│   %-19s portal-web/public/build/%s\n" "${goos}/${goarch}" "dns-resolver-${goos}-${goarch}"
    done
    printf "│\n"
    printf "│ geodns:\n"
    for platform in "${PLATFORMS[@]}"; do
        IFS='/' read -r goos goarch <<< "$platform"
        printf "│   %-19s portal-web/public/build/%s\n" "${goos}/${goarch}" "geodns-${goos}-${goarch}"
    done
    printf "│\n"
    printf "│ 安装脚本:\n"
    printf "│   portal-web/public/build/dns-resolver-install.sh\n"
    printf "│   portal-web/public/build/geodns-install.sh\n"
    printf "└─────────────────────────────────────\n"
}

main "$@"