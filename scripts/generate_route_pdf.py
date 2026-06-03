#!/usr/bin/env python3
"""Premium family road-trip itinerary PDF — Aug 2026."""

from __future__ import annotations

import os
from datetime import date

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import cm, mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    KeepTogether,
    NextPageTemplate,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)

FONT_DIR = r"C:\Windows\Fonts"
ROOT = os.path.dirname(os.path.dirname(__file__))
OUTPUT = os.path.join(ROOT, "docs", "Маршрут_Екатеринбург_СПб_август_2026.pdf")

# --- palette ---
INK = colors.HexColor("#1A2332")
BLUE = colors.HexColor("#2563EB")
BLUE_D = colors.HexColor("#1E3A8A")
BLUE_L = colors.HexColor("#EFF6FF")
SAND = colors.HexColor("#FFFBF5")
GOLD = colors.HexColor("#B45309")
MUTED = colors.HexColor("#64748B")
LINE = colors.HexColor("#E2E8F0")
WHITE = colors.white

PAGE_W, PAGE_H = A4
MARGIN_L = 50
MARGIN_R = 50
MARGIN_T = 58
MARGIN_B = 50
CONTENT_W = PAGE_W - MARGIN_L - MARGIN_R


def col_widths(*parts: float) -> list[float]:
    total = sum(parts)
    return [CONTENT_W * p / total for p in parts]


def register_fonts() -> None:
    pdfmetrics.registerFont(TTFont("Calibri", os.path.join(FONT_DIR, "calibri.ttf")))
    pdfmetrics.registerFont(TTFont("Calibri-Bold", os.path.join(FONT_DIR, "calibrib.ttf")))
    pdfmetrics.registerFont(TTFont("Calibri-Italic", os.path.join(FONT_DIR, "calibrii.ttf")))
    pdfmetrics.registerFont(TTFont("Georgia", os.path.join(FONT_DIR, "georgia.ttf")))
    pdfmetrics.registerFont(TTFont("Georgia-Bold", os.path.join(FONT_DIR, "georgiab.ttf")))


class Styles:
    def __init__(self) -> None:
        self.display = ParagraphStyle(
            "Display", fontName="Georgia-Bold", fontSize=28, leading=34,
            textColor=WHITE, alignment=TA_CENTER, spaceAfter=6,
        )
        self.display_sub = ParagraphStyle(
            "DisplaySub", fontName="Calibri", fontSize=13, leading=18,
            textColor=colors.HexColor("#DBEAFE"), alignment=TA_CENTER,
        )
        self.h1 = ParagraphStyle(
            "H1", fontName="Georgia-Bold", fontSize=20, leading=26,
            textColor=INK, spaceBefore=4, spaceAfter=10,
        )
        self.h2 = ParagraphStyle(
            "H2", fontName="Calibri-Bold", fontSize=12.5, leading=16,
            textColor=BLUE_D, spaceBefore=14, spaceAfter=6,
        )
        self.h3 = ParagraphStyle(
            "H3", fontName="Calibri-Bold", fontSize=10.5, leading=14,
            textColor=INK, spaceBefore=8, spaceAfter=4,
        )
        self.body = ParagraphStyle(
            "Body", fontName="Calibri", fontSize=10, leading=14.5,
            textColor=INK, alignment=TA_JUSTIFY, spaceAfter=5,
        )
        self.body_left = ParagraphStyle(
            "BodyLeft", parent=self.body, alignment=TA_LEFT,
        )
        self.small = ParagraphStyle(
            "Small", fontName="Calibri", fontSize=8.5, leading=11,
            textColor=MUTED, spaceAfter=3,
        )
        self.bullet = ParagraphStyle(
            "Bullet", fontName="Calibri", fontSize=9.5, leading=13.5,
            textColor=INK, leftIndent=14, bulletIndent=0, spaceAfter=2,
        )
        self.tbl_h = ParagraphStyle(
            "TblH", fontName="Calibri-Bold", fontSize=9, leading=12, textColor=WHITE,
        )
        self.tbl_c = ParagraphStyle(
            "TblC", fontName="Calibri", fontSize=9, leading=12.5, textColor=INK,
        )
        self.tbl_c_r = ParagraphStyle(
            "TblCR", parent=self.tbl_c, alignment=TA_RIGHT,
        )
        self.day_num = ParagraphStyle(
            "DayNum", fontName="Georgia-Bold", fontSize=22, leading=24,
            textColor=WHITE, alignment=TA_CENTER,
        )
        self.day_title = ParagraphStyle(
            "DayTitle", fontName="Calibri-Bold", fontSize=13, leading=17, textColor=WHITE,
        )
        self.day_route = ParagraphStyle(
            "DayRoute", fontName="Calibri", fontSize=9.5, leading=13,
            textColor=colors.HexColor("#BFDBFE"), alignment=TA_LEFT, spaceBefore=2,
        )
        self.stat_label = ParagraphStyle(
            "StatLabel", fontName="Calibri-Bold", fontSize=8, leading=10,
            textColor=MUTED, spaceAfter=1,
        )
        self.stat_value = ParagraphStyle(
            "StatValue", fontName="Calibri-Bold", fontSize=9.5, leading=12, textColor=INK,
        )
        self.chip = ParagraphStyle(
            "Chip", fontName="Calibri-Bold", fontSize=8.5, leading=11,
            textColor=BLUE_D, alignment=TA_CENTER,
        )


