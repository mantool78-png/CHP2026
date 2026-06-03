-- После смены модели призов: взнос 1500 ₽, топ-5, реферальная скидка 500 ₽, без процента в призовой фонд из взноса.
-- Выполните на сервере один раз при обновлении с старой схемы.

INSERT INTO settings (setting_key, setting_value, updated_at)
VALUES ('entry_fee_rub', '1500', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

INSERT INTO settings (setting_key, setting_value, updated_at)
VALUES
    ('referral_discount_rub', '500', NOW()),
    ('referral_discount_limit_per_account', '1', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

DELETE FROM settings WHERE setting_key = 'prize_pool_percent';
