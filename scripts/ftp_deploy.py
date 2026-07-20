#!/usr/bin/env python3
"""Upload changed project files to Beget via FTP. Credentials: .beget-ftp.env (see .beget-ftp.env.example)."""

from __future__ import annotations

import ftplib
import os
import sys
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


# (local relative path, remote directory from FTP root, remote filename)
PAIRS: list[tuple[str, str, str]] = [
    (".htaccess", "/", ".htaccess"),
    ("app/bootstrap.php", "/app", "bootstrap.php"),
    ("app/mail.php", "/app", "mail.php"),
    ("app/match_reminders.php", "/app", "match_reminders.php"),
    ("app/match_result_notifications.php", "/app", "match_result_notifications.php"),
    ("app/pdf_export.php", "/app", "pdf_export.php"),
    ("app/engagement.php", "/app", "engagement.php"),
    ("app/weekly_digest.php", "/app", "weekly_digest.php"),
    ("app/admin_routes.php", "/app", "admin_routes.php"),
    ("app/domain.php", "/app", "domain.php"),
    ("app/api_football.php", "/app", "api_football.php"),
    ("app/worldcup2026_groups.php", "/app", "worldcup2026_groups.php"),
    ("app/migration_runner_008.php", "/app", "migration_runner_008.php"),
    ("public/index.php", "/public", "index.php"),
    ("public/apply_migration_005.php", "/public", "apply_migration_005.php"),
    ("public/apply_migration_010.php", "/public", "apply_migration_010.php"),
    ("public/apply_migration_011.php", "/public", "apply_migration_011.php"),
    ("public/apply_migration_012.php", "/public", "apply_migration_012.php"),
    ("public/apply_migration_013.php", "/public", "apply_migration_013.php"),
    ("public/apply_migration_014.php", "/public", "apply_migration_014.php"),
    ("public/apply_migration_015.php", "/public", "apply_migration_015.php"),
    ("public/apply_migration_008.php", "/public", "apply_migration_008.php"),
    ("public/cron_match_reminders.php", "/public", "cron_match_reminders.php"),
    ("public/cron_match_result_correction.php", "/public", "cron_match_result_correction.php"),
    ("public/cron_api_football_sync.php", "/public", "cron_api_football_sync.php"),
    ("public/cron_api_football_probe.php", "/public", "cron_api_football_probe.php"),
    ("public/cron_payment_reminders.php", "/public", "cron_payment_reminders.php"),
    ("public/cron_last_free_match_payment.php", "/public", "cron_last_free_match_payment.php"),
    ("public/cron_audit_free_trial.php", "/public", "cron_audit_free_trial.php"),
    ("public/cron_opening_match_reminder.php", "/public", "cron_opening_match_reminder.php"),
    ("public/cron_home_activity.php", "/public", "cron_home_activity.php"),
    ("public/cron_weekly_digest.php", "/public", "cron_weekly_digest.php"),
    ("public/assets/styles.css", "/public/assets", "styles.css"),
    ("public/assets/logo.png", "/public/assets", "logo.png"),
    ("public/assets/favicon.ico", "/public/assets", "favicon.ico"),
    ("public/assets/favicon-16x16.png", "/public/assets", "favicon-16x16.png"),
    ("public/assets/favicon-32x32.png", "/public/assets", "favicon-32x32.png"),
    ("public/assets/apple-touch-icon.png", "/public/assets", "apple-touch-icon.png"),
    ("public/assets/prize-iphone.png", "/public/assets", "prize-iphone.png"),
    ("public/assets/prize-iphone.jpg", "/public/assets", "prize-iphone.jpg"),
    ("public/assets/prize-iphone-hero.png", "/public/assets", "prize-iphone-hero.png"),
    ("public/assets/prize-iphone-hero.jpg", "/public/assets", "prize-iphone-hero.jpg"),
    ("public/assets/hero-duel.jpg", "/public/assets", "hero-duel.jpg"),
    ("public/assets/qr_payment.png", "/public/assets", "qr_payment.png"),
    ("public/assets/mascot-eagle.png", "/public/assets", "mascot-eagle.png"),
    ("public/assets/mascot-jaguar.png", "/public/assets", "mascot-jaguar.png"),
    ("public/assets/mascot-moose.png", "/public/assets", "mascot-moose.png"),
    ("public/assets/max-icon.png", "/public/assets", "max-icon.png"),
    ("views/home.php", "/views", "home.php"),
    ("views/prizes.php", "/views", "prizes.php"),
    ("views/tournament.php", "/views", "tournament.php"),
    ("views/leaderboard.php", "/views", "leaderboard.php"),
    ("views/rules.php", "/views", "rules.php"),
    ("views/terms.php", "/views", "terms.php"),
    ("views/auth/register.php", "/views/auth", "register.php"),
    ("views/auth/login.php", "/views/auth", "login.php"),
    ("views/partials/layout.php", "/views/partials", "layout.php"),
    ("views/partials/official_channels.php", "/views/partials", "official_channels.php"),
    ("views/partials/organizer_transparency.php", "/views/partials", "organizer_transparency.php"),
    ("views/partials/trust_block.php", "/views/partials", "trust_block.php"),
    ("views/partials/hero_channels.php", "/views/partials", "hero_channels.php"),
    ("views/partials/header_channels.php", "/views/partials", "header_channels.php"),
    ("views/partials/scroll_to_top.php", "/views/partials", "scroll_to_top.php"),
    ("views/partials/faq_list.php", "/views/partials", "faq_list.php"),
    ("views/partials/payment_details.php", "/views/partials", "payment_details.php"),
    ("views/faq.php", "/views", "faq.php"),
    ("views/matches.php", "/views", "matches.php"),
    ("views/partials/mascots.php", "/views/partials", "mascots.php"),
    ("views/partials/yandex_metrika.php", "/views/partials", "yandex_metrika.php"),
    ("views/admin/index.php", "/views/admin", "index.php"),
    ("views/admin/matches.php", "/views/admin", "matches.php"),
    ("views/admin/api_football.php", "/views/admin", "api_football.php"),
    ("views/admin/partials/api_football_today_cache.php", "/views/admin/partials", "api_football_today_cache.php"),
    ("views/partials/api_football_widgets.php", "/views/partials", "api_football_widgets.php"),
    ("views/partials/home_schedule.php", "/views/partials", "home_schedule.php"),
    ("views/partials/home_activity.php", "/views/partials", "home_activity.php"),
    ("views/partials/site_polls.php", "/views/partials", "site_polls.php"),
    ("views/partials/participant_engagement_stats.php", "/views/partials", "participant_engagement_stats.php"),
    ("views/partials/stage_prizes_table.php", "/views/partials", "stage_prizes_table.php"),
    ("views/partials/stage_leaders_table.php", "/views/partials", "stage_leaders_table.php"),
    ("views/rating_stages.php", "/views", "rating_stages.php"),
    ("views/compare.php", "/views", "compare.php"),
    ("views/admin/site_settings.php", "/views/admin", "site_settings.php"),
    ("views/admin/teams.php", "/views/admin", "teams.php"),
    ("views/admin/user_detail.php", "/views/admin", "user_detail.php"),
    ("views/admin/users.php", "/views/admin", "users.php"),
    ("views/admin/mini_leagues.php", "/views/admin", "mini_leagues.php"),
    ("views/admin/mini_league.php", "/views/admin", "mini_league.php"),
    ("views/admin/finale_preview.php", "/views/admin", "finale_preview.php"),
    ("views/partials/finale_results_hero.php", "/views/partials", "finale_results_hero.php"),
    ("views/user/dashboard.php", "/views/user", "dashboard.php"),
    ("views/user/my_scores.php", "/views/user", "my_scores.php"),
    ("views/user/mini_league.php", "/views/user", "mini_league.php"),
    ("views/user/mini_leagues.php", "/views/user", "mini_leagues.php"),
    ("views/user/mini_league_join_confirm.php", "/views/user", "mini_league_join_confirm.php"),
    ("views/match.php", "/views", "match.php"),
    ("views/predictions.php", "/views", "predictions.php"),
    ("views/participant.php", "/views", "participant.php"),
    ("views/privacy.php", "/views", "privacy.php"),
    ("config/config.php", "/config", "config.php"),
    ("config/config.example.php", "/config", "config.example.php"),
    ("database/schema.sql", "/database", "schema.sql"),
    ("database/migrations/004_prize_model_top5.sql", "/database/migrations", "004_prize_model_top5.sql"),
    ("database/migrations/005_payment_receipts.sql", "/database/migrations", "005_payment_receipts.sql"),
    ("database/migrations/006_prediction_reminder_log.sql", "/database/migrations", "006_prediction_reminder_log.sql"),
    ("database/migrations/007_teams_match_card_fields.sql", "/database/migrations", "007_teams_match_card_fields.sql"),
    ("database/migrations/008_tournament_groups_playoff.sql", "/database/migrations", "008_tournament_groups_playoff.sql"),
    ("database/migrations/009_api_football.sql", "/database/migrations", "009_api_football.sql"),
    ("database/migrations/010_champion_poll.sql", "/database/migrations", "010_champion_poll.sql"),
    ("database/migrations/011_match_result_notification_log.sql", "/database/migrations", "011_match_result_notification_log.sql"),
    ("database/migrations/012_engagement.sql", "/database/migrations", "012_engagement.sql"),
    ("database/migrations/013_site_polls_compact.sql", "/database/migrations", "013_site_polls_compact.sql"),
    ("database/migrations/014_site_polls_update.sql", "/database/migrations", "014_site_polls_update.sql"),
    ("database/migrations/015_fix_match_schedule_msk.sql", "/database/migrations", "015_fix_match_schedule_msk.sql"),
    ("database/fifa_2026_schedule_moscow.csv", "/database", "fifa_2026_schedule_moscow.csv"),
    # Создаёт на сервере каталог вне public для загрузок чеков (пустой маркер).
    ("storage/payment_receipts/.gitkeep", "/storage/payment_receipts", ".gitkeep"),
    ("scripts/cli_match_reminders.php", "/scripts", "cli_match_reminders.php"),
    ("scripts/apply_migration_008.php", "/scripts", "apply_migration_008.php"),
    ("scripts/apply_migration_009.php", "/scripts", "apply_migration_009.php"),
    ("scripts/cli_api_football_sync.php", "/scripts", "cli_api_football_sync.php"),
    ("storage/cache/.gitkeep", "/storage/cache", ".gitkeep"),
]

