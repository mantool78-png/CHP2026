#!/usr/bin/env python3
"""Deploy finale thanks mailout and send in resumable batches."""

from __future__ import annotations

import ftplib
import re
import ssl
import sys
import time
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


def token_from_config(text: str) -> str:
    m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", text)
    return m.group(1) if m else ""


def http_get(url: str, timeout: int = 180) -> str:
    req = urllib.request.Request(url, headers={"User-Agent": "CHP2026-finale-thanks/1.0"})
    with urllib.request.urlopen(url=req, timeout=timeout, context=ssl.create_default_context()) as resp:
        return resp.read().decode("utf-8", "replace")


def main() -> int:
    env = load_env(ROOT / ".beget-ftp.env")
    local_token = token_from_config((ROOT / "config" / "config.php").read_text(encoding="utf-8"))

    ftp = ftplib.FTP(env["FTP_HOST"], timeout=120)
    ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
    ftp.set_pasv(True)
    for local, remote in [
        (ROOT / "app/mail.php", "/app/mail.php"),
        (ROOT / "public/cron_finale_thanks.php", "/public/cron_finale_thanks.php"),
    ]:
        with open(local, "rb") as f:
            ftp.storbinary(f"STOR {remote}", f)
        print("uploaded", remote, flush=True)
    chunks: list[bytes] = []
    try:
        ftp.retrbinary("RETR /config/config.php", chunks.append)
    except Exception as e:
        print("config retrieve warn", e, flush=True)
    ftp.quit()

    token = token_from_config(b"".join(chunks).decode("utf-8", "replace")) if chunks else ""
    token = token or local_token
    if not token:
        print("no token", file=sys.stderr)
        return 2

    status_url = "https://wc2026.gymacro.ru/public/cron_finale_thanks.php?" + urllib.parse.urlencode(
        {"token": token}
    )
    print("--- status ---", flush=True)
    print(http_get(status_url, timeout=60), flush=True)

    q = urllib.parse.urlencode({"token": token, "confirm": "yes"})
    url = f"https://wc2026.gymacro.ru/public/cron_finale_thanks.php?{q}"

    last_body = ""
    for attempt in range(1, 8):
        print(f"--- attempt {attempt} ---", flush=True)
        try:
            last_body = http_get(url, timeout=300)
            print(last_body, flush=True)
            if "ok" in last_body and "failed=" in last_body:
                m_sent = re.search(r"sent=(\d+)", last_body)
                m_skip = re.search(r"skipped=(\d+)", last_body)
                m_total = re.search(r"total=(\d+)", last_body)
                m_fail = re.search(r"failed=(\d+)", last_body)
                sent = int(m_sent.group(1)) if m_sent else -1
                skipped = int(m_skip.group(1)) if m_skip else 0
                total = int(m_total.group(1)) if m_total else 0
                failed = int(m_fail.group(1)) if m_fail else 0
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
