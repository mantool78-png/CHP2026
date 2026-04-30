import csv
import re
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

import fitz


ROOT = Path(__file__).resolve().parents[1]
PDF_PATH = ROOT / "database" / "fifa_schedule.pdf"
OUT_PATH = ROOT / "database" / "fifa_2026_schedule_moscow.csv"

TEAM_NAMES = {
    "MEX": "Мексика",
    "RSA": "ЮАР",
    "KOR": "Республика Корея",
    "CZE": "Чехия",
    "CAN": "Канада",
    "BIH": "Босния и Герцеговина",
    "USA": "США",
    "PAR": "Парагвай",
    "BRA": "Бразилия",
    "MAR": "Марокко",
    "HAI": "Гаити",
    "SCO": "Шотландия",
    "AUS": "Австралия",
    "TUR": "Турция",
    "QAT": "Катар",
    "SUI": "Швейцария",
    "CIV": "Кот-д’Ивуар",
    "ECU": "Эквадор",
    "GER": "Германия",
    "CUW": "Кюрасао",
    "NED": "Нидерланды",
    "JPN": "Япония",
    "SWE": "Швеция",
    "TUN": "Тунис",
    "KSA": "Саудовская Аравия",
    "URU": "Уругвай",
    "ESP": "Испания",
    "CPV": "Кабо-Верде",
    "IRN": "Иран",
    "NZL": "Новая Зеландия",
    "BEL": "Бельгия",
    "EGY": "Египет",
    "FRA": "Франция",
    "SEN": "Сенегал",
    "IRQ": "Ирак",
    "NOR": "Норвегия",
    "ARG": "Аргентина",
    "ALG": "Алжир",
    "AUT": "Австрия",
    "JOR": "Иордания",
    "GHA": "Гана",
    "PAN": "Панама",
    "ENG": "Англия",
    "CRO": "Хорватия",
    "POR": "Португалия",
    "COD": "ДР Конго",
    "UZB": "Узбекистан",
    "COL": "Колумбия",
}


def stage_for_match(number):
    if number <= 72:
        return "Групповой этап"
    if number <= 88:
        return "1/16 финала"
    if number <= 96:
        return "1/8 финала"
    if number <= 100:
        return "Четвертьфинал"
    if number <= 102:
        return "Полуфинал"
    if number == 103:
        return "Матч за 3 место"
    return "Финал"


def team_name(value):
    value = value.strip()
    if value in TEAM_NAMES:
        return TEAM_NAMES[value]
    if value.startswith("W") and value[1:].isdigit():
        return "Победитель матча " + value[1:]
    if value.startswith("1") and len(value) == 2:
        return "1-е место группы " + value[1]
    if value.startswith("2") and len(value) == 2:
        return "2-е место группы " + value[1]
    if value.startswith("3 "):
        return "3-е место групп " + value[2:]
    if value == "Loser Match 101":
        return "Проигравший матча 101"
    if value == "Loser Match 102":
        return "Проигравший матча 102"
    if value == "Winner Match 101":
        return "Победитель матча 101"
    if value == "Winner Match 102":
        return "Победитель матча 102"
    return value


def clean_tokens(tokens):
    while tokens and re.match(r"^\d{1,3}$|^\d{2}:\d{2}$", tokens[-1]):
        tokens.pop()
    if len(tokens) >= 4 and re.match(r"^[A-L]$", tokens[-1]):
        tokens.pop()
    while tokens and re.match(r"^\d{1,3}$|^\d{2}:\d{2}$", tokens[-1]):
        tokens.pop()
    return tokens


doc = fitz.open(str(PDF_PATH))
page = doc[0]
words = page.get_text("words")

date_headers = []
for block in page.get_text("blocks"):
    x0, y0, x1, y1, text, *_ = block
    normalized = " ".join(text.strip().split())
    match = re.match(
        r"^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday) (\d{1,2}) (June|July)$",
        normalized,
    )
    if not match:
        continue

    month = 6 if match.group(3) == "June" else 7
    date_headers.append(
        ((x0 + x1) / 2, f"2026-{month:02d}-{int(match.group(2)):02d}")
    )


def nearest_date(center_x):
    return min(date_headers, key=lambda item: abs(item[0] - center_x))[1]


matches = {}
for word in words:
    x0, y0, x1, y1, text, *_ = word
    if not text.isdigit():
        continue

    number = int(text)
    if not 1 <= number <= 104:
        continue

    time_words = [
        candidate
        for candidate in words
        if re.match(r"^\d{2}:\d{2}$", candidate[4])
        and abs(candidate[1] - y0) < 1.2
        and 0 < candidate[0] - x1 < 18
    ]
    if not time_words:
        continue

    time_word = min(time_words, key=lambda item: item[0])
    center_x = (x0 + time_word[2]) / 2
    date = nearest_date(center_x)
    time = time_word[4]

    tokens = []
    for other_word in words:
        wx0, wy0, wx1, wy1, wtext, *_ = other_word
        word_center = (wx0 + wx1) / 2
        if y0 + 4 < wy0 < y0 + 38 and abs(word_center - center_x) < 10:
            tokens.append((wy0, wx0, wtext))

    tokens = clean_tokens([token for _, __, token in sorted(tokens)])
    if "v" not in tokens:
        continue

    separator_index = tokens.index("v")
    home = " ".join(tokens[:separator_index])
    away = " ".join(tokens[separator_index + 1 :])
    if home and away:
        matches[number] = {
            "date": date,
            "time": time,
            "home": home,
            "away": away,
        }

# The poster renders these two without "team v team"; add them from the same official PDF.
matches[103] = {
    "date": "2026-07-18",
    "time": "17:00",
    "home": "Loser Match 101",
    "away": "Loser Match 102",
}
matches[104] = {
    "date": "2026-07-19",
    "time": "15:00",
    "home": "Winner Match 101",
    "away": "Winner Match 102",
}

missing = [number for number in range(1, 105) if number not in matches]
if missing:
    raise RuntimeError(f"Missing matches: {missing}")

with OUT_PATH.open("w", encoding="utf-8-sig", newline="") as file:
    writer = csv.writer(file, delimiter=";")
    writer.writerow(["Стадия", "Команда 1", "Команда 2", "Дата и время"])

    for number in range(1, 105):
        match = matches[number]
        eastern_time = datetime.strptime(
            f"{match['date']} {match['time']}",
            "%Y-%m-%d %H:%M",
        ).replace(tzinfo=ZoneInfo("America/New_York"))
        moscow_time = eastern_time.astimezone(ZoneInfo("Europe/Moscow"))

        writer.writerow(
            [
                f"{stage_for_match(number)} - матч {number}",
                team_name(match["home"]),
                team_name(match["away"]),
                moscow_time.strftime("%Y-%m-%d %H:%M"),
            ]
        )

print(f"Created {OUT_PATH} with {len(matches)} matches")
