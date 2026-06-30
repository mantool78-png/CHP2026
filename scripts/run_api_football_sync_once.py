#!/usr/bin/env python3
"""Trigger production API-Football cron once."""

from __future__ import annotations

import ftplib
import re
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def load_env(path: Path) -> dict[str, str]:
    out: dict[str, str] = {}
    if not path.is_file():
        return out
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, _, v = line.partition("=")
        out[k.strip()] = v.strip().strip('"').strip("'")
    return out


def main() -> int:
    env = load_env(ROOT / ".beget-ftp.env")
    ftp = ftplib.FTP(env["FTP_HOST"], timeout=90)
    ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
    ftp.set_pasv(True)
    chunks: list[bytes] = []
    ftp.retrbinary("RETR /config/config.php", chunks.append)
    ftp.quit()
    cfg = b"".join(chunks).decode("utf-8", "replace")
    token_m = re.search(r"'cron_token'\s*=>\s*'([^']*)'", cfg)
    token = token_m.group(1) if token_m else ""
    if not token:
        print("ABORT: api_football.cron_token missing", file=sys.stderr)
        return 2

    query = urllib.parse.urlencode({"token": token})
    url = f"https://wc2026.gymacro.ru/cron_api_football_sync.php?{query}"
    ctx = ssl.create_default_context()
    with urllib.request.urlopen(url, timeout=180, context=ctx) as resp:
        print(resp.read().decode("utf-8", "replace").strip())
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
