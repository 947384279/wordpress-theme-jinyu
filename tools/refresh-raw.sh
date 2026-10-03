#!/bin/bash
# 全量 raw 审计：打包源码 → 上传服务器 → 用「无任何 exclude」的尺子跑 phpcs
# → 拉回 csv 快照 → 本地做减法（只报新增）。
#
# 用途：清空/锁死「每次扫描都扫出新问题」的循环。
# 详见 .workbuddy/audit/baseline.json 顶部的 _invariant。
#
# 依赖：服务器 /opt/wpcs（phpcs 3.x + WPCS 3.4.1）、本机 Git Bash + ssh key。
set -e
cd "$(dirname "$0")/.."
ROOT=$(pwd)
KEY="$HOME/.ssh/id_ed25519_workbuddy"
HOST="root@175.24.138.28"
REMOTE="/tmp/jinyu-audit"
SKIP=(node_modules .git dist-wporg release .workbuddy tools tests)

mkdir -p "$ROOT/.workbuddy/tmp" "$ROOT/.workbuddy/audit"

ARGS=(czf "$ROOT/.workbuddy/tmp/jinyu-src.tgz")
for s in "${SKIP[@]}"; do ARGS+=(--exclude="$s"); done
ARGS+=(".")

echo "[refresh] 打包源码…"
tar "${ARGS[@]}"

echo "[refresh] 上传到服务器并解压…"
ssh -i "$KEY" "$HOST" "mkdir -p $REMOTE"
ssh -i "$KEY" "$HOST" "cat > $REMOTE/src.tgz" < "$ROOT/.workbuddy/tmp/jinyu-src.tgz"
ssh -i "$KEY" "$HOST" "rm -rf $REMOTE/src && mkdir -p $REMOTE/src && cd $REMOTE/src && tar xzf $REMOTE/src.tgz"
ssh -i "$KEY" "$HOST" "cat > $REMOTE/phpcs-all.xml.dist" < "$ROOT/phpcs-all.xml.dist"
ssh -i "$KEY" "$HOST" "cat > $REMOTE/run-raw.sh" < "$ROOT/tools/remote-run-raw.sh"

echo "[refresh] 扫描（服务器 phpcs，约 5s）…"
ssh -i "$KEY" "$HOST" "bash $REMOTE/run-raw.sh"

OUT="$ROOT/.workbuddy/audit/raw-$(date +%F).csv"
ssh -i "$KEY" "$HOST" "cat $REMOTE/raw.csv" > "$OUT"
rm -f "$ROOT/.workbuddy/tmp/jinyu-src.tgz"
echo "[refresh] 快照写入 $OUT"

exec node "$ROOT/tools/audit.js"
