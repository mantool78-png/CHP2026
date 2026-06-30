#!/usr/bin/env python3
"""Run last-free-match payment mailout in batches (Beget PHP time limit)."""

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


def fetch_token(env: dict[str, str]) -> str:
    host = env.get("FTP_HOST", "").strip()
    user = env.get("FTP_USER", "").strip()
    password = env.get("FTP_PASSWORD", "").strip()
    ftp = ftplib.FTP(host, timeout=90)
    ftp.login(user, password)
    ftp.set_pasv(True)
    chunks: list[bytes] = []
    ftp.retrbinary("RETR /config/config.php", chunks.append)
    ftp.quit()
    cfg = b"".join(chunks).decode("utf-8", "replace")
    token_m = re.search(r"'reminder_cron_token'\s*=>\s*'([^']*)'", cfg)
    return token_m.group(1) if token_m else ""


def call_batch(token: str, *, force: bool, batch: int) -> tuple[int, str]:
    params: dict[str, str] = {"token": token, "batch": str(batch)}
    if force:
        params["force"] = "1"
    query = urllib.parse.urlencode(params)
    ctx = ssl.create_default_context()
    last_err: Exception | None = None
    for base in ("https://wc2026.gymacro.ru", "http://wc2026.gymacro.ru"):
        url = f"{base}/cron_last_free_match_payment.php?{query}"
        try:
            req = urllib.request.Request(url)
            with urllib.request.urlopen(req, timeout=180, context=ctx) as resp:
                return resp.status, resp.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            body = e.read().decode("utf-8", "replace")
            return e.code, body
        except Exception as e:
            last_err = e
    if last_err:
        raise last_err
    raise RuntimeError("no response")


def parse_field(body: str, key: str) -> str:
    prefix = key + "="
    for line in body.splitlines():
        if line.startswith(prefix):
            return line[len(prefix) :].strip()
    return ""


def main() -> int:
    force = "--force" in sys.argv[1:]
    batch = 20
    for arg in sys.argv[1:]:
        if arg.startswith("--batch="):
            batch = max(1, min(50, int(arg.split("=", 1)[1])))

    env = load_env(ROOT / ".beget-ftp.env")
    if not env.get("FTP_HOST") or not env.get("FTP_USER") or not env.get("FTP_PASSWORD"):
        print("Missing FTP credentials in .beget-ftp.env", file=sys.stderr)
        return 2

    token = fetch_token(env)
    if not token:
        print("ABORT: reminder_cron_token missing", file=sys.stderr)
        return 4

    print("force", force, "batch", batch)
    first = True
    total_sent = 0
    while True:
        status, body = call_batch(token, force=force and first, batch=batch)
        print("---")
        print("status", status)
        print(body.strip())
        if status != 200:
            return 1
        if "already_sent=1" in body:
            return 6
        if "mail_not_configured" in body:
            return 5
        if body.strip().startswith("error="):
            return 1

        sent = int(parse_field(body, "sent") or "0")
        failed = int(parse_field(body, "failed") or "0")
        done = parse_field(body, "done") == "1"
        total_sent += sent
        first = False

        if failed > 0:
            print("STOP: batch had failures", file=sys.stderr)
            return 7
        if done:
            print("ALL DONE total_sent", total_sent)
            return 0

        time.sleep(1)


if __name__ == "__main__":
    raise SystemExit(main())
