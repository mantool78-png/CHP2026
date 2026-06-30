#!/usr/bin/env python3
"""Build slightly waving SVG flags for all 48 World Cup 2026 teams.

Uses PNG from flagcdn (reliable) embedded in a waving SVG shell — complex SVG
sources often break in <img> due to missing xlink xmlns or huge path data.
"""

from __future__ import annotations

import base64
import sys
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "public" / "assets" / "flags"

TEAMS: dict[str, str] = {
    "MEX": "mx",
    "RSA": "za",
    "KOR": "kr",
    "CZE": "cz",
    "CAN": "ca",
    "BIH": "ba",
    "QAT": "qa",
    "SUI": "ch",
    "BRA": "br",
    "MAR": "ma",
    "HAI": "ht",
    "SCO": "gb-sct",
    "USA": "us",
    "PAR": "py",
    "AUS": "au",
    "TUR": "tr",
    "GER": "de",
    "CUW": "cw",
    "CIV": "ci",
    "ECU": "ec",
    "NED": "nl",
    "JPN": "jp",
    "SWE": "se",
    "TUN": "tn",
    "BEL": "be",
    "EGY": "eg",
    "IRN": "ir",
    "NZL": "nz",
    "ESP": "es",
    "CPV": "cv",
    "KSA": "sa",
    "URU": "uy",
    "FRA": "fr",
    "SEN": "sn",
    "IRQ": "iq",
    "NOR": "no",
    "ARG": "ar",
    "ALG": "dz",
    "AUT": "at",
    "JOR": "jo",
    "POR": "pt",
    "COD": "cd",
    "UZB": "uz",
    "COL": "co",
    "ENG": "gb-eng",
    "CRO": "hr",
    "GHA": "gh",
    "PAN": "pa",
}

WAVE_TEMPLATE = """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 72 48" role="img" aria-hidden="true">
  <defs>
    <clipPath id="wave-{code}">
      <path d="M1,6 C12,2 24,7 36,4 C48,1 60,6 71,4 L71,44 C60,47 48,42 36,45 C24,48 12,43 1,44 Z"/>
    </clipPath>
    <filter id="sh-{code}" x="-10%" y="-10%" width="120%" height="130%">
      <feDropShadow dx="0" dy="1.2" stdDeviation="0.9" flood-color="#000" flood-opacity="0.22"/>
    </filter>
  </defs>
  <g clip-path="url(#wave-{code})" filter="url(#sh-{code})" transform="rotate(-1.5 36 24)">
    <image href="data:image/png;base64,{b64}" x="0" y="0" width="72" height="48" preserveAspectRatio="xMidYMid slice"/>
  </g>
  <path d="M1,6 C12,2 24,7 36,4 C48,1 60,6 71,4" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="0.6"/>
  <path d="M1,44 C12,47 24,42 36,45 C48,48 60,43 71,44" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="0.6"/>
</svg>
"""

PNG_URLS = (
    "https://flagcdn.com/w640/{iso2}.png",
    "https://flagcdn.com/24x18/{iso2}.png",
    "https://flagcdn.com/w320/{iso2}.png",
)
MIN_PNG_BYTES = 300
API_SPORTS_FLAG_URL = "https://media.api-sports.io/flags/{iso2}.svg"


def fetch_png(iso2: str) -> bytes:
    best: bytes | None = None
    last_err: Exception | None = None

    for tpl in PNG_URLS:
        url = tpl.format(iso2=iso2)
        req = urllib.request.Request(url, headers={"User-Agent": "CHP2026-flag-builder/2.0"})
        try:
            with urllib.request.urlopen(req, timeout=45) as resp:
                data = resp.read()
        except (urllib.error.URLError, TimeoutError) as exc:
            last_err = exc
            continue

        if len(data) >= MIN_PNG_BYTES and (best is None or len(data) > len(best)):
            best = data

    if best is not None:
        return best

    if last_err is not None:
        raise last_err

    raise ValueError("PNG too small")


def fetch_api_sports_svg(iso2: str) -> str:
    url = API_SPORTS_FLAG_URL.format(iso2=iso2)
    req = urllib.request.Request(url, headers={"User-Agent": "CHP2026-flag-builder/2.0"})
    with urllib.request.urlopen(req, timeout=45) as resp:
        svg = resp.read().decode("utf-8", errors="replace").strip()

    if len(svg) < 120 or "<svg" not in svg:
        raise ValueError("SVG too small")

    return svg


def main() -> int:
    OUT.mkdir(parents=True, exist_ok=True)
    errors: list[str] = []

    for code, iso2 in TEAMS.items():
        out_path = OUT / f"{code}.svg"
        try:
            png = fetch_png(iso2)
            b64 = base64.b64encode(png).decode("ascii")
            out_path.write_text(WAVE_TEMPLATE.format(code=code, b64=b64), encoding="utf-8")
            kb = out_path.stat().st_size // 1024
            print(f"ok {code} ({iso2}) wave {kb}KB")
        except (urllib.error.URLError, TimeoutError, ValueError) as exc:
            try:
                svg = fetch_api_sports_svg(iso2)
                out_path.write_text(svg, encoding="utf-8")
                kb = out_path.stat().st_size // 1024
                print(f"ok {code} ({iso2}) flat-svg {kb}KB (fallback)")
            except (urllib.error.URLError, TimeoutError, ValueError) as fallback_exc:
                errors.append(f"{code}: {exc}; fallback: {fallback_exc}")
                print(f"fail {code}: {exc}; fallback: {fallback_exc}", file=sys.stderr)

    if errors:
        print(f"\n{len(errors)} error(s)", file=sys.stderr)
        return 1

    print(f"\ndone: {len(TEAMS)} flags -> {OUT}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
