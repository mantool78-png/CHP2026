#!/usr/bin/env python3
"""Verify deployed mailout files and check cron status."""

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
    for path in ("/app/mail.php", "/app/domain.php", "/public/cron_last_free_match_payment.php"):
        chunks: list[bytes] = []
        ftp.retrbinary("RETR " + path, chunks.append)
        data = b"".join(chunks).decode("utf-8", "replace")
        print("===", path, "bytes", len(data))
        print("  mail_send_last_free_match_payment_notice:", data.count("function mail_send_last_free_match_payment_notice"))
        print("  run_last_free_match_payment_mailout:", data.count("function run_last_free_match_payment_mailout"))
        print("  set_time_limit:", data.count("set_time_limit"))

    chunks = []
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
    url = f"https://wc2026.gymacro.ru/cron_last_free_match_payment.php?{query}"
    print("GET status check:", url.split("token=")[0] + "token=***")
    try:
        with urllib.request.urlopen(url, timeout=30, context=ctx) as resp:
            body = resp.read().decode("utf-8", "replace")
            print("status", resp.status)
            print(body.strip())
    except Exception as e:
        print("error", type(e).__name__, str(e))
        if hasattr(e, "read"):
            print(getattr(e, "read")().decode("utf-8", "replace")[:800])
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
