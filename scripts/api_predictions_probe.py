#!/usr/bin/env python3
"""Probe API-Football /predictions for production fixtures."""

from __future__ import annotations

import ftplib
import json
import re
import ssl
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


def cfg_value(cfg: str, key: str) -> str:
    m = re.search(r"'" + key + r"'\s*=>\s*'([^']*)'", cfg)
    return m.group(1) if m else ""


def api_get(path: str, api_key: str) -> dict:
    req = urllib.request.Request(
        "https://v3.football.api-sports.io" + path,
        headers={"x-apisports-key": api_key},
    )
    ctx = ssl.create_default_context()
    with urllib.request.urlopen(req, timeout=60, context=ctx) as resp:
        return json.loads(resp.read().decode("utf-8", "replace"))


def main() -> None:
    cfg = fetch_config()
    api_key = cfg_value(cfg, "api_key")
    if not api_key:
        print("no api_key")
        return

    # Read cached fixture ids from server
    env = load_env(ROOT / ".beget-ftp.env")
    ftp = ftplib.FTP(env["FTP_HOST"], timeout=90)
    ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
    ftp.set_pasv(True)
    ftp.cwd("/storage/cache")
    fixture_ids = []
    for name in ftp.nlst():
        m = re.match(r"api_football_predictions_(\d+)\.json", name)
        if m:
            fixture_ids.append(int(m.group(1)))
    ftp.quit()

    print("cached_fixtures:", fixture_ids)

    probe_ids = fixture_ids[:5] + [1489377, 1489369, 1538999]
    seen: set[int] = set()
    for fid in probe_ids:
        if fid in seen:
            continue
        seen.add(fid)
        data = api_get(f"/predictions?fixture={fid}", api_key)
        item = (data.get("response") or [{}])[0]
        pred = item.get("predictions") or {}
        pct = pred.get("percent") or {}
        comp = (item.get("comparison") or {}).get("total") or {}
        print(
            f"LIVE fixture {fid}: results={data.get('results')} percent={pct} comparison_total={comp} "
            f"winner={(pred.get('winner') or {}).get('name')} advice={pred.get('advice')}"
        )

    # Upcoming WC fixtures from API league
    fixtures = api_get("/fixtures?league=1&season=2026&next=5", api_key)
    print("next_fixtures_count:", len(fixtures.get("response") or []))
    for row in fixtures.get("response") or []:
        fid = (row.get("fixture") or {}).get("id")
        home = (row.get("teams") or {}).get("home", {}).get("name")
        away = (row.get("teams") or {}).get("away", {}).get("name")
        if not fid:
            continue
        pred_data = api_get(f"/predictions?fixture={fid}", api_key)
        item = (pred_data.get("response") or [{}])[0]
        pred = item.get("predictions") or {}
        pct = pred.get("percent") or {}
        comp = (item.get("comparison") or {}).get("total") or {}
        print(
            f"NEXT fixture {fid} {home} vs {away}: percent={pct} comparison_total={comp} "
            f"winner={(pred.get('winner') or {}).get('name')}"
        )


if __name__ == "__main__":
    main()
