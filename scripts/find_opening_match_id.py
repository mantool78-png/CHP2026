#!/usr/bin/env python3
import re
import ssl
import urllib.request

ctx = ssl.create_default_context()

for mid in range(1, 12):
    url = f"https://wc2026.gymacro.ru/match?id={mid}"
    try:
        html = urllib.request.urlopen(url, timeout=30, context=ctx).read().decode("utf-8", "replace")
    except Exception as e:
        print(mid, "ERR", e)
        continue
    if "Страница не найдена" in html or "не найден" in html.lower():
        print(mid, "404")
        continue
    title = re.search(r"<h1[^>]*>([^<]+)", html)
    rsa = "RSA" in html or "ZAF" in html
    mex = "MEX" in html
    print(mid, title.group(1).strip() if title else "?", "MEX", mex, "RSA", rsa)
