#!/usr/bin/env python3
"""Lookup participant email by name on production."""

from __future__ import annotations

import re
import ssl
import sys
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def token_from_config(text: str) -> str:
    m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", text)
    return m.group(1) if m else ""


def main() -> int:
    names = sys.argv[1:] or ["Mike Ivy", "Ivy", "Mike"]
    token = token_from_config((ROOT / "config" / "config.php").read_text(encoding="utf-8"))
    if not token:
        print("no token", file=sys.stderr)
        return 2

    ctx = ssl.create_default_context()
    for name in names:
        q = urllib.parse.urlencode({"token": token, "name": name})
        url = f"https://wc2026.gymacro.ru/public/cron_probe_prediction.php?{q}"
        req = urllib.request.Request(url, headers={"User-Agent": "CHP2026-lookup/1.0"})
        body = urllib.request.urlopen(req, timeout=90, context=ctx).read().decode("utf-8", "replace")
        print("=" * 60)
        print("QUERY", name)
        # print only identity lines, truncate prediction dump
        lines = body.splitlines()
        for line in lines[:40]:
            print(line)
        print()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
