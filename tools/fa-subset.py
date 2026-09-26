# -*- coding: utf-8 -*-
"""
按图标白名单生成 FontAwesome 子集。

用法：
    python tools/fa-subset.py                 # 用当前白名单生成
    python tools/fa-subset.py --check         # 只校验白名单，不生成

白名单来源（合并去重，并剔除 all.min.css 中并不存在的类名）：
    assets/fonts/fa/icons.raw.txt    自动采集（主题源码 + 数据库 + 线上 DOM）
    assets/fonts/fa/icons.extra.txt  手工维护的保险清单

产物：
    assets/fonts/webfonts/fa-{solid-900,regular-400,brands-400}.woff2   子集字体
    assets/fonts/fa/subset.min.css                                      瘦身后的图标样式表

subset.min.css 的做法是「保留 all.min.css 原文顺序，只删掉白名单之外的图标 content 规则」，
而不是重新组装一份精简样式表 —— 这样不会改变任何规则的层叠顺序，样式表现与原版一致。
"""
import os
import re
import sys
import shutil

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FA_DIR = os.path.join(ROOT, "assets", "fonts", "fa")
WEBFONTS = os.path.join(ROOT, "assets", "fonts", "webfonts")
SRC_FONTS = os.path.join(ROOT, ".workbuddy", "tmp", "fontsrc")
SRC_CSS = os.path.join(FA_DIR, "all.min.css")
OUT_CSS = os.path.join(FA_DIR, "subset.min.css")

sys.path.insert(0, os.path.join(ROOT, "tools"))
from fa_parse import parse  # noqa: E402

# 三个字重族 -> 源 woff2
FAMILIES = [
    ("fa-solid-900.woff2", "Font Awesome 6 Free", 900),
    ("fa-regular-400.woff2", "Font Awesome 6 Free", 400),
    ("fa-brands-400.woff2", "Font Awesome 6 Brands", 400),
]

# 一条规则里写死的图标码位，如 content:"\f078"
ICON_CONTENT = re.compile(r'content:\s*"\\([0-9a-fA-F]{1,4})"', re.I)
# 形如 .fa-chevron-down:before  / .fa-x:before,.fa-y:before
SELECTOR_RULE = re.compile(r'([^{}]+)\{([^{}]*?)\}', re.S)


def read_whitelist():
    names = set()
    for fn in ("icons.raw.txt", "icons.extra.txt"):
        p = os.path.join(FA_DIR, fn)
        if not os.path.isfile(p):
            continue
        for line in open(p, encoding="utf-8"):
            line = line.strip()
            if line and not line.startswith("#"):
                names.add(line)
    return names


def resolve(candidates):
    """与 all.min.css 的 class->码位 映射校验，返回 (可用类名, 码位集合, 被剔除的类名)"""
    mapping, _codes = parse()
    ok = sorted(c for c in candidates if c in mapping)
    # fontTools 的 populate(unicodes=) 只接受整数码位
    # 同名 class 可能对应多个码位（FA6 品牌图标新旧码位并存），裁剪取并集
    codes = sorted({int(x, 16) for c in ok for x in mapping[c]})
    dropped = sorted(c for c in candidates if c not in mapping)
    return ok, codes, dropped


def subset_font(src_name, unicode_list):
    from fontTools import subset
    from fontTools.ttLib import TTFont

    src = os.path.join(SRC_FONTS, src_name)
    dst = os.path.join(WEBFONTS, src_name)
    if not os.path.isfile(src):
        print("  !! 缺少源字体: " + src)
        return None
    os.makedirs(WEBFONTS, exist_ok=True)
    font = TTFont(src)
    opts = subset.Options()
    opts.flavor = "woff2"
    opts.desubroutinize = True
    opts.drop_tables += ["DSIG"]
    opts.name_IDs = ["*"]
    sub = subset.Subsetter(options=opts)
    sub.populate(unicodes=unicode_list)
    sub.subset(font)
    font.flavor = "woff2"
    font.save(dst)
    return dst


