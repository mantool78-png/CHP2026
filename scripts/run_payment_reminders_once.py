#!/usr/bin/env python3
"""One-shot: read cron token from server config via FTP and trigger payment reminders."""

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
        out[k.strip()] = v.strip()
    return out


def main() -> int:
    env = load_env(ROOT / ".beget-ftp.env")
    host = env.get("FTP_HOST", "").strip()
    user = env.get("FTP_USER", "").strip()
    password = env.get("FTP_PASSWORD", "").strip()
    if not host or not user or not password:
        print("Missing FTP credentials in .beget-ftp.env", file=sys.stderr)
        return 2

    ftp = ftplib.FTP(host, timeout=90)
    ftp.login(user, password)
    ftp.set_pasv(True)
    chunks: list[bytes] = []
    ftp.retrbinary("RETR /config/config.php", chunks.append)
    ftp.quit()

    cfg = b"".join(chunks).decode("utf-8", "replace")
    enabled = bool(re.search(r"'enabled'\s*=>\s*true", cfg))
    token_m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", cfg)
    token = token_m.group(1) if token_m else ""

    print("mail_enabled", enabled)
    print("token_present", bool(token))

    if not enabled:
        print("ABORT: mail.enabled is false in server config.php", file=sys.stderr)
        return 3
    if not token:
        print("ABORT: reminder_cron_token missing", file=sys.stderr)
        return 4

    query = urllib.parse.urlencode({"token": token})
    last_err: Exception | None = None
    for base in ("https://wc2026.gymacro.ru", "http://wc2026.gymacro.ru"):
        url = f"{base}/cron_payment_reminders.php?{query}"
        try:
            req = urllib.request.Request(url)
            ctx = ssl.create_default_context()
            with urllib.request.urlopen(req, timeout=180, context=ctx) as resp:
                body = resp.read().decode("utf-8", "replace")
                print("status", resp.status)
                print(body.strip())
                if "mail_not_configured" in body:
                    return 5
                return 0
        except urllib.error.HTTPError as e:
            body = e.read().decode("utf-8", "replace")
            print("http_error", base, e.code, body.strip(), file=sys.stderr)
            last_err = e
        except Exception as e:
            print("error", base, type(e).__name__, str(e)[:200], file=sys.stderr)
            last_err = e

    if last_err:
        print("FAILED", last_err, file=sys.stderr)
        return 1
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
