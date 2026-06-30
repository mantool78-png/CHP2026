#!/usr/bin/env python3
"""Rebuild fifa_2026_schedule_moscow.csv from official local kickoffs + venue timezones."""

from __future__ import annotations

import csv
import re
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

ROOT = Path(__file__).resolve().parents[1]
OUT_PATH = ROOT / "database" / "fifa_2026_schedule_moscow.csv"
OLD_PATH = OUT_PATH

# Match, calendar date (local), local kickoff, host city — Roadtrips / FIFA schedule.
SCHEDULE: list[tuple[int, str, str, str]] = [
    (1, "2026-06-11", "13:00", "Mexico City"),
    (2, "2026-06-11", "20:00", "Guadalajara"),
    (3, "2026-06-12", "15:00", "Toronto"),
    (4, "2026-06-12", "18:00", "Los Angeles"),
    (5, "2026-06-13", "21:00", "Boston"),
    (6, "2026-06-13", "21:00", "Vancouver"),
    (7, "2026-06-13", "18:00", "New York/New Jersey"),
    (8, "2026-06-13", "12:00", "San Francisco Bay Area"),
    (9, "2026-06-14", "19:00", "Philadelphia"),
    (10, "2026-06-14", "12:00", "Houston"),
    (11, "2026-06-14", "15:00", "Dallas"),
    (12, "2026-06-14", "20:00", "Monterrey"),
    (13, "2026-06-15", "18:00", "Miami"),
    (14, "2026-06-15", "12:00", "Atlanta"),
    (15, "2026-06-15", "18:00", "Los Angeles"),
    (16, "2026-06-15", "12:00", "Seattle"),
    (17, "2026-06-16", "15:00", "New York/New Jersey"),
    (18, "2026-06-16", "18:00", "Boston"),
    (19, "2026-06-16", "20:00", "Kansas City"),
    (20, "2026-06-16", "21:00", "San Francisco Bay Area"),
    (21, "2026-06-17", "19:00", "Toronto"),
    (22, "2026-06-17", "15:00", "Dallas"),
    (23, "2026-06-17", "12:00", "Houston"),
    (24, "2026-06-17", "20:00", "Mexico City"),
    (25, "2026-06-18", "12:00", "Atlanta"),
    (26, "2026-06-18", "12:00", "Los Angeles"),
    (27, "2026-06-18", "15:00", "Vancouver"),
    (28, "2026-06-18", "19:00", "Guadalajara"),
    (29, "2026-06-19", "21:00", "Philadelphia"),
    (30, "2026-06-19", "18:00", "Boston"),
    (31, "2026-06-19", "20:00", "San Francisco Bay Area"),
    (32, "2026-06-19", "12:00", "Seattle"),
    (33, "2026-06-20", "16:00", "Toronto"),
    (34, "2026-06-20", "19:00", "Kansas City"),
    (35, "2026-06-20", "12:00", "Houston"),
    (36, "2026-06-20", "22:00", "Monterrey"),
    (37, "2026-06-21", "18:00", "Miami"),
    (38, "2026-06-21", "12:00", "Atlanta"),
    (39, "2026-06-21", "12:00", "Los Angeles"),
    (40, "2026-06-21", "18:00", "Vancouver"),
    (41, "2026-06-22", "20:00", "New York/New Jersey"),
    (42, "2026-06-22", "17:00", "Philadelphia"),
    (43, "2026-06-22", "12:00", "Dallas"),
    (44, "2026-06-22", "20:00", "San Francisco Bay Area"),
    (45, "2026-06-23", "16:00", "Boston"),
    (46, "2026-06-23", "19:00", "Toronto"),
    (47, "2026-06-23", "12:00", "Houston"),
    (48, "2026-06-23", "20:00", "Guadalajara"),
    (49, "2026-06-24", "18:00", "Miami"),
    (50, "2026-06-24", "18:00", "Atlanta"),
    (51, "2026-06-24", "12:00", "Vancouver"),
    (52, "2026-06-24", "12:00", "Seattle"),
    (53, "2026-06-24", "19:00", "Mexico City"),
    (54, "2026-06-24", "19:00", "Monterrey"),
    (55, "2026-06-25", "16:00", "Philadelphia"),
    (56, "2026-06-25", "16:00", "New York/New Jersey"),
    (57, "2026-06-25", "18:00", "Dallas"),
    (58, "2026-06-25", "18:00", "Kansas City"),
    (59, "2026-06-25", "19:00", "Los Angeles"),
    (60, "2026-06-25", "19:00", "San Francisco Bay Area"),
    (61, "2026-06-26", "15:00", "Boston"),
    (62, "2026-06-26", "15:00", "Toronto"),
    (63, "2026-06-26", "20:00", "Seattle"),
    (64, "2026-06-26", "20:00", "Vancouver"),
    (65, "2026-06-26", "19:00", "Houston"),
    (66, "2026-06-26", "18:00", "Guadalajara"),
    (67, "2026-06-27", "17:00", "New York/New Jersey"),
    (68, "2026-06-27", "17:00", "Philadelphia"),
    (69, "2026-06-27", "21:00", "Kansas City"),
    (70, "2026-06-27", "21:00", "Dallas"),
    (71, "2026-06-27", "19:30", "Miami"),
    (72, "2026-06-27", "19:30", "Atlanta"),
    (73, "2026-06-28", "12:00", "Los Angeles"),
    (74, "2026-06-29", "16:30", "Boston"),
    (75, "2026-06-29", "19:00", "Monterrey"),
    (76, "2026-06-29", "12:00", "Houston"),
    (77, "2026-06-30", "17:00", "New York/New Jersey"),
    (78, "2026-06-30", "12:00", "Dallas"),
    (79, "2026-06-30", "19:00", "Mexico City"),
    (80, "2026-07-01", "12:00", "Atlanta"),
    (81, "2026-07-01", "17:00", "San Francisco Bay Area"),
    (82, "2026-07-01", "13:00", "Seattle"),
    (83, "2026-07-02", "19:00", "Toronto"),
    (84, "2026-07-02", "12:00", "Los Angeles"),
    (85, "2026-07-02", "20:00", "Vancouver"),
    (86, "2026-07-03", "18:00", "Miami"),
    (87, "2026-07-03", "20:30", "Kansas City"),
    (88, "2026-07-03", "13:00", "Dallas"),
    (89, "2026-07-04", "17:00", "Philadelphia"),
    (90, "2026-07-04", "12:00", "Houston"),
    (91, "2026-07-05", "16:00", "New York/New Jersey"),
    (92, "2026-07-05", "18:00", "Mexico City"),
    (93, "2026-07-06", "14:00", "Dallas"),
    (94, "2026-07-06", "17:00", "Seattle"),
    (95, "2026-07-07", "12:00", "Atlanta"),
    (96, "2026-07-07", "13:00", "Vancouver"),
    (97, "2026-07-09", "16:00", "Boston"),
    (98, "2026-07-10", "12:00", "Los Angeles"),
    (99, "2026-07-11", "17:00", "Miami"),
    (100, "2026-07-11", "20:00", "Kansas City"),
    (101, "2026-07-14", "14:00", "Dallas"),
    (102, "2026-07-15", "15:00", "Atlanta"),
    (103, "2026-07-18", "17:00", "Miami"),
    (104, "2026-07-19", "15:00", "New York/New Jersey"),
]