S = Styles()


def fix_text(text: str) -> str:
    return (
        text.replace("\u2011", "-")
        .replace("\u2013", "–")
        .replace("\u2014", "—")
        .replace("‑", "-")
        .replace("‑", "-")
    )


def P(text: str, style: ParagraphStyle) -> Paragraph:
    return Paragraph(fix_text(text).replace("\n", "<br/>"), style)


def bullets(items: list[str]) -> list:
    return [P(f"<bullet>&bull;</bullet>&nbsp;{item}", S.bullet) for item in items]


def tbl(data: list[list], widths: list[float], header_rows: int = 1) -> Table:
    rows: list[list] = []
    for r, row in enumerate(data):
        wrapped = []
        for cell in row:
            if isinstance(cell, Paragraph):
                wrapped.append(cell)
            elif r < header_rows:
                wrapped.append(P(str(cell), S.tbl_h))
            else:
                wrapped.append(P(str(cell), S.tbl_c))
        rows.append(wrapped)

    t = Table(rows, colWidths=widths, repeatRows=header_rows, splitByRow=1)
    cmds = [
        ("BACKGROUND", (0, 0), (-1, header_rows - 1), BLUE_D),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
        ("LEFTPADDING", (0, 0), (-1, -1), 9),
        ("RIGHTPADDING", (0, 0), (-1, -1), 9),
        ("LINEBELOW", (0, header_rows - 1), (-1, header_rows - 1), 1, BLUE),
    ]
    for i in range(header_rows, len(rows)):
        bg = BLUE_L if i % 2 == 0 else WHITE
        cmds.append(("BACKGROUND", (0, i), (-1, i), bg))
        cmds.append(("LINEBELOW", (0, i), (-1, i), 0.4, LINE))
    t.setStyle(TableStyle(cmds))
    return t


def callout(title: str, lines: list[str]) -> Table:
    content = [[P(f"<font color='#1E3A8A'><b>{title}</b></font>", S.body_left)]]
    for line in lines:
        content.append([P(line, S.body_left)])
    box = Table(content, colWidths=[CONTENT_W])
    box.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), BLUE_L),
        ("BOX", (0, 0), (-1, -1), 0, WHITE),
        ("LINEBEFORE", (0, 0), (0, -1), 3, BLUE),
        ("TOPPADDING", (0, 0), (-1, -1), 10),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
        ("LEFTPADDING", (0, 0), (-1, -1), 14),
        ("RIGHTPADDING", (0, 0), (-1, -1), 12),
    ]))
    return box


def section_title(text: str) -> list:
    bar = Table([[P(text, S.h1)]], colWidths=[CONTENT_W])
    bar.setStyle(TableStyle([
        ("LINEBELOW", (0, 0), (-1, -1), 2, BLUE),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ]))
    return [bar, Spacer(1, 6)]


