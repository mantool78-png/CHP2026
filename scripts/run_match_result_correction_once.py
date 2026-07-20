#!/usr/bin/env python3
"""One-shot: send match result correction emails on production."""

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
        out[k.strip()] = v.strip().strip('"').strip("'")
    return out


def main() -> int:
    dry_run = "--dry-run" in sys.argv
    match_id = 84
    prev_home = 3
    prev_away = 2
    for arg in sys.argv[1:]:
        if arg.startswith("--match-id="):
            match_id = int(arg.split("=", 1)[1])
        elif arg.startswith("--prev="):
            a, b = arg.split("=", 1)[1].split(":", 1)
            prev_home, prev_away = int(a), int(b)

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
    print("match_id", match_id)
    print("prev_score", f"{prev_home}:{prev_away}")
    print("dry_run", dry_run)

    if not enabled:
        print("ABORT: mail.enabled is false in server config.php", file=sys.stderr)
        return 3
    if not token:
        print("ABORT: reminder_cron_token missing", file=sys.stderr)
        return 4

    params = {
        "token": token,
        "match_id": match_id,
        "prev_home": prev_home,
        "prev_away": prev_away,
    }
    if dry_run:
        params["dry_run"] = "1"
    query = urllib.parse.urlencode(params)
    url = f"https://wc2026.gymacro.ru/cron_match_result_correction.php?{query}"
    ctx = ssl.create_default_context()
    try:
        with urllib.request.urlopen(url, timeout=300, context=ctx) as resp:
            body = resp.read().decode("utf-8", "replace")
            print(body.strip())
            if "mail_not_configured" in body:
                return 5
            if "failed=" in body:
                for line in body.splitlines():
                    if line.startswith("failed="):
                        if int(line.split("=", 1)[1]) > 0:
                            return 6
            return 0
    except urllib.error.HTTPError as e:
        print(e.read().decode("utf-8", "replace"), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
