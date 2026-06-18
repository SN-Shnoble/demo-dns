# syntax=docker/dockerfile:1.7

# ---------- Builder ----------
# 与 go.mod 中 "go 1.22" 对齐；不要追到 1.25，避免 CI 触发 toolchain 下载。
FROM golang:1.22-bookworm AS builder

ARG TARGETOS=linux
ARG TARGETARCH=amd64

WORKDIR /src

COPY go.mod go.sum ./
RUN --mount=type=cache,target=/go/pkg/mod \
    go mod download

COPY . .

RUN --mount=type=cache,target=/go/pkg/mod \
    --mount=type=cache,target=/root/.cache/go-build \
    CGO_ENABLED=0 GOOS=${TARGETOS} GOARCH=${TARGETARCH} \
    go build -trimpath -ldflags="-s -w" \
        -o /out/geodns ./cmd/geodns

# ---------- Runtime ----------
FROM gcr.io/distroless/static-debian12:nonroot AS runtime

COPY --from=builder /out/geodns /usr/local/bin/geodns

USER nonroot:nonroot

EXPOSE 8053

ENTRYPOINT ["/usr/local/bin/geodns"]