def iter_rules(css):
    """按花括号深度切出每条规则的 (selector, body)。

    不能用简单的 [^{}]+ 正则，因为 CSS 里存在 @media 等嵌套块，
    正则会把块内规则一起吞掉导致内容丢失。
    """
    i, n = 0, len(css)
    while i < n:
        j = css.find("{", i)
        if j < 0:
            if css[i:].strip():
                yield css[i:].strip(), ""
                break
            return
        # 从 '{' 往回跳过空白作为选择器起点。
        # 注意不能直接取「上一个 } 之后一个字符」——那样会吃掉选择器组第一个
        # 选择器的前导点，导致 .fa-xxx 丢失点号被误判为白名单外而被删掉。
        k = min(j - 1, i)
        while k >= 0 and css[k] not in "{};":
            k -= 1
        sel = css[k + 1:j].strip()
        depth, k = 1, j + 1
        while k < n and depth:
            if css[k] == "{":
                depth += 1
            elif css[k] == "}":
                depth -= 1
            k += 1
        yield sel, css[j + 1:k - 1]
        i = k


def build_css(whitelist):
    css = open(SRC_CSS, encoding="utf-8").read()
    kept = []
    removed = 0
    for sel, body in iter_rules(css):
        if not sel or sel.startswith("@") or not body.strip():
            kept.append(sel + "{" + body + "}")
            continue
        hit = ICON_CONTENT.search(body)
        if hit:
            # 只在「整条规则的选择器都不在白名单」时丢弃，避免误删被复用的规则
            names = re.findall(r"\.([A-Za-z0-9_-]+)", sel)
            if names and not any(n in whitelist for n in names):
                removed += 1
                continue
        kept.append(sel + "{" + body + "}")

    header = (
        "/*! Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com\n"
        " * License - https://fontawesome.com/license/free"
        " (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License)\n"
        " * Copyright 2023 Fonticons, Inc.\n"
        " * 图标已按 projects/icons.extra.txt 白名单裁剪子集，勿直接编辑本文件，请用 tools/fa-subset.py 重新生成\n"
        " */\n"
    )
    return header + "".join(kept).strip() + "\n", removed


def main():
    check_only = "--check" in sys.argv
    raw = read_whitelist()
    names, codes, dropped = resolve(raw)

    print("白名单候选        : %d" % len(raw))
    print("校验后可用图标    : %d" % len(names))
    if dropped:
        print("不存在的类名(已剔除): %d" % len(dropped))
        print("  " + " ".join(dropped))
    code_count = len(codes)
    print("去重码位          : %d" % code_count)

    if check_only:
        return

    before = {
        "solid-900": os.path.getsize(os.path.join(SRC_FONTS, "fa-solid-900.woff2")) if os.path.isfile(os.path.join(SRC_FONTS, "fa-solid-900.woff2")) else 0,
    }

    src_total = 0
    for fname, _family, _weight in FAMILIES:
        p = os.path.join(SRC_FONTS, fname)
        if os.path.isfile(p):
            src_total += os.path.getsize(p)

    print("\n生成子集字体:")
    total_new = 0
    for fname, _family, _weight in FAMILIES:
        src = os.path.join(SRC_FONTS, fname)
        if not os.path.isfile(src):
            print("  跳过 %s（源字体缺失）" % fname)
            continue
        before_sz = os.path.getsize(src)
        out = subset_font(fname, codes)
        if out:
            after_sz = os.path.getsize(out)
            total_new += after_sz
            print("  %-22s %7d -> %6d B  (-%d)" % (fname, before_sz, after_sz, before_sz - after_sz))

    print("\n生成瘦身样式表:")
    out_css, removed = build_css(set(names))
    before_css = os.path.getsize(SRC_CSS)
    open(OUT_CSS, "w", encoding="utf-8").write(out_css)
    after_css = os.path.getsize(OUT_CSS)
    print("  subset.min.css  %7d -> %6d B  (-%d，丢弃 %d 条白名单外的图标规则)"
          % (before_css, after_css, before_css - after_css, removed))

    old_total = src_total + before_css
    new_total = total_new + after_css
    print("\n合计（字体 + 样式表）: %d -> %d B  （-%d B，-%.1f%%）"
          % (old_total, new_total, old_total - new_total, 100.0 * (old_total - new_total) / old_total))


if __name__ == "__main__":
    main()
