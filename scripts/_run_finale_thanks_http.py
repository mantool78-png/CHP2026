#!/usr/bin/env python3
"""Call production finale-thanks cron using local token (no FTP)."""

from __future__ import annotations

import re
import ssl
import sys
import time
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def token_from_config(text: str) -> str:
    m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", text)
    return m.group(1) if m else ""


def http_get(url: str, timeout: int = 300) -> str:
    req = urllib.request.Request(url, headers={"User-Agent": "CHP2026-finale-thanks/1.0"})
    with urllib.request.urlopen(url=req, timeout=timeout, context=ssl.create_default_context()) as resp:
        return resp.read().decode("utf-8", "replace")


def main() -> int:
    token = token_from_config((ROOT / "config" / "config.php").read_text(encoding="utf-8"))
    if not token:
        print("no token", file=sys.stderr)
        return 2

    base = "https://wc2026.gymacro.ru/public/cron_finale_thanks.php"
    status_url = base + "?" + urllib.parse.urlencode({"token": token})
    print("--- status ---", flush=True)
    try:
        status = http_get(status_url, timeout=60)
        print(status, flush=True)
    except Exception as e:
        print("status error:", e, flush=True)
        status = ""

    if "completed=" in status and re.search(r"completed=\S+", status) and "completed=" in status:
        # If already completed with progress covering all, do not resend.
        m_prog = re.search(r"progress_sent_ids=(\d+)", status)
        m_total = re.search(r"recipients=(\d+)|total=(\d+)", status)
        if m_prog and m_total:
            prog = int(m_prog.group(1))
            total = int(m_total.group(1) or m_total.group(2) or 0)
            if prog >= total > 0:
                print("already complete, no resend", flush=True)
                (ROOT / "scripts" / "_finale_thanks_result.txt").write_text(status, encoding="utf-8")
                return 0

    send = "--send" in sys.argv
    if not send:
        print("dry status only; pass --send to confirm", flush=True)
        return 0

    url = base + "?" + urllib.parse.urlencode({"token": token, "confirm": "yes"})
    last_body = ""
    for attempt in range(1, 10):
        print(f"--- attempt {attempt} ---", flush=True)
        try:
            last_body = http_get(url, timeout=360)
            print(last_body, flush=True)
            m_sent = re.search(r"sent=(\d+)", last_body)
            m_skip = re.search(r"skipped=(\d+)", last_body)
            m_total = re.search(r"total=(\d+)", last_body)
            m_fail = re.search(r"failed=(\d+)", last_body)
            if not (m_sent and m_skip and m_total and m_fail):
                time.sleep(2)
                continue
            sent = int(m_sent.group(1))
            skipped = int(m_skip.group(1))
            total = int(m_total.group(1))
            failed = int(m_fail.group(1))
            if sent == 0 and skipped + failed >= total:
                break
            if failed == 0 and sent + skipped >= total:
                break
            time.sleep(2)
        except Exception as e:
            print("attempt error:", e, flush=True)
            time.sleep(3)

    out = ROOT / "scripts" / "_finale_thanks_result.txt"
    out.write_text(last_body, encoding="utf-8")
    print("saved", out, flush=True)
    return 0 if "ok" in last_body else 1


if __name__ == "__main__":
    raise SystemExit(main())
