#!/bin/bash
# 远端一键扫描：在 /tmp/jinyu-audit/src 用「无任何 exclude」的 raw 尺子跑 phpcs，
# 结果写成 /tmp/jinyu-audit/raw.csv。由 tools/refresh-raw.sh 上传并执行。
set -e
cd /tmp/jinyu-audit/src
# ⚠️ phpcs 的退出码语义：0=无错，1=找到问题，2=存在可自动修复的问题，3=处理报错。
# raw 扫描必然满屏「可修复」问题 → 必然 exit 2。这里必须吞掉退出码，
# 否则 set -e 会让整条 refresh 链断在扫描这一步（历史上踩过：拉回快照永远跑不到）。
set +e
# ⚠️ phpcs 会把进度点（"...."）混进 stdout，混进 csv 后行首不再是引号，解析会丢行。
# 只放行「以引号开头的行」= 真正的 CSV 数据行，表头另起一行手写，保证快照干净。
{
  echo 'File,Line,Column,Type,Message,Source,Severity,Fixable'
  /usr/local/php-8.5/bin/php /opt/wpcs/vendor/squizlabs/php_codesniffer/bin/phpcs \
    --standard=phpcs-all.xml.dist --extensions=php --report=csv 2>/dev/null \
    | grep '^"'
} > /tmp/jinyu-audit/raw.csv
echo "[remote] rows=$(tail -n +2 /tmp/jinyu-audit/raw.csv | wc -l)"
exit 0
