# -*- coding: utf-8 -*-
"""
解析 FontAwesome 的 all.min.css，建立 class -> content 码位 的映射。

注意：minified CSS 里一条 content 规则常带多个选择器，例如
    .fa-gauge-high:before,.fa-tachometer-alt:before{content:"\f625"}
如果只按 `:before{content:` 逐个名字匹配，只有最后一个选择器能被正则命中
（前面的名字后面跟的是 `:before,` 而非 `:before{`）。
所以这里必须先把「选择器组」整体取出，再从组内拆出所有名字。
"""
import os
import re

FA_CSS = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                      "assets", "fonts", "fa", "all.min.css")

# 匹配 :before{content:"\XXXX"} 之前的选择器组
RULE = re.compile(r'([^{}]+?):{1,2}before\{content:"\\([0-9a-fA-F]{1,4})"', re.I)
# 选择器组里可能出现 .class / .class::after 等，只取 class 名
NAME = re.compile(r'\.([A-Za-z0-9_-]+)')


def parse(css_path=FA_CSS):
    """返回 (class->码位列表 有序字典, 全部码位集合)

    同一个 class 在 FA CSS 里可能出现多条 content 规则（FA6 把品牌图标
    从 U+F2xx 迁到 U+E2xx，会同时保留新旧两条），这里全部收集 —— 裁剪
    字体时用并集，宁可多带一个码位也不能少，否则会渲染成豆腐块。
    """
    css = open(css_path, encoding="utf-8").read()
    mapping = {}
    codes = set()
    for sel_group, code in RULE.findall(css):
        for name in NAME.findall(sel_group):
            if not name.startswith("fa-"):
                continue
            mapping.setdefault(name, [])
            if code not in mapping[name]:
                mapping[name].append(code)
            codes.add(int(code, 16))
    return mapping, codes


if __name__ == "__main__":
    mapping, codes = parse()
    print("class 映射数 : %d" % len(mapping))
    print("码位去重数   : %d" % len(codes))
    for c in ["fa-gauge-high", "fa-ban", "fa-bolt", "fa-comment-dots",
              "fa-magnifying-glass", "fa-arrow-up-right-from-square", "fa-check"]:
        print("  %-32s -> %s" % (c, mapping.get(c, "**MISS**")))
