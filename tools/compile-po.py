#!/usr/bin/env python3
"""使用 polib 把 .po 编译成 WordPress 可读取的 .mo。"""
import sys
import polib


def main():
    if len(sys.argv) < 2:
        print(f'Usage: {sys.argv[0]} <po-file> [mo-file]', file=sys.stderr)
        sys.exit(1)
    po_path = sys.argv[1]
    mo_path = sys.argv[2] if len(sys.argv) > 2 else po_path.replace('.po', '.mo')

    pofile = polib.pofile(po_path)
    pofile.save_as_mofile(mo_path)
    print(f'Wrote {mo_path} ({len(pofile)} entries)')


if __name__ == '__main__':
    main()