CITY_TZ: dict[str, str] = {
    "Mexico City": "America/Mexico_City",
    "Guadalajara": "America/Mexico_City",
    "Monterrey": "America/Mexico_City",
    "Toronto": "America/Toronto",
    "Boston": "America/New_York",
    "New York/New Jersey": "America/New_York",
    "Philadelphia": "America/New_York",
    "Miami": "America/New_York",
    "Atlanta": "America/New_York",
    "Los Angeles": "America/Los_Angeles",
    "San Francisco Bay Area": "America/Los_Angeles",
    "Seattle": "America/Los_Angeles",
    "Vancouver": "America/Vancouver",
    "Dallas": "America/Chicago",
    "Houston": "America/Chicago",
    "Kansas City": "America/Chicago",
}


def stage_for_match(number: int) -> str:
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


def to_moscow(number: int, date: str, local_time: str, city: str) -> str:
    tz = ZoneInfo(CITY_TZ[city])
    dt = datetime.strptime(f"{date} {local_time}", "%Y-%m-%d %H:%M").replace(tzinfo=tz)
    return dt.astimezone(ZoneInfo("Europe/Moscow")).strftime("%Y-%m-%d %H:%M")


def load_old_rows() -> dict[int, list[str]]:
    if not OLD_PATH.is_file():
        return {}
    rows = list(csv.reader(OLD_PATH.open(encoding="utf-8-sig"), delimiter=";"))
    out: dict[int, list[str]] = {}
    for row in rows[1:]:
        m = re.search(r"(\d+)$", row[0])
        if m:
            out[int(m.group(1))] = row
    return out


def main() -> int:
    old = load_old_rows()
    new_rows: list[list[str]] = []
    changes: list[str] = []

    for number, date, local_time, city in SCHEDULE:
        old_row = old.get(number)
        if not old_row:
            raise SystemExit(f"Missing old CSV row for match {number}")
        msk = to_moscow(number, date, local_time, city)
        stage = f"{stage_for_match(number)} - матч {number}"
        new_row = [stage, old_row[1], old_row[2], msk]
        new_rows.append(new_row)
        if old_row[3] != msk:
            changes.append(f"M{number}: {old_row[1]} — {old_row[2]} | {old_row[3]} -> {msk} ({city} {date} {local_time})")

    with OUT_PATH.open("w", encoding="utf-8-sig", newline="") as file:
        writer = csv.writer(file, delimiter=";")
        writer.writerow(["Стадия", "Команда 1", "Команда 2", "Дата и время"])
        writer.writerows(new_rows)

    sql_path = ROOT / "database" / "migrations" / "015_fix_match_schedule_msk.sql"
    sql_lines = ["-- Correct kickoff times (Europe/Moscow) from venue local schedules"]
    for row in new_rows:
        stage, home, away, msk = row
        sql_lines.append(
            "UPDATE matches SET starts_at = "
            f"'{msk}:00', updated_at = NOW() "
            f"WHERE stage = '{stage.replace(chr(39), chr(39)+chr(39))}';"
        )
    sql_path.write_text("\n".join(sql_lines) + "\n", encoding="utf-8")

    print(f"Wrote {OUT_PATH} ({len(new_rows)} matches)")
    print(f"Wrote {sql_path}")
    print(f"Changed: {len(changes)}")
    for line in changes:
        print(line)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
