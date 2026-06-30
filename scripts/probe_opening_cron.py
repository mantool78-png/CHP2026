#!/usr/bin/env python3
from __future__ import annotations

import ftplib
import ssl
import sys
import urllib.error
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
        out[k.strip()] = v.strip()
    return out


def main() -> int:
    env = load_env(ROOT / ".beget-ftp.env")
    host = env.get("FTP_HOST", "").strip()
    user = env.get("FTP_USER", "").strip()
    password = env.get("FTP_PASSWORD", "").strip()
    ftp = ftplib.FTP(host, timeout=90)
    ftp.login(user, password)
    ftp.set_pasv(True)
    for remote in (
        "/public/cron_opening_match_reminder.php",
        "/app/match_reminders.php",
        "/app/mail.php",
        "/.htaccess",
    ):
        chunks: list[bytes] = []
        try:
            ftp.retrbinary(f"RETR {remote}", chunks.append)
            body = b"".join(chunks).decode("utf-8", "replace")
            print("===", remote, "bytes", len(body))
            if "run_opening_match_reminder_mailout" in body:
                print("  has run_opening_match_reminder_mailout")
            if "mail_send_opening_match_reminder" in body:
                print("  has mail_send_opening_match_reminder")
            if "cron_opening_match_reminder" in body:
                print("  has htaccess rule")
        except ftplib.all_errors as e:
            print("FAIL", remote, e)
    ftp.quit()

    ctx = ssl.create_default_context()
    for url in (
        "https://wc2026.gymacro.ru/cron_opening_match_reminder.php?token=bad",
        "https://wc2026.gymacro.ru/cron_payment_reminders.php?token=bad",
    ):
        try:
            with urllib.request.urlopen(url, timeout=30, context=ctx) as resp:
                print(url, resp.status, resp.read(500).decode("utf-8", "replace"))
        except urllib.error.HTTPError as e:
            print(url, e.code, e.read(500).decode("utf-8", "replace"))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
