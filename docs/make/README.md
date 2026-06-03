# Make.com: черновик → канал @chpwc2026

Blueprint без AI-модулей: вы вставляете свой кастомный модуль (Gemini, HTTP, и т.д.) между Router и публикацией в канал.

## Файл

`chp2026-tg-channel.blueprint.json`

## Схема (6 модулей)

```
[1] Telegram Bot → Watch Updates
[2] Router
    ├─ NEWS      → [3] Send Message (@chpwc2026)
    ├─ RESULT    → [4] Send Message (@chpwc2026)
    ├─ REMINDER  → [5] Send Message (@chpwc2026)
    └─ Default   → [6] Send Message (@chpwc2026)
```

После импорта в каждой ветке Router **вставьте свой модуль** между Router и **Send a Text Message**, затем в поле **Text** у Send Message укажите выход кастомного модуля (сейчас там заглушка `{{1.message.text}}` — сырой черновик).

## Импорт

1. [Make.com](https://www.make.com/) → **Scenarios** → **Create a new scenario**
2. Внизу **⋯** → **Import Blueprint**
3. Выберите `chp2026-tg-channel.blueprint.json`
4. На модулях **Watch Updates** и **Send Message** → **Create a connection** → токен бота из [@BotFather](https://t.me/BotFather)  
   **Токен в репозиторий не кладём.** Если токен когда-либо светился в чате — сделайте **Revoke** и используйте новый только в Make.
5. Убедитесь, что `@openclawpasha_bot` — **админ** канала [@chpwc2026](https://t.me/chpwc2026) с правом **Post messages**
6. Scheduling: **Immediately**
7. **Run once** — тест боту в личку

## Router (префиксы в первой строке)

| Ветка | Условие |
|-------|---------|
| NEWS | `NEWS` / `news` / `НОВОСТЬ` |
| RESULT | `RESULT` / `result` / `РЕЗУЛЬТАТ` |
| REMINDER | `REMINDER` / `reminder` / `НАПОМИНАНИЕ` |
| Default | всё остальное |

## Примеры черновиков боту

**Новость:**
```
NEWS
Завтра стартует групповой этап. Прогнозы на сайте.
```

**Результат:**
```
RESULT
Мексика — ЮАР
Группа A
2:1
```

**Напоминание:**
```
REMINDER
Через 2 часа: Республика Корея — Чехия. Успейте сохранить прогноз.
```

## Куда постим

- Канал: **@chpwc2026**
- CTA в постах (добавьте в промпт своего AI-модуля): `https://wc2026.gymacro.ru`

## Send Message (все 4 ветки)

| Поле | Значение |
|------|----------|
| Chat ID | `@chpwc2026` |
| Parse mode | HTML |
| Text | после вставки кастомного модуля — его выход |

## Безопасность

Без фильтра по user id: **любой**, кто напишет боту, может опубликовать пост в канал. Не светите username бота публично или добавьте секретный префикс в Router.

## Если импорт ругается на модули

Make иногда меняет внутренние имена модулей. Если blueprint не подхватил **Watch Updates** / **Send a Text Message**, соберите те же 6 модулей вручную по схеме выше — логика та же.
