# -*- coding: utf-8 -*-
"""
采集主题实际使用的 FontAwesome 图标候选清单。

数据来源三路，全部合并去重后与 all.min.css 的 class->码位 映射校验：
  1. 主题源码（php / less / js，排除 all.min.css 自身）
  2. 命令行补充来源：
       - 普通文件：把里面出现的 fa- 类名加进来（例如从数据库 dump 的 widget 配置）
       - --dom=<json>：线上 DOM 扫描得到的图标名列表
输出：
  assets/fonts/fa/icons.raw.txt  确认可用（在 FA CSS 中有 content 定义）的图标 class
  assets/fonts/fa/icons.map.json class -> 码位 的映射
"""
import os
import re
import sys
import json

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FA_DIR = os.path.join(ROOT, "assets", "fonts", "fa")
OUT_TXT = os.path.join(FA_DIR, "icons.raw.txt")
OUT_JSON = os.path.join(FA_DIR, "icons.map.json")

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from fa_parse import parse  # noqa: E402

ICON_MAP, ALL_CODES = parse()

# 不是图标，而是 FA 的修饰/状态类
NON_ICON = {
    "fa-solid", "fa-regular", "fa-brands", "fa-light", "fa-thin", "fa-duotone",
    "fa-ul", "fa-li", "fa-pull-left", "fa-pull-right", "fa-spin", "fa-pulse",
    "fa-spin-pulse", "fa-spin-reverse", "fa-fw", "fa-border", "fa-flip",
    "fa-flip-horizontal", "fa-flip-vertical", "fa-flip-both", "fa-inverse",
    "fa-stack", "fa-stack-1x", "fa-stack-2x", "fa-beat", "fa-beat-fade",
    "fa-fade", "fa-2x", "fa-3x", "fa-4x", "fa-5x", "fa-lg", "fa-sm", "fa-xs",
    "fa-2xs", "fa-raw", "fa-sharp", "fa-sharp-solid", "fa-svg-inline",
    "fa-rotate-90", "fa-rotate-180", "fa-rotate-270",
    "fa", "fas", "far", "fab", "fal", "fat", "fad", "fass",
}
FA_RE = re.compile(r"\bfa-[A-Za-z0-9]{1,34}-?[A-Za-z0-9-]*")

candidates = set()

# ---- 1. 主题源码 ----
for d in [os.path.join(ROOT, "inc"), os.path.join(ROOT, "assets", "style"),
          os.path.join(ROOT, "assets", "js"), os.path.join(ROOT, "assets", "fonts")]:
    for dirpath, dirnames, filenames in os.walk(d):
        dirnames[:] = [x for x in dirnames if x not in ("node_modules", "vendor")]
        for fn in filenames:
            if fn.endswith((".php", ".less", ".js", ".css")) and fn != "all.min.css":
                candidates |= set(FA_RE.findall(
                    open(os.path.join(dirpath, fn), encoding="utf-8", errors="ignore").read()))

for extra in ["functions.php", "header.php", "footer.php", "index.php", "404.php",
              "page.php", "author.php", "category.php", "date.php", "comments.php",
              "search.php", "archive.php", "single.php"]:
    p = os.path.join(ROOT, extra)
    if os.path.isfile(p):
        candidates |= set(FA_RE.findall(open(p, encoding="utf-8", errors="ignore").read()))

# ---- 2. 命令行补充 ----
for arg in sys.argv[1:]:
    if arg.startswith("--dom="):
        candidates |= set(json.load(open(arg.split("=", 1)[1], encoding="utf-8")))
    else:
        candidates |= set(FA_RE.findall(open(arg, encoding="utf-8").read()))

# ---- 汇总校验 ----
real = sorted(c for c in candidates if c in ICON_MAP and c not in NON_ICON)
bogus = sorted(c for c in candidates if c.startswith("fa-") and c not in ICON_MAP and c not in NON_ICON)

os.makedirs(FA_DIR, exist_ok=True)
open(OUT_TXT, "w", encoding="utf-8").write("\n".join(real) + "\n")
json.dump({k: ICON_MAP[k] for k in real}, open(OUT_JSON, "w", encoding="utf-8"),
          ensure_ascii=False, indent=1, sort_keys=True)

print("FA CSS 可用 class  : %d" % len(ICON_MAP))
print("候选 token         : %d" % len(candidates))
print("确认为真图标       : %d" % len(real))
print("疑似误判(类名不存在): %d" % len(bogus))
if bogus:
    print("  " + " ".join(bogus))
print("\n已写出: " + os.path.relpath(OUT_TXT, ROOT) + " / " + os.path.relpath(OUT_JSON, ROOT))