_flags_dir = ROOT / "public" / "assets" / "flags"
if _flags_dir.is_dir():
    for _flag in sorted(_flags_dir.glob("*.svg")):
        PAIRS.append((f"public/assets/flags/{_flag.name}", "/public/assets/flags", _flag.name))


def ensure_dir(ftp: ftplib.FTP, remote_dir: str) -> bool:
    """cd to remote_dir; create missing segments if needed."""
    remote_dir = remote_dir.rstrip("/") or "/"
    if remote_dir == "/":
        try:
            ftp.cwd("/")
            return True
        except ftplib.error_perm as e:
            print("CWD FAIL", remote_dir, e, file=sys.stderr)
            return False

    parts = [p for p in remote_dir.split("/") if p]
    try:
        ftp.cwd("/")
    except ftplib.error_perm:
        pass
    cur = ""
    for p in parts:
        cur += "/" + p
        try:
            ftp.cwd(cur)
        except ftplib.error_perm:
            try:
                ftp.mkd(cur)
            except ftplib.error_perm:
                pass
            try:
                ftp.cwd(cur)
            except ftplib.error_perm as e:
                print("CWD FAIL", cur, e, file=sys.stderr)
                return False
    return True


def connect_ftp(host: str, user: str, password: str, passive: bool) -> ftplib.FTP:
    ftp = ftplib.FTP(host, timeout=90)
    ftp.encoding = "utf-8"
    ftp.set_pasv(passive)
    ftp.login(user, password)
    return ftp


