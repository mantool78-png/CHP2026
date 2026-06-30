#!/usr/bin/env python3
import ftplib, json, re, ssl, urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def load_env(path):
    out = {}
    for line in Path(path).read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, _, v = line.partition("=")
        out[k.strip()] = v.strip().strip('"').strip("'")
    return out

env = load_env(ROOT / ".beget-ftp.env")
ftp = ftplib.FTP(env["FTP_HOST"], timeout=90)
ftp.login(env["FTP_USER"], env["FTP_PASSWORD"])
ftp.set_pasv(True)

cfg_chunks = []
ftp.retrbinary("RETR /config/config.php", cfg_chunks.append)
cfg = b"".join(cfg_chunks).decode("utf-8", "replace")
m = re.search(r"'api_key'\s*=>\s*'([^']*)'", cfg)
api_key = m.group(1) if m else ""
m = re.search(r"'league_id'\s*=>\s*(\d+)", cfg)
league_id = m.group(1) if m else "1"
m = re.search(r"'season'\s*=>\s*(\d+)", cfg)
season = m.group(1) if m else "2026"
print("league_id", league_id, "season", season)

for name in sorted(n for n in ftp.nlst("/storage/cache") if n.endswith(".json") and "predictions_" in n)[:8]:
    chunks = []
    ftp.retrbinary(f"RETR /storage/cache/{name.split('/')[-1]}", chunks.append)
    obj = json.loads(b"".join(chunks))
    p = obj.get("payload", {})
    print(name.split("/")[-1], "v", obj.get("version"), "avail", p.get("available"), "err", p.get("error"), "pct", p.get("percent"))

ftp.quit()

ctx = ssl.create_default_context()
for fid in [1489374, 1489377, 1489369]:
    req = urllib.request.Request(
        f"https://v3.football.api-sports.io/predictions?fixture={fid}",
        headers={"x-apisports-key": api_key},
    )
    with urllib.request.urlopen(req, timeout=60, context=ctx) as resp:
        data = json.loads(resp.read())
    print("API", fid, "results", data.get("results"), "errors", data.get("errors"))
    item = (data.get("response") or [None])[0]
    if item:
        pred = item.get("predictions") or {}
        print("  percent", pred.get("percent"), "advice", pred.get("advice"))
        print("  comparison", (item.get("comparison") or {}).get("total"))

req = urllib.request.Request(
    f"https://v3.football.api-sports.io/fixtures?league={league_id}&season={season}&next=3",
    headers={"x-apisports-key": api_key},
)
with urllib.request.urlopen(req, timeout=60, context=ctx) as resp:
    fixtures = json.loads(resp.read())
print("next fixtures", fixtures.get("results"), fixtures.get("errors"))
for row in fixtures.get("response") or []:
    fid = row["fixture"]["id"]
    home = row["teams"]["home"]["name"]
    away = row["teams"]["away"]["name"]
    req2 = urllib.request.Request(
        f"https://v3.football.api-sports.io/predictions?fixture={fid}",
        headers={"x-apisports-key": api_key},
    )
    with urllib.request.urlopen(req2, timeout=60, context=ctx) as resp2:
        pred_data = json.loads(resp2.read())
    item = (pred_data.get("response") or [None])[0]
    pred = (item or {}).get("predictions") or {}
    print(f"  {fid} {home} vs {away} pct={pred.get('percent')}")
