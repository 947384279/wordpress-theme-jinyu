# -*- coding: utf-8 -*-
"""
校验子集产物是否完整：
  1. 白名单里每个图标的码位，是否都能在某个字重族的子集字体 cmap 中找到
     （找不到就一定会渲染成豆腐块 .notdef）
  2. 瘦身后的 subset.min.css 里是否保留了该图标的 content 规则
  3. subset.min.css 是否仍然引用了存在的字体文件
"""
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FA_DIR = os.path.join(ROOT, "assets", "fonts", "fa")
WEBFONTS = os.path.join(ROOT, "assets", "fonts", "webfonts")
OUT_CSS = os.path.join(FA_DIR, "subset.min.css")

sys.path.insert(0, os.path.join(ROOT, "tools"))
from fa_parse import parse  # noqa: E402

ICON_MAP, _ = parse()

names = set()
for fn in ("icons.raw.txt", "icons.extra.txt"):
    p = os.path.join(FA_DIR, fn)
    if os.path.isfile(p):
        for line in open(p, encoding="utf-8"):
            line = line.strip()
            if line and not line.startswith("#") and line in ICON_MAP:
                names.add(line)

print("白名单图标: %d" % len(names))

# 1) 读取三个子集字体的 cmap
from fontTools.ttLib import TTFont  # noqa: E402

cmaps = {}
for fname in ["fa-solid-900.woff2", "fa-regular-400.woff2", "fa-brands-400.woff2"]:
    p = os.path.join(WEBFONTS, fname)
    if not os.path.isfile(p):
        print("!! 缺字体: " + fname)
        continue
    f = TTFont(p)
    cmap = set()
    for table in f["cmap"].tables:
        cmap |= set(table.cmap.keys())
    cmaps[fname] = cmap
    print("  %-22s 字形 %4d 个" % (fname, len(cmap)))

union = set().union(*cmaps.values()) if cmaps else set()

missing_font = sorted(c for c in names if any(int(x, 16) not in union for x in ICON_MAP[c]))
print("\n[1] 字体缺字形 (会显示豆腐块): %d" % len(missing_font))
if missing_font:
    for c in missing_font:
        print("    " + c + "  " + " ".join("U+%04X" % int(x, 16) for x in ICON_MAP[c]))

# 2) CSS 规则检查
css = open(OUT_CSS, encoding="utf-8").read()
missing_css = []
for c in sorted(names):
    # 注意 :before 后面可能是选择器组的续（:before,.fa-other:before{），
    # 直接写 :before\{ 会漏掉多选择器规则的的第一个选择器
    m = re.search(r'\.' + re.escape(c) + r':{1,2}before[^{]*\{[^}]*content:"\\([0-9a-fA-F]{1,4})"', css)
    if not m or m.group(1).lower() not in [x.lower() for x in ICON_MAP[c]]:
        missing_css.append(c)
print("[2] CSS 缺规则: %d" % len(missing_css))
if missing_css:
    for c in missing_css:
        print("    " + c)

# 3) CSS 引用的字体是否都存在
refs = set(re.findall(r'url\(\s*[\'"]?([^\'")]+\.woff2)', css))
print("[3] CSS 引用的字体: %d 个" % len(refs))
for r in sorted(refs):
    rp = os.path.normpath(os.path.join(FA_DIR, r))
    print("    %-28s %s" % (r, "OK" if os.path.isfile(rp) else "** 缺失 **"))

bad = len(missing_font) + len(missing_css) + sum(1 for r in refs if not os.path.isfile(os.path.join(FA_DIR, r)))
print("\n结论: %s" % ("全部通过" if bad == 0 else "发现 %d 处问题" % bad))
sys.exit(0 if bad == 0 else 1)
