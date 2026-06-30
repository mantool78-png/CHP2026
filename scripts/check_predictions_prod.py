#!/usr/bin/env python3
"""Check production config, caches and match pages for pre-match index."""

from __future__ import annotations

import ftplib
import json
import re
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


def list_prediction_caches() -> list[str]:
    env = load_env(ROOT / ".beget-ftp.env")
    ftp = ftplib.FTP(env["FTP_HOST"], timeout=90)
    ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
    ftp.set_pasv(True)
    names: list[str] = []
    try:
        ftp.cwd("/storage/cache")
        names = [n for n in ftp.nlst() if n.startswith("api_football_predictions_")]
    except ftplib.error_perm:
        pass
    ftp.quit()
    return names


def read_cache_file(name: str) -> dict | None:
    env = load_env(ROOT / ".beget-ftp.env")
    ftp = ftplib.FTP(env["FTP_HOST"], timeout=90)
    ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
    ftp.set_pasv(True)
    chunks: list[bytes] = []
    try:
        ftp.retrbinary(f"RETR /storage/cache/{name}", chunks.append)
    except ftplib.error_perm:
        ftp.quit()
        return None
    ftp.quit()
    try:
        data = json.loads(b"".join(chunks).decode("utf-8", "replace"))
        return data if isinstance(data, dict) else None
    except json.JSONDecodeError:
        return None


def main() -> int:
    cfg = fetch_config()
    for key in ("enabled", "predictions_enabled", "league_id", "season"):
        m = re.search(r"'" + key + r"'\s*=>\s*([^,\n]+)", cfg)
        print(f"api_football.{key}:", m.group(1).strip() if m else "NOT FOUND (defaults on)")

    caches = list_prediction_caches()
    print(f"prediction_cache_files: {len(caches)}")
    available = 0
    errors: dict[str, int] = {}
    for name in caches[:30]:
        data = read_cache_file(name)
        if not data:
            continue
        payload = data.get("payload") if isinstance(data.get("payload"), dict) else {}
        if payload.get("available"):
            available += 1
        else:
            err = str(payload.get("error") or "unknown")
            errors[err] = errors.get(err, 0) + 1
    print(f"cached_available: {available} / sampled {min(len(caches), 30)}")
    if errors:
        print("cache_errors:", errors)

    ctx = ssl.create_default_context()
    shown = 0
    for match_id in range(1, 67):
        url = f"https://wc2026.gymacro.ru/match?id={match_id}"
        try:
            with urllib.request.urlopen(url, timeout=30, context=ctx) as resp:
                if resp.status != 200:
                    continue
                html = resp.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            if e.code == 404:
                continue
            print("error", match_id, e.code)
            continue
        except Exception as e:
            print("error", match_id, e)
            continue

        has_index = "Предматчевый индекс" in html
        if has_index:
            shown += 1
            print(f"match #{match_id}: INDEX VISIBLE")
    print(f"pages_with_index (ids 1-29): {shown}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
