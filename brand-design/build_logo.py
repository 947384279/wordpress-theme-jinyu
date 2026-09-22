#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""七彩云博客 · 品牌资产构建流水线

输入 : brand-design/icon-source.jpg  (白底 AI 生成的虹彩云图标)
输出 : mark.png        纯图标母版 (透明底, 正方形)
       favicon.ico     多尺寸站点图标 (透明底纯图标)
       logo.png        浅色模式 lockup (透明底: 图标 + 深墨字标)
       logob.png       深色模式 lockup (透明底: 同一图标 + 冷白字标)
       preview.png     交付预览
       favicon-check.png  多尺寸可读性校验

改一行常量即可换字体 / 换色 / 换尺寸。依赖仅 Pillow。
"""
import os
from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, "icon-source.jpg")

# ---------------- 字标 ----------------
WORDMARK = "七彩云博客"
FONT_PATH = r"C:\Windows\Fonts\Source Han Serif SC Heavy (TrueType).ttf"  # 思源宋体特重
LABEL_FONT = r"C:\Windows\Fonts\msyh.ttc"
INK = (22, 22, 42, 255)          # #16162E  浅色模式字色
SNOW = (238, 241, 255, 255)      # #EEF1FF  深色模式字色

# ---------------- 规格 ----------------
LOCK_W, LOCK_H = 600, 114        # 对齐线上 logo 尺寸
LOCK_PAD_V = 8                   # lockup 上下留白
LOCK_PAD_L = 12                  # lockup 左起留白
LOCK_GAP = 14                    # 图标与字标间距
WORD_SIZE = 62                   # 字标字号
MARK_PX = 512                    # 纯图标母版边长

# ---------------- 抠底参数 (白底 -> 透明) ----------------
KEY_LO, KEY_HI = 46, 150         # 距白距离(0..765): <=LO 全透明, >=HI 全不透明, 中间羽化
FAVICON_SIZES = [16, 24, 32, 48, 64, 128, 256]
BG_LIGHT = (250, 250, 252, 255)
BG_DARK = (16, 17, 28, 255)


def white_to_alpha(img, lo=KEY_LO, hi=KEY_HI):
    """纯白背景 -> 透明 alpha, 边缘按距离羽化, 保留彩色云体。"""
    img = img.convert("RGB")
    w, h = img.size
    src = img.load()
    out = Image.new("RGBA", (w, h))
    dst = out.load()
    span = hi - lo
    for y in range(h):
        for x in range(w):
            r, g, b = src[x, y]
            d = (255 - r) + (255 - g) + (255 - b)
            if d <= lo:
                a = 0
            elif d >= hi:
                a = 255
            else:
                a = int((d - lo) * 255 / span)
            dst[x, y] = (r, g, b, a)
    return out


def tight_bbox(img, thresh=8):
    alpha = img.getchannel("A").point(lambda v: 255 if v > thresh else 0)
    return alpha.getbbox() or (0, 0, img.width, img.height)


def build_lockup(icon, color):
    """透明底: 图标 + 字标, 整体水平居中、视觉垂直居中。"""
    ih = LOCK_H - LOCK_PAD_V * 2
    iw = max(1, round(icon.width * ih / icon.height))
    ic = icon.resize((iw, ih), Image.LANCZOS)

    font = ImageFont.truetype(FONT_PATH, WORD_SIZE)
    tw = round(font.getlength(WORDMARK))
    total = LOCK_PAD_L + iw + LOCK_GAP + tw
    x0 = max(0, (LOCK_W - total) // 2)

    canvas = Image.new("RGBA", (LOCK_W, LOCK_H), (0, 0, 0, 0))
    canvas.alpha_composite(ic, (x0, LOCK_PAD_V))

    d = ImageDraw.Draw(canvas)
    bb = d.textbbox((0, 0), WORDMARK, font=font)
    ty = (LOCK_H - (bb[3] - bb[1])) // 2 - bb[1]      # 视觉垂直居中
    d.text((x0 + iw + LOCK_GAP, ty), WORDMARK, font=font, fill=color)
    return canvas


def main():
    icon = white_to_alpha(Image.open(SRC))
    tight = icon.crop(tight_bbox(icon))              # 云本体 (非方形)

    # ---- 1. 纯图标母版: 正方形 + 统一留白 ----
    side = max(tight.width, tight.height)
    pad = round(side * 0.025)
    mark = Image.new("RGBA", (side + pad * 2, side + pad * 2), (0, 0, 0, 0))
    mark.alpha_composite(tight, ((mark.width - tight.width) // 2,
                                 (mark.height - tight.height) // 2))
    mark = mark.resize((MARK_PX, MARK_PX), Image.LANCZOS)
    mark.save(os.path.join(HERE, "mark.png"))

    # ---- 2. favicon.ico (多尺寸, 透明底纯图标) ----
    mark.save(os.path.join(HERE, "favicon.ico"),
              format="ICO", sizes=[(s, s) for s in FAVICON_SIZES])

    # ---- 3. lockup 双模式 (同一图标, 仅字标换色) ----
    logo = build_lockup(tight, INK)
    logob = build_lockup(tight, SNOW)
    logo.save(os.path.join(HERE, "logo.png"))
    logob.save(os.path.join(HERE, "logob.png"))

    # ---- 4. 交付预览 ----
    lab = ImageFont.truetype(LABEL_FONT, 15)
    pv = Image.new("RGB", (680, 492), (255, 255, 255))
    d = ImageDraw.Draw(pv)
    d.text((24, 14), "纯图标 · 透明底 (浅底 / 深底 两种语境)", font=lab, fill=(90, 92, 105))

    icp = 128
    ics = mark.resize((icp, icp), Image.LANCZOS)
    for bx, bg in ((24, BG_LIGHT), (356, BG_DARK)):
        tile = Image.new("RGBA", (300, 176), bg)
        tile.alpha_composite(ics, ((300 - icp) // 2, 24))
        pv.paste(tile.convert("RGB"), (bx, 44))

    bands = [
        ("浅色模式 lockup · logo.png", logo, (255, 255, 255)),
        ("深色模式 lockup · logob.png", logob, BG_DARK),
    ]
    y = 246
    for title, art, bg in bands:
        d.text((24, y), title, font=lab, fill=(90, 92, 105))
        band = Image.new("RGB", (632, 116), bg)
        band.paste(art, (16, 1), art)
        pv.paste(band, (24, y + 22))
        y += 124
    pv.save(os.path.join(HERE, "preview.png"))

    # ---- 5. favicon 可读性校验 ----
    show = [16, 24, 32, 48, 64, 128]
    gap, pad = 26, 24
    W = pad * 2 + sum(show) + gap * (len(show) - 1)
    fc = Image.new("RGB", (W, 250), (245, 246, 250))
    d = ImageDraw.Draw(fc)
    d.text((pad, 14), "favicon 多尺寸可读性 (16 → 128 px)", font=lab, fill=(90, 92, 105))
    x = pad
    for s in show:
        f = mark.resize((s, s), Image.LANCZOS)
        fc.paste(f, (x, 64 + (128 - s) // 2), f)
        d.text((x, 44), f"{s}", font=lab, fill=(120, 122, 135))
        x += s + gap
    ty = 208
    d.rectangle((pad, ty - 8, W - pad, ty + 34), fill=(228, 231, 240))
    tab = Image.new("RGBA", (172, 34), (255, 255, 255, 255))
    tab.alpha_composite(mark.resize((18, 18), Image.LANCZOS), (8, 8))
    ImageDraw.Draw(tab).text((32, 9), "七彩云博客",
                             font=ImageFont.truetype(LABEL_FONT, 14), fill=(60, 62, 74))
    fc.paste(tab.convert("RGB"), (pad, ty))
    fc.save(os.path.join(HERE, "favicon-check.png"))

    # ---- 报告 ----
    for n in ("mark.png", "favicon.ico", "logo.png", "logob.png"):
        im = Image.open(os.path.join(HERE, n))
        extra = sorted(im.info.get("sizes", [])) if im.format == "ICO" else ""
        print(f"{n:14s} {im.format:5s} {str(im.size):12s} {im.mode:5s} {extra}")


if __name__ == "__main__":
    main()