def day_block(
    num: str,
    when: str,
    route: str,
    stats: list[tuple[str, str]],
    paragraphs: list[str],
    sections: list[tuple[str, list[str]]] | None = None,
) -> list:
    """Day card: header + stats grid + body in one flowable block."""
    badge_w = 40
    text_w = CONTENT_W - badge_w
    header = Table(
        [[
            P(num, S.day_num),
            Table([
                [P(f"День {num} · {when}", S.day_title)],
                [P(route, S.day_route)],
            ], colWidths=[text_w - 16]),
        ]],
        colWidths=[badge_w, text_w],
    )
    header.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), BLUE_D),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 10),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
        ("LEFTPADDING", (0, 0), (0, 0), 8),
        ("LEFTPADDING", (1, 0), (1, 0), 0),
        ("RIGHTPADDING", (1, 0), (1, 0), 12),
        ("ALIGN", (0, 0), (0, 0), "CENTER"),
        ("VALIGN", (1, 0), (1, 0), "MIDDLE"),
    ]))

    stat_w = CONTENT_W / 2
    stat_rows = []
    for i in range(0, len(stats), 2):
        row = []
        for label, value in stats[i:i + 2]:
            cell = Table([
                [P(label.upper(), S.stat_label)],
                [P(value, S.stat_value)],
            ], colWidths=[stat_w - 12])
            cell.setStyle(TableStyle([
                ("TOPPADDING", (0, 0), (-1, -1), 0),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
            ]))
            row.append(cell)
        if len(row) == 1:
            row.append("")
        stat_rows.append(row)

    stats_table = Table(stat_rows, colWidths=[stat_w, stat_w])
    stats_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), SAND),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("TOPPADDING", (0, 0), (-1, -1), 8),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
        ("LEFTPADDING", (0, 0), (-1, -1), 10),
        ("INNERGRID", (0, 0), (-1, -1), 0.5, LINE),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))

    body_rows: list[list] = []
    for para in paragraphs:
        body_rows.append([P(para, S.body_left)])
    for title, items in (sections or []):
        body_rows.append([P(title, S.h3)])
        for item in items:
            body_rows.append([P(f"<bullet>&bull;</bullet>&nbsp;{item}", S.bullet)])

    body = Table(body_rows, colWidths=[CONTENT_W])
    body.setStyle(TableStyle([
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ("LEFTPADDING", (0, 0), (-1, -1), 14),
        ("RIGHTPADDING", (0, 0), (-1, -1), 14),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("LINEBELOW", (0, 0), (0, 0), 0, LINE),
    ]))

    top = Table([[header], [stats_table]], colWidths=[CONTENT_W])
    top.setStyle(TableStyle([
        ("TOPPADDING", (0, 0), (-1, -1), 0),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 0),
    ]))
    return [KeepTogether([top]), body, Spacer(1, 10)]


def draw_cover(canvas, _doc) -> None:
    canvas.saveState()
    canvas.setFillColor(BLUE_D)
    canvas.rect(0, PAGE_H * 0.42, PAGE_W, PAGE_H * 0.58, fill=1, stroke=0)
    canvas.setFillColor(BLUE)
    canvas.rect(0, PAGE_H * 0.40, PAGE_W, 8, fill=1, stroke=0)

    canvas.setFillColor(WHITE)
    canvas.setFont("Georgia-Bold", 24)
    y = PAGE_H - 92 * mm
    for line in [
        "Екатеринбург — Казань — Москва",
        "Санкт-Петербург — и обратно домой",
    ]:
        canvas.drawCentredString(PAGE_W / 2, y, line)
        y -= 30

    canvas.setFont("Calibri", 12)
    canvas.setFillColor(colors.HexColor("#BFDBFE"))
    canvas.drawCentredString(PAGE_W / 2, y - 6, "Семейное автопутешествие  |  8–16 августа 2026")
    canvas.setFont("Calibri", 10.5)
    canvas.drawCentredString(PAGE_W / 2, y - 22, "М-12 «Восток»  |  М-11 «Нева»  |  спокойный семейный маршрут")

    # route chips
    cities = ["Екб", "Казань", "Москва", "СПб", "Новгород", "Н. Новгород", "Елабуга", "Екб"]
    chip_y = PAGE_H * 0.46
    total_w = PAGE_W - 80
    step = total_w / (len(cities) - 1)
    x0 = 40
    canvas.setStrokeColor(colors.HexColor("#93C5FD"))
    canvas.setLineWidth(1.5)
    canvas.line(x0, chip_y + 5, x0 + total_w, chip_y + 5)
    for i, city in enumerate(cities):
        x = x0 + i * step
        canvas.setFillColor(WHITE)
        canvas.circle(x, chip_y + 5, 4, fill=1, stroke=0)
        canvas.setFillColor(BLUE_D)
        canvas.setFont("Calibri-Bold", 7.5)
        canvas.drawCentredString(x, chip_y - 10, city)

    # facts grid (bottom)
    facts = [
        ("4", "человека"),
        ("8", "ночёвок"),
        ("9", "дней"),
        ("~4800", "км"),
    ]
    fx = 55
    fw = (PAGE_W - 110) / 4
    for i, (num, label) in enumerate(facts):
        x = fx + i * fw
        canvas.setFillColor(BLUE_L)
        canvas.roundRect(x, 55 * mm, fw - 12, 28 * mm, 6, fill=1, stroke=0)
        canvas.setFillColor(BLUE_D)
        canvas.setFont("Georgia-Bold", 22)
        canvas.drawCentredString(x + (fw - 12) / 2, 72 * mm, num)
        canvas.setFont("Calibri", 9)
        canvas.setFillColor(MUTED)
        canvas.drawCentredString(x + (fw - 12) / 2, 64 * mm, label)

    canvas.setFont("Calibri-Italic", 8.5)
    canvas.setFillColor(MUTED)
    canvas.drawCentredString(
        PAGE_W / 2, 22 * mm,
        "Тарифы платных трасс уточняйте в калькуляторе Автодора перед выездом",
    )
    canvas.restoreState()


