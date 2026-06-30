#!/usr/bin/env python3
"""Fetch free-trial prediction audit from production."""

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


def main() -> int:
    env = load_env(ROOT / ".beget-ftp.env")
    host = env.get("FTP_HOST", "").strip()
    user = env.get("FTP_USER", "").strip()
    password = env.get("FTP_PASSWORD", "").strip()
    if not host or not user or not password:
        print("Missing FTP credentials", file=sys.stderr)
        return 2

    ftp = ftplib.FTP(host, timeout=90)
    ftp.login(user, password)
    ftp.set_pasv(True)
    chunks: list[bytes] = []
    ftp.retrbinary("RETR /config/config.php", chunks.append)
    ftp.quit()
    cfg = b"".join(chunks).decode("utf-8", "replace")
    token_m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", cfg)
    token = token_m.group(1) if token_m else ""
    if not token:
        print("No token", file=sys.stderr)
        return 3

    query = urllib.parse.urlencode({"token": token})
    ctx = ssl.create_default_context()
    url = f"https://wc2026.gymacro.ru/cron_audit_free_trial.php?{query}"
    with urllib.request.urlopen(url, timeout=60, context=ctx) as resp:
        print(resp.read().decode("utf-8", "replace"))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
