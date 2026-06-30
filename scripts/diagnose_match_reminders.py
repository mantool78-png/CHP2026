#!/usr/bin/env python3
"""Diagnose match reminder cron on production."""

from __future__ import annotations

import ftplib
import re
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timedelta, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MSK = timezone(timedelta(hours=3))


def load_env(path: Path) -> dict[str, str]:
    out: dict[str, str] = {}
    if not path.is_file():
        return out
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, _, v = line.partition("=")
        out[k.strip()] = v.strip()
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

    mail_enabled = bool(re.search(r"'enabled'\s*=>\s*true", cfg))
    token_m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", cfg)
    token = token_m.group(1) if token_m else ""

    now = datetime.now(MSK)
    win_start = now + timedelta(minutes=50)
    win_end = now + timedelta(minutes=75)

    print("now_msk", now.strftime("%Y-%m-%d %H:%M:%S"))
    print("reminder_window", win_start.strftime("%H:%M"), "-", win_end.strftime("%H:%M"), "(matches starting in this range)")
    print("mail_enabled", mail_enabled)
    print("token_present", bool(token))

    if not token:
        print("ABORT: no token")
        return 4

    query = urllib.parse.urlencode({"token": token})
    ctx = ssl.create_default_context()
    for path in ("/cron_match_reminders.php", "/public/cron_match_reminders.php"):
        url = f"https://wc2026.gymacro.ru{path}?{query}"
        try:
            with urllib.request.urlopen(url, timeout=120, context=ctx) as resp:
                body = resp.read().decode("utf-8", "replace")
                print("url", url.split("?")[0])
                print("status", resp.status)
                print(body.strip())
                return 0
        except urllib.error.HTTPError as e:
            print("fail", path, e.code, e.read()[:120])
        except Exception as e:
            print("fail", path, type(e).__name__, str(e)[:120])

    return 1


if __name__ == "__main__":
    raise SystemExit(main())
