#!/usr/bin/env python3
import re
import ssl
import urllib.parse
import urllib.request

ctx = ssl.create_default_context()
for q in ["Hallback", "Hall", "allback", "Halfback", "17"]:
    url = "https://wc2026.gymacro.ru/predictions?" + urllib.parse.urlencode({"q": q})
    html = urllib.request.urlopen(url, timeout=60, context=ctx).read().decode("utf-8", "replace")
    hits = re.findall(r"/participant\?id=(\d+)[^>]*>([^<]+)</a>", html)
    print(q, hits[:8])