def upload_file(host: str, user: str, password: str, rel: str, rdir: str, name: str) -> bool:
    p = ROOT / rel
    if not p.is_file():
        print("SKIP", rel, flush=True)
        return True

    last_error: Exception | None = None
    for passive in (True, False):
        ftp: ftplib.FTP | None = None
        try:
            ftp = connect_ftp(host, user, password, passive)
            if not ensure_dir(ftp, rdir):
                return False
            with p.open("rb") as f:
                ftp.storbinary(f"STOR {name}", f)
            mode = "passive" if passive else "active"
            print("ok", rel, mode, flush=True)
            return True
        except ftplib.all_errors as e:
            last_error = e
            mode = "passive" if passive else "active"
            print("retry", rel, mode, e, flush=True)
        finally:
            if ftp is not None:
                try:
                    ftp.quit()
                except Exception:
                    ftp.close()

    print("FAIL", rel, last_error, file=sys.stderr, flush=True)
    return False


def main() -> int:
    env_path = ROOT / ".beget-ftp.env"
    env = load_env(env_path)
    host = (os.environ.get("FTP_HOST") or env.get("FTP_HOST", "")).strip()
    user = (os.environ.get("FTP_USER") or env.get("FTP_USER", "")).strip()
    password = (os.environ.get("FTP_PASSWORD") or env.get("FTP_PASSWORD", "")).strip()
    if not host or not user or not password:
        print(
            "Missing FTP_HOST, FTP_USER, or FTP_PASSWORD in",
            env_path,
            file=sys.stderr,
        )
        return 2

    if len(sys.argv) >= 2 and sys.argv[1] == "--list":
        ftp = connect_ftp(host, user, password, True)
        try:
            paths = sys.argv[2:] or ["/"]
            for remote_path in paths:
                try:
                    ftp.cwd(remote_path)
                    print("DIR", remote_path, "PWD", ftp.pwd(), flush=True)
                    for name in ftp.nlst():
                        print(" ", name, flush=True)
                except ftplib.all_errors as e:
                    print("FAIL", remote_path, e, flush=True)
        finally:
            try:
                ftp.quit()
            except Exception:
                ftp.close()
        return 0

    only = {
        item.strip().replace("\\", "/")
        for item in (os.environ.get("FTP_DEPLOY_ONLY") or "").split(",")
        if item.strip()
    }
    only.update(arg.strip().replace("\\", "/") for arg in sys.argv[1:] if arg.strip())
    pairs = [pair for pair in PAIRS if not only or pair[0] in only]

    failed = False
    for rel, rdir, name in pairs:
        if not upload_file(host, user, password, rel, rdir, name):
            failed = True

    if failed:
        return 1

    print("done", flush=True)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