def draw_content_page(canvas, doc) -> None:
    canvas.saveState()
    canvas.setStrokeColor(LINE)
    canvas.setLineWidth(0.6)
    canvas.line(MARGIN_L, PAGE_H - 38, PAGE_W - MARGIN_R, PAGE_H - 38)
    canvas.setFont("Calibri-Bold", 7.5)
    canvas.setFillColor(MUTED)
    canvas.drawString(MARGIN_L, PAGE_H - 32, "МАРШРУТ · АВГУСТ 2026")
    canvas.drawRightString(PAGE_W - MARGIN_R, 28, f"стр. {doc.page}")
    canvas.restoreState()


def build() -> None:
    register_fonts()
    os.makedirs(os.path.dirname(OUTPUT), exist_ok=True)

    doc = BaseDocTemplate(
        OUTPUT,
        pagesize=A4,
        title="Маршрут Екатеринбург — СПб — август 2026",
        author="CHP2026",
    )
    frame = Frame(
        MARGIN_L, MARGIN_B,
        CONTENT_W, PAGE_H - MARGIN_T - MARGIN_B,
        id="main", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0,
    )
    cover_frame = Frame(0, 0, PAGE_W, PAGE_H, id="cover", leftPadding=0, rightPadding=0)
    doc.addPageTemplates([
        PageTemplate(id="Cover", frames=[cover_frame], onPage=draw_cover),
        PageTemplate(id="Content", frames=[frame], onPage=draw_content_page),
    ])

    story: list = []
    w = CONTENT_W
    cw2 = col_widths(32, 68)
    cw3 = col_widths(1, 1, 1)
    cw4 = col_widths(1, 1, 1, 1)

    # --- cover (drawn on canvas) then switch to content template ---
    story.append(NextPageTemplate("Content"))
    story.append(PageBreak())

    # --- overview ---
    story.extend(section_title("Обзор маршрута"))
    story.append(P(
        "Спокойный семейный маршрут с упором на хорошие дороги и платные трассы: "
        "М‑12 «Восток» (Екатеринбург–Казань–Москва) и М‑11 «Нева» (Москва–Санкт‑Петербург). "
        "Обратно — с остановками в Великом Новгороде и Нижнем Новгороде, без гонки "
        "«СПб → Москва → Казань» одним темпом.",
        S.body,
    ))
    story.append(Spacer(1, 10))
    story.append(callout("М‑12 «Восток»", [
        "Платная скоростная трасса, система «Свободный поток», без шлагбаумов.",
        "Москва–Казань для легкового авто в 2026 г.: ориентировочно <b>5 250–5 850 ₽</b>. "
        "Точную сумму проверьте в калькуляторе Автодора.",
    ]))
    story.append(Spacer(1, 12))
    story.append(P("Схема маршрута", S.h2))
    story.append(tbl([
        ["Направление", "Маршрут"],
        ["Туда", "Екатеринбург → Казань → Москва → Санкт‑Петербург"],
        ["Обратно", "СПб → Великий Новгород → Нижний Новгород → Елабуга / Наб. Челны → Екатеринбург"],
    ], cw2))
    story.append(Spacer(1, 12))
    story.append(P("Календарь ночёвок", S.h2))
    story.append(tbl([
        ["Дата", "Где", "Дата", "Где"],
        ["8 авг.", "Казань", "13 авг.", "Новгород"],
        ["9 авг.", "Москва", "14 авг.", "Н. Новгород"],
        ["10–12 авг.", "Санкт‑Петербург", "15 авг.", "Елабуга / Челны"],
        ["", "", "16 авг.", "Екатеринбург"],
    ], cw4))
    story.append(Spacer(1, 8))
    story.append(P("<b>Итого: 8 ночей вне дома.</b>", S.body))
    story.append(PageBreak())
    story.extend(section_title("План по дням"))

    story.extend(day_block(
        "1", "8 августа", "Екатеринбург → Казань",
        [("Расстояние", "900–950 км"), ("Время", "10–12 ч"), ("Дороги", "М‑12"), ("Ночёвка", "Казань")],
        ["Самый длинный первый перегон — логичный старт: сразу проходите восточный участок "
         "и попадаете в город с хорошим выбором апарт‑отелей."],
        [
            ("Вечером в Казани", ["Кремль", "набережная Казанки", "улица Баумана", "семейный ужин"]),
            ("Жильё (4 чел.)", [
                "Центр / Баумана / Кремль — удобно гулять",
                "Суконная слобода — дешевле, удобный выезд",
                "Апартаменты 2 комн.: 6 000–10 000 ₽/ночь",
                "Апарт‑отель с парковкой: 8 000–13 000 ₽/ночь",
            ]),
        ],
    ))

    story.extend(day_block(
        "2", "9 августа", "Казань → Москва",
        [("Расстояние", "800–850 км"), ("Время", "8–10 ч"), ("Дорога", "М‑12 «Восток»"), ("Ночёвка", "Москва")],
        ["Один из лучших перегонов: платная скоростная трасса без населённых пунктов и светофоров."],
        [("Жильё в Москве", [
            "Ходынка / Динамо / Аэропорт — выезд на М‑11",
            "Тушино / Строгино / Митино — выезд на СПб",
            "Апартаменты: 8 000–15 000 ₽; 4* апарт‑отель: 12 000–20 000 ₽",
            "Парковка: 500–1 500 ₽/сутки отдельно",
        ])],
    ))

    story.extend(day_block(
        "3", "10 августа", "Москва → Санкт‑Петербург",
        [("Расстояние", "680–720 км"), ("Время", "7–9 ч"), ("Дорога", "М‑11 «Нева»"), ("Ночёвка", "СПб (1/3)")],
        ["М‑11 — платная, быстрая, предсказуемая. Тарифы зависят от участка, времени и транспондера."],
        [("Жильё в СПб", [
            "Московский р‑н / Пулковская — удобно на машине, проще парковка",
            "Петроградская — красиво, парковка сложнее",
            "Апартаменты: 9 000–18 000 ₽; ближе к центру: 14 000–25 000 ₽",
        ])],
    ))

    story.append(Spacer(1, 6))
    story.extend(section_title("Санкт‑Петербург · 3 ночи"))
    story.append(P("10, 11, 12 августа — программа без перегруза:", S.body))
    story.append(Spacer(1, 6))
    story.append(tbl([
        ["День", "Программа"],
        ["1", "Дворцовая площадь · Невский · Исаакиевский собор · набережные · развод мостов"],
        ["2", "Петергоф или Царское Село · вечером — Новая Голландия / Севкабель"],
        ["3", "Эрмитаж или Русский музей · Петропавловская крепость · кораблик по каналам"],
    ], col_widths(22, 78)))
    story.append(Spacer(1, 14))
    story.extend(section_title("Обратный маршрут"))
    story.append(P(
        "Не возвращайтесь схемой «СПб → Москва → Казань → Екатеринбург» без остановок. "
        "Лучше сохранить хорошие дороги и добавить приятные города.",
        S.body,
    ))
    story.append(Spacer(1, 8))

    story.extend(day_block(
        "6", "13 августа", "СПб → Великий Новгород",
        [("Расстояние", "190–220 км"), ("Время", "2,5–3,5 ч"), ("Дорога", "М‑11"), ("Ночёвка", "Новгород")],
        ["Комфортная короткая остановка. Красивый спокойный исторический центр."],
        [
            ("Посмотреть", ["Кремль", "Софийский собор", "Ярославово дворище", "набережная Волхова"]),
            ("Жильё", ["Центр у Кремля", "4 чел.: 5 000–9 000 ₽/ночь"]),
        ],
    ))

    story.extend(day_block(
        "7", "14 августа", "Новгород → Нижний Новгород",
        [("Расстояние", "750–850 км"), ("Время", "9–11 ч"), ("Дороги", "М‑11 · ЦКАД · М‑12"), ("Ночёвка", "Н. Новгород")],
        ["Длинный, но логичный перегон. Отличный город для вечерней прогулки."],
        [
            ("Вечером", ["Кремль", "Чкаловская лестница", "Верхневолжская набережная", "Стрелка"]),
            ("Жильё", ["Центр / Покровская", "Апартаменты: 6 000–11 000 ₽/ночь"]),
        ],
    ))

    story.extend(day_block(
        "8", "15 августа", "Н. Новгород → Елабуга / Челны",
        [("Расстояние", "550–650 км"), ("Время", "7–9 ч"), ("Дорога", "М‑12"), ("Ночёвка", "Елабуга / Челны")],
        ["Елабуга — красивый город. Наб. Челны — практичнее по логистике."],
        [
            ("Елабуга", ["Исторический центр", "музей Шишкина", "Чёртово городище"]),
            ("Наб. Челны", ["Больше жилья и инфраструктуры", "4 500–11 000 ₽/ночь"]),
        ],
    ))

    story.extend(day_block(
        "9", "16 августа", "Елабуга / Челны → Екатеринбург",
        [("Расстояние", "650–750 км"), ("Время", "8–10 ч"), ("Дорога", "М‑12"), ("Финиш", "Екатеринбург")],
        ["Финальный перегон домой по хорошим участкам М‑12."],
    ))
    story.append(PageBreak())

    # --- overnight table ---
    story.extend(section_title("Ночёвки"))
    story.append(tbl([
        ["Дата", "Город", "Комментарий"],
        ["8 авг.", "Казань", "1 ночь"],
        ["9 авг.", "Москва", "1 ночь"],
        ["10–12 авг.", "Санкт‑Петербург", "3 ночи"],
        ["13 авг.", "Великий Новгород", "1 ночь"],
        ["14 авг.", "Нижний Новгород", "1 ночь"],
        ["15 авг.", "Елабуга / Челны", "1 ночь"],
        ["16 авг.", "Екатеринбург", "домой"],
    ], col_widths(18, 28, 54)))
    story.append(Spacer(1, 6))
    story.append(P("<b>Итого: 8 ночей вне дома.</b>", S.body))
    story.append(Spacer(1, 10))
    story.append(P("При бронировании фильтруйте:", S.h3))
    story.extend(bullets([
        "4 гостя · парковка · кондиционер · кухня",
        "стиральная машина (особенно в СПб) · рейтинг 8,5+",
        "бесконтактное заселение при позднем заезде",
    ]))
    story.append(PageBreak())

    # --- budget ---
    story.extend(section_title("Смета поездки"))
    story.append(P("Для семьи из 4 человек на легковом автомобиле (ориентиры).", S.body))
    story.append(Spacer(1, 8))

    story.append(P("Проживание", S.h2))
    story.append(tbl([
        ["Город", "Ночей", "За ночь", "Сумма"],
        ["Казань", "1", "7–11 тыс.", "7–11 тыс."],
        ["Москва", "1", "10–18 тыс.", "10–18 тыс."],
        ["Санкт‑Петербург", "3", "10–20 тыс.", "30–60 тыс."],
        ["Новгород", "1", "5–10 тыс.", "5–10 тыс."],
        ["Н. Новгород", "1", "7–13 тыс.", "7–13 тыс."],
        ["Елабуга / Челны", "1", "5–9 тыс.", "5–9 тыс."],
        ["Итого", "", "", "64–121 тыс. ₽"],
    ], col_widths(28, 14, 22, 36)))
    story.append(P("Комфортный бюджет: <b>85 000–100 000 ₽</b>", S.body))
    story.append(Spacer(1, 8))

    story.append(P("Платные дороги", S.h2))
    story.append(tbl([
        ["Участок", "Стоимость"],
        ["Екб → Казань (М‑12)", "3 500–5 000 ₽"],
        ["Казань → Москва (М‑12)", "5 250–5 850 ₽"],
        ["Москва → СПб (М‑11)", "3 500–6 500 ₽"],
        ["СПб → Новгород + далее", "1 000–2 500 ₽"],
        ["ЦКАД / М‑12 → Н. Новгород", "2 000–4 000 ₽"],
        ["Н. Новгород → Екб", "4 000–6 000 ₽"],
        ["Итого", "19 000–30 000 ₽"],
    ], col_widths(58, 42)))
    story.append(P("Заложите: <b>25 000–30 000 ₽</b>", S.body))
    story.append(Spacer(1, 8))

    story.append(P("Топливо (~4 600–5 000 км)", S.h2))
    story.append(tbl([
        ["Расход", "Литры", "При 65 ₽/л"],
        ["8 л/100", "368–400 л", "24–26 тыс. ₽"],
        ["10 л/100", "460–500 л", "30–33 тыс. ₽"],
        ["12 л/100", "552–600 л", "36–39 тыс. ₽"],
    ], cw3))
    story.append(Spacer(1, 8))

    story.append(P("Питание (9 дней)", S.h2))
    story.append(tbl([
        ["Формат", "В день", "Итого"],
        ["Экономно", "4–5,5 тыс.", "36–50 тыс. ₽"],
        ["Комфортно", "6–8 тыс.", "54–72 тыс. ₽"],
        ["С ресторанами", "9–12 тыс.", "81–108 тыс. ₽"],
    ], cw3))
    story.append(Spacer(1, 8))

    story.append(P("Прочее", S.h2))
    story.append(tbl([
        ["Статья", "Сумма"],
        ["Парковки Мск + СПб", "5–12 тыс. ₽"],
        ["Музеи / экскурсии", "15–35 тыс. ₽"],
        ["Мелочи", "5–10 тыс. ₽"],
    ], col_widths(58, 42)))
    story.append(Spacer(1, 10))

    story.append(P("Итого", S.h2))
    story.append(tbl([
        ["Категория", "Минимум", "Комфорт"],
        ["Проживание", "64 тыс.", "90–110 тыс."],
        ["Дороги", "19 тыс.", "25–30 тыс."],
        ["Топливо", "30 тыс.", "35–38 тыс."],
        ["Питание", "45–55 тыс.", "60–75 тыс."],
        ["Прочее", "25 тыс.", "35–55 тыс."],
    ], cw3))

    total = Table([[
        P("<b>Бюджетно‑комфортно:</b> 190 000–220 000 ₽", S.body_left),
        P("<b>Комфортно:</b> 240 000–300 000 ₽", S.body_left),
        P("<i>Центр СПб/Москва — до 320 000 ₽+</i>", S.small),
    ]], colWidths=[w])
    total.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), SAND),
        ("LINEBEFORE", (0, 0), (0, -1), 3, GOLD),
        ("TOPPADDING", (0, 0), (-1, -1), 12),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 12),
        ("LEFTPADDING", (0, 0), (-1, -1), 14),
    ]))
    story.append(Spacer(1, 10))
    story.append(total)
    story.append(PageBreak())

    # --- tips ---
    story.extend(section_title("Перед поездкой"))
    tips = [
        ("Транспондер T‑Pass", "На М‑12 — «Свободный поток». Без транспондера оплатите проезд в течение 5 дней."),
        ("Ранний выезд", "Из Екатеринбурга в 5:00–6:00 — не опаздывайте с заездом в Казань и Москву."),
        ("Парковка в СПб", "Жильё с парковкой обязательно — центр может испортить впечатление."),
        ("Заправки на М‑12", "Держите запас минимум 1/3 бака между точками."),
        ("Обратный путь", "Новгород + Н. Новгород — лучший баланс для семьи, чем гонка через Москву."),
    ]
    for title, text in tips:
        story.append(callout(title, [text]))
        story.append(Spacer(1, 6))

    story.append(Spacer(1, 20))
    story.append(P(
        f"Сформировано {date.today().strftime('%d.%m.%Y')}. Цены и тарифы ориентировочные.",
        ParagraphStyle("Foot", parent=S.small, alignment=TA_CENTER),
    ))

    doc.build(story)
    print(OUTPUT)


if __name__ == "__main__":
    build()
