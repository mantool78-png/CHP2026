#!/usr/bin/env python3
import re
import ssl
import urllib.request

ctx = ssl.create_default_context()
mid = 3
html = urllib.request.urlopen(f"https://wc2026.gymacro.ru/match?id={mid}", timeout=60, context=ctx).read().decode(
    "utf-8", "replace"
)
if "На этот матч пока нет прогнозов" in html:
    print("predictions_on_match", 0)
else:
    print("has_distribution", "prediction-distribution-container" in html)
if "Мексика" in html or "MEX" in html:
    print("opening_match_page", True)
