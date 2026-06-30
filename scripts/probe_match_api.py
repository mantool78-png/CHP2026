#!/usr/bin/env python3
"""Probe API-Football mapping for a match (default: Australia vs Turkey)."""

from __future__ import annotations

import ftplib
import json
import re
import ssl
import sys
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


def fetch_token(env: dict[str, str]) -> str:
    ftp = ftplib.FTP(env["FTP_HOST"], timeout=90)
    ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
    ftp.set_pasv(True)
    chunks: list[bytes] = []
    ftp.retrbinary("RETR /config/config.php", chunks.append)
    ftp.quit()
    cfg = b"".join(chunks).decode("utf-8", "replace")
    m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", cfg)
    return m.group(1) if m else ""


def http_get(url: str, timeout: int = 120) -> tuple[int, str]:
    ctx = ssl.create_default_context()
    req = urllib.request.Request(url)
    with urllib.request.urlopen(req, timeout=timeout, context=ctx) as resp:
        return resp.status, resp.read().decode("utf-8", "replace")


def main() -> int:
    env = load_env(ROOT / ".beget-ftp.env")
    token = fetch_token(env)
    if not token:
        print("No cron token", file=sys.stderr)
        return 2

    q = urllib.parse.urlencode({"token": token, "stage": "Групповой этап - матч 6"})
    base = "https://wc2026.gymacro.ru"
    for path in ("/cron_api_football_probe.php", "/public/cron_api_football_probe.php"):
        try:
            status, body = http_get(f"{base}{path}?{q}")
            print(f"=== {path} status={status} ===")
            print(body)
            return 0 if status == 200 else 1
        except Exception as e:
            print("fail", path, e, file=sys.stderr)

    print("Probe endpoint missing — deploy cron_api_football_probe.php first", file=sys.stderr)
    return 3


if __name__ == "__main__":
    raise SystemExit(main())
