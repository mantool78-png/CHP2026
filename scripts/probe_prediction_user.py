#!/usr/bin/env python3
"""Probe production: user prediction status for a match."""

from __future__ import annotations

import ftplib
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


def fetch_config() -> str:
    env = load_env(ROOT / ".beget-ftp.env")
    ftp = ftplib.FTP(env["FTP_HOST"], timeout=90)
    ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
    ftp.set_pasv(True)
    chunks: list[bytes] = []
    ftp.retrbinary("RETR /config/config.php", chunks.append)
    ftp.quit()
    return b"".join(chunks).decode("utf-8", "replace")


def upload_probe() -> None:
    env = load_env(ROOT / ".beget-ftp.env")
    ftp = ftplib.FTP(env["FTP_HOST"], timeout=90)
    ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
    ftp.set_pasv(True)
    with open(ROOT / "public/cron_probe_prediction.php", "rb") as f:
        ftp.storbinary("STOR /public/cron_probe_prediction.php", f)
    ftp.quit()


def main() -> int:
    upload_probe()
    print("uploaded probe")

    cfg = fetch_config()
    token_m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", cfg)
    if not token_m:
        print("ABORT: reminder_cron_token missing", file=sys.stderr)
        return 1
    token = token_m.group(1)

    query = urllib.parse.urlencode(
        {
            "token": token,
            "name": "Компаньён",
            "home": "Австрия",
            "away": "Иордания",
        }
    )
    url = f"https://wc2026.gymacro.ru/public/cron_probe_prediction.php?{query}"
    ctx = ssl.create_default_context()
    with urllib.request.urlopen(url, timeout=60, context=ctx) as resp:
        print(resp.read().decode("utf-8", "replace"))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
