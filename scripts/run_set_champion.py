#!/usr/bin/env python3
"""Deploy and run set-champion probe (Spain / ESP by default)."""

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
    code = (sys.argv[1] if len(sys.argv) > 1 else "ESP").strip().upper() or "ESP"
    env = load_env(ROOT / ".beget-ftp.env")
    ftp = ftplib.FTP(env["FTP_HOST"], timeout=90)
    ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
    ftp.set_pasv(True)
    with open(ROOT / "public/cron_set_champion.php", "rb") as f:
        ftp.storbinary("STOR /public/cron_set_champion.php", f)
    chunks: list[bytes] = []
    ftp.retrbinary("RETR /config/config.php", chunks.append)
    ftp.quit()

    cfg = b"".join(chunks).decode("utf-8", "replace")
    token_m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", cfg)
    token = token_m.group(1) if token_m else ""
    if not token:
        print("no token", file=sys.stderr)
        return 2

    q = urllib.parse.urlencode({"token": token, "code": code})
    url = f"https://wc2026.gymacro.ru/public/cron_set_champion.php?{q}"
    body = urllib.request.urlopen(url, timeout=120, context=ssl.create_default_context()).read().decode(
        "utf-8", "replace"
    )
    out = ROOT / "scripts" / "_set_champion_result.txt"
    out.write_text(body, encoding="utf-8")
    print(body)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
