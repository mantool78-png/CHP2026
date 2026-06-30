#!/usr/bin/env python3
"""Probe participant prediction status on production."""

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
    name = sys.argv[1] if len(sys.argv) > 1 else "Hallback"
    home = sys.argv[2] if len(sys.argv) > 2 else ""
    away = sys.argv[3] if len(sys.argv) > 3 else ""

    upload_probe()
    cfg = fetch_config()
    token_m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", cfg)
    token = token_m.group(1) if token_m else ""
    lock_m = re.search(r"'prediction_lock_minutes'\s*=>\s*(\d+)", cfg)
    print("prediction_lock_minutes=", lock_m.group(1) if lock_m else "default 5")

    q = urllib.parse.urlencode({"token": token, "name": name, "home": home, "away": away})
    if name.endswith("17"):
        q2 = urllib.parse.urlencode({"token": token, "name": name, "exact": "1"})
    else:
        q2 = None
    url = f"https://wc2026.gymacro.ru/public/cron_probe_prediction.php?{q}"
    ctx = ssl.create_default_context()
    with urllib.request.urlopen(url, timeout=60, context=ctx) as resp:
        print(resp.read().decode("utf-8", "replace"))
    if q2:
        url2 = f"https://wc2026.gymacro.ru/public/cron_probe_prediction.php?{q2}"
        print("=== exact name ===")
        with urllib.request.urlopen(url2, timeout=60, context=ctx) as resp:
            print(resp.read().decode("utf-8", "replace"))
    for alt in ("hall", "17", "allback"):
        if alt.lower() in name.lower():
            continue
        q_alt = urllib.parse.urlencode({"token": token, "name": alt})
        url_alt = f"https://wc2026.gymacro.ru/public/cron_probe_prediction.php?{q_alt}"
        print(f"=== search {alt} ===")
        with urllib.request.urlopen(url_alt, timeout=60, context=ctx) as resp:
            body = resp.read().decode("utf-8", "replace")
            if "users_found=0" not in body:
                print(body)
            else:
                print("users_found=0")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
