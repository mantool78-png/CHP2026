#!/usr/bin/env python3
"""Probe tournament progress on a match page."""

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
    match_id = int(sys.argv[1]) if len(sys.argv) > 1 else 25

    env = load_env(ROOT / ".beget-ftp.env")
    ftp = ftplib.FTP(env["FTP_HOST"], timeout=90)
    ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
    ftp.set_pasv(True)
    with open(ROOT / "public/cron_probe_prediction.php", "rb") as f:
        ftp.storbinary("STOR /public/cron_probe_prediction.php", f)
    ftp.quit()

    chunks: list[bytes] = []
    ftp = ftplib.FTP(env["FTP_HOST"], timeout=90)
    ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
    ftp.set_pasv(True)
    ftp.retrbinary("RETR /config/config.php", chunks.append)
    ftp.quit()
    cfg = b"".join(chunks).decode("utf-8", "replace")
    token_m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", cfg)
    token = token_m.group(1) if token_m else ""

    ctx = ssl.create_default_context()

    for home, away in [("Чехия", "ЮАР"), ("", "")]:
        q = urllib.parse.urlencode(
            {
                "token": token,
                "name": "x",
                "home": home or "Чехия",
                "away": away or "ЮАР",
            }
        )
        url = f"https://wc2026.gymacro.ru/public/cron_probe_prediction.php?{q}"
        print("=== probe db ===")
        with urllib.request.urlopen(url, timeout=60, context=ctx) as resp:
            text = resp.read().decode("utf-8", "replace")
            for line in text.splitlines():
                if any(
                    key in line
                    for key in (
                        "match_id=",
                        "tournament_progress",
                        "_games:",
                        " vs ",
                        "status=",
                    )
                ):
                    print(line)

    page_url = f"https://wc2026.gymacro.ru/match?id={match_id}"
    with urllib.request.urlopen(page_url, timeout=60, context=ctx) as resp:
        html = resp.read().decode("utf-8", "replace")
    print("=== page ===")
    print("url", page_url)
    print("has_section", "На турнире" in html)
    print("games", html.count("match-tournament-game"))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
