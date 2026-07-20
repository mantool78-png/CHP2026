#!/usr/bin/env python3
"""Lookup prize winner emails on production."""

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


def token_from_config(text: str) -> str:
    m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", text)
    return m.group(1) if m else ""


def fetch(token: str, name: str) -> str:
    q = urllib.parse.urlencode({"token": token, "name": name})
    url = f"https://wc2026.gymacro.ru/public/cron_probe_prediction.php?{q}"
    return urllib.request.urlopen(url, timeout=90, context=ssl.create_default_context()).read().decode(
        "utf-8", "replace"
    )


def main() -> int:
    env = load_env(ROOT / ".beget-ftp.env")
    local_token = token_from_config((ROOT / "config" / "config.php").read_text(encoding="utf-8"))

    ftp = ftplib.FTP(env["FTP_HOST"], timeout=120)
    ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
    ftp.set_pasv(True)
    with open(ROOT / "public/cron_probe_prediction.php", "rb") as f:
        ftp.storbinary("STOR /public/cron_probe_prediction.php", f)
    chunks: list[bytes] = []
    try:
        ftp.retrbinary("RETR /config/config.php", chunks.append)
    except Exception as e:
        print("config retrieve warn", e, file=sys.stderr)
    ftp.quit()

    remote_token = token_from_config(b"".join(chunks).decode("utf-8", "replace")) if chunks else ""
    token = remote_token or local_token
    if not token:
        print("no token", file=sys.stderr)
        return 2

    for name in ["Филиппов", "Яночка"]:
        print("=" * 60)
        print("QUERY", name)
        body = fetch(token, name)
        print(body[:2500])
        print()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
