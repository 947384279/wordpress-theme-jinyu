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
    assets/fonts/fa/all.css                                            上游未压缩原始样式表
    assets/fonts/fa/subset.css                                         瘦身后的图标样式表（未压缩）
    assets/fonts/fa/subset.min.css                                     同上的压缩版

subset.css 的做法是「保留上游原文顺序，只删掉白名单之外的图标 content 规则」，
而不是重新组装一份精简样式表 —— 这样不会改变任何规则的层叠顺序，样式表现与原版一致。

关于「压缩文件必须配未压缩原始文件」（wp.org 审核要求）：
    上游 FontAwesome 只发压缩版 all.min.css，本仓库这份还含 FA5 兼容别名
    （.fa-add / .fa-bar-chart 等），与官网 6.5.1 的 all.css 并非同一份内容 ——
    换用官网版会让一批兼容别名消失，属于行为变更，不可接受。
    故这里的做法是：把 all.min.css 反压缩成 all.css（规则集合逐条等价，只是排版展开），
    再用这份 all.css 做裁剪源产出未压缩的 subset.css，最后压缩成 subset.min.css。
    三者的规则集合严格一致，与线上现状零差异。
"""
import os
import re
import sys
import shutil

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FA_DIR = os.path.join(ROOT, "assets", "fonts", "fa")
WEBFONTS = os.path.join(ROOT, "assets", "fonts", "webfonts")
SRC_FONTS = os.path.join(ROOT, ".workbuddy", "tmp", "fontsrc")
# 裁剪源：仓库这份 all.min.css（含 FA5 兼容别名，是线上现状的唯一事实来源）。
SRC_CSS = os.path.join(FA_DIR, "all.min.css")
# 由 SRC_CSS 反压缩生成的未压缩原始文件，作为 all.min.css 的配对源。
OUT_ALL_CSS = os.path.join(FA_DIR, "all.css")
OUT_CSS = os.path.join(FA_DIR, "subset.css")
OUT_CSS_MIN = os.path.join(FA_DIR, "subset.min.css")

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
    """按白名单裁剪上游样式表，返回 (未压缩 CSS 文本, 删除的规则数)。

    读的是未压缩的 all.css（由 all.min.css 反压缩而来，规则集合等价），
    输出未压缩的 subset.css；压缩版由 clean-css 从这份产物压出，
    保证 subset.min.css 与 subset.css 的规则集合严格一致。
    """
    css = open(OUT_ALL_CSS, encoding="utf-8").read()
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
        " * 图标已按 assets/fonts/fa/icons.extra.txt 白名单裁剪子集，勿直接编辑本文件，\n"
        " * 请用 tools/fa-subset.py 重新生成（subset.min.css 是本文件的压缩版）。\n"
        " */\n"
    )
    return header + "".join(kept).strip() + "\n", removed


def expand_css(css_text):
    """把压缩过的 CSS 展开成可读的未压缩形态。

    只做排版层面的展开：把规则逐条换行、选择器与花括号之间加空格、
    声明之间加分号与换行。不新增、不删除、不合并任何规则，
    因此展开前后「选择器集合 + 声明集合」完全等价。
    """
    out = []
    for sel, body in iter_rules(css_text):
        decls = [d.strip() for d in body.split(";") if d.strip()]
        if not decls:
            out.append("%s {\n}" % sel)
            continue
        # 选择器组内的逗号分隔项各自换行
        sels = [s.strip() for s in sel.split(",")] if not sel.startswith("@") else [sel]
        head = ",\n".join(sels)
        if head.startswith("@"):
            out.append("%s {\n  %s\n}" % (head, ";\n  ".join(decls)))
        else:
            out.append("%s {\n  %s;\n}" % (head, ";\n  ".join(decls)))
    return "\n".join(out) + "\n"


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
    # 第一步：把上游压缩版展开成未压缩的 all.css（all.min.css 的配对原始文件）。
    # 裁剪源用这份未压缩版，产出未压缩的 subset.css，再压成 subset.min.css ——
    # 这样 all.min.css / all.css / subset.css / subset.min.css 四者规则集合一致。
    raw_css = open(SRC_CSS, encoding="utf-8").read()
    all_css = expand_css(raw_css)
    open(OUT_ALL_CSS, "w", encoding="utf-8").write(all_css)
    print("  all.css           %7d -> %6d B  （all.min.css 反压缩，规则等价）"
          % (len(raw_css), len(all_css)))

    out_css, removed = build_css(set(names))
    before_css = os.path.getsize(OUT_ALL_CSS)
    open(OUT_CSS, "w", encoding="utf-8").write(out_css)
    after_css = os.path.getsize(OUT_CSS)
    print("  subset.css        %7d -> %6d B  (-%d，丢弃 %d 条白名单外的图标规则)"
          % (before_css, after_css, before_css - after_css, removed))

    # 压缩版：走 node clean-css，与 gulp 构建链用同一个压缩器，产物风格一致。
    min_css = minify_css(out_css)
    open(OUT_CSS_MIN, "w", encoding="utf-8").write(min_css)
    print("  subset.min.css    %7d -> %6d B  （clean-css 压缩自 subset.css）"
          % (after_css, os.path.getsize(OUT_CSS_MIN)))

    old_total = src_total + len(raw_css)
    new_total = total_new + after_css
    print("\n合计（字体 + 样式表）: %d -> %d B  （-%d B，-%.1f%%）"
          % (old_total, new_total, old_total - new_total, 100.0 * (old_total - new_total) / old_total))


def minify_css(css_text):
    """用 node + clean-css 压缩 CSS（与 gulp 构建链同一压缩器）。"""
    import subprocess
    node = shutil.which("node") or "node"
    script = (
        "const CleanCSS=require('clean-css');"
        "const fs=require('fs');"
        "const out=new CleanCSS({level:1}).minify(fs.readFileSync(0,'utf8'));"
        "process.stdout.write(out.styles);"
    )
    res = subprocess.run(
        [node, "-e", script],
        input=css_text.encode("utf-8"),
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        cwd=ROOT,
    )
    if res.returncode != 0:
        raise RuntimeError("clean-css 压缩失败: %s" % res.stderr.decode("utf-8", "replace"))
    return res.stdout.decode("utf-8")


if __name__ == "__main__":
    main()
