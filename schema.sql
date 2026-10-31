-- =====================================================================
--  Refer & Earn  —  Database Schema
--  Target: MySQL 5.7+ / MariaDB 10.2+  (XAMPP default: MariaDB 10.4)
--  Charset: utf8mb4 / utf8mb4_unicode_ci
--
--  Portability notes:
--    * No window functions (RANK/ROW_NUMBER) — v_leaderboard uses a
--      correlated subquery so MySQL 5.7 works too.
--    * The CHECK constraint on `withdrawals` is parsed but ignored by
--      MySQL 5.7; it is enforced on MySQL 8.0.16+ / MariaDB 10.2.1+.
--    * DESC in `idx_users_leaderboard` is ignored by MySQL 5.7 (harmless).
--    * JSON columns are supported from MySQL 5.7.8 / MariaDB 10.2.7.
--
--  Covers:
--    * Telegram bot users, wallets and referral earnings   (index, refer-earn, activity, leaderboard, withdraw)
--    * Withdrawal requests + admin review workflow         (adm/withdrawal.php)
--    * Broadcast campaigns, buttons and delivery stats     (adm/broadcast.php)
--    * Platform / referral / withdrawal / bonus settings   (adm/settings.php)
--    * Admin accounts, sessions and audit trail            (adm/auth/index.php)
--
--  Import:  mysql -u root -p < schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `refer_earn`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `refer_earn`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
--  1. ADMINS  (adm/auth/index.php)
-- =====================================================================

DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(100)    NOT NULL,
  `username`       VARCHAR(60)     NOT NULL,
  `email`          VARCHAR(190)    NOT NULL,
  `password_hash`  VARCHAR(255)    NOT NULL,
  `role`           ENUM('super_admin','admin','moderator') NOT NULL DEFAULT 'admin',
  `avatar_url`     VARCHAR(500)    DEFAULT NULL,
  `is_active`      TINYINT(1)      NOT NULL DEFAULT 1,
  `last_login_at`  DATETIME        DEFAULT NULL,
  `last_login_ip`  VARCHAR(45)     DEFAULT NULL,
  `remember_token` VARCHAR(100)    DEFAULT NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `admin_sessions`;
CREATE TABLE `admin_sessions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`    BIGINT UNSIGNED NOT NULL,
  `token_hash`  CHAR(64)        NOT NULL,
  `ip_address`  VARCHAR(45)     DEFAULT NULL,
  `user_agent`  VARCHAR(255)    DEFAULT NULL,
  `expires_at`  DATETIME        NOT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_sessions_token` (`token_hash`),
  KEY `idx_admin_sessions_admin` (`admin_id`, `expires_at`),
  CONSTRAINT `fk_admin_sessions_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  2. USERS  (Telegram bot users — index.php, withdraw.php)
-- =====================================================================

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `telegram_id`        BIGINT          NOT NULL COMMENT 'Telegram chat id, shown masked as *****6789',
  `username`           VARCHAR(64)     DEFAULT NULL COMMENT 'Telegram @username without the @',
  `first_name`         VARCHAR(100)    DEFAULT NULL,
  `last_name`          VARCHAR(100)    DEFAULT NULL,
  `avatar_url`         VARCHAR(500)    DEFAULT NULL,
  `referral_code`      VARCHAR(32)     NOT NULL COMMENT 'Used in t.me/<bot>?start=<code>',
  `referred_by_id`     BIGINT UNSIGNED DEFAULT NULL COMMENT 'Inviter (self reference)',
  `balance_t`          DECIMAL(14,2)   NOT NULL DEFAULT 0.00 COMMENT 'Wallet balance in T coins',
  `total_earned_t`     DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `total_withdrawn_t`  DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `referral_count`     INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Denormalised count of credited referrals',
  `status`             ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `bot_started_at`     DATETIME        DEFAULT NULL COMMENT 'Set when the bot first receives a message from this user',
  `channels_verified_at` DATETIME      DEFAULT NULL COMMENT 'Last time every required channel membership was confirmed',
  `pending_referral_code` VARCHAR(32)  DEFAULT NULL COMMENT 'Referral code captured by /start, applied inside the web app',
  `joined_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_active_at`     DATETIME        DEFAULT NULL,
  `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_telegram_id` (`telegram_id`),
  UNIQUE KEY `uq_users_referral_code` (`referral_code`),
  KEY `idx_users_username` (`username`),
  KEY `idx_users_referred_by` (`referred_by_id`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_leaderboard` (`referral_count` DESC, `total_earned_t` DESC),
  CONSTRAINT `fk_users_referred_by`
    FOREIGN KEY (`referred_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  3. REFERRALS  (refer-earn.php, activity.php, leaderboard.php)
-- =====================================================================

DROP TABLE IF EXISTS `referrals`;
CREATE TABLE `referrals` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_id`       BIGINT UNSIGNED NOT NULL COMMENT 'User who shared the link',
  `referee_id`        BIGINT UNSIGNED NOT NULL COMMENT 'User who joined',
  `reward_t`          DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `status`            ENUM('pending','credited','reversed') NOT NULL DEFAULT 'pending',
  `credited_at`       DATETIME        DEFAULT NULL,
  `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_referrals_referee` (`referee_id`) COMMENT 'A user can only be referred once',
  KEY `idx_referrals_referrer` (`referrer_id`, `status`),
  KEY `idx_referrals_created` (`created_at`),
  CONSTRAINT `fk_referrals_referrer`
    FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_referrals_referee`
    FOREIGN KEY (`referee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  4. WALLET LEDGER  (activity.php, index.php "Recent Activity")
-- =====================================================================

DROP TABLE IF EXISTS `wallet_transactions`;
CREATE TABLE `wallet_transactions` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          BIGINT UNSIGNED NOT NULL,
  `type`             ENUM('referral_reward','signup_bonus','bonus','withdrawal_debit',
                          'withdrawal_refund','admin_credit','admin_debit') NOT NULL,
  `direction`        ENUM('credit','debit') NOT NULL,
  `amount_t`         DECIMAL(14,2)   NOT NULL,
  `balance_after_t`  DECIMAL(14,2)   NOT NULL,
  `reference_type`   ENUM('referral','withdrawal','broadcast','manual') DEFAULT NULL,
  `reference_id`     BIGINT UNSIGNED DEFAULT NULL,
  `description`      VARCHAR(255)    DEFAULT NULL COMMENT 'e.g. "@rahul123 joined using your link"',
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_tx_user` (`user_id`, `created_at`),
  KEY `idx_wallet_tx_reference` (`reference_type`, `reference_id`),
  KEY `idx_wallet_tx_type` (`type`),
  CONSTRAINT `fk_wallet_tx_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  5. WITHDRAWALS  (withdraw.php + adm/withdrawal.php)
-- =====================================================================

DROP TABLE IF EXISTS `withdrawals`;
CREATE TABLE `withdrawals` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`             BIGINT UNSIGNED NOT NULL,
  `amount_t`            DECIMAL(14,2)   NOT NULL COMMENT 'Requested amount in T coins',
  `amount_inr`          DECIMAL(14,2)   NOT NULL COMMENT 'Payout value in ₹ (T x conversion rate)',
  `payout_method`       ENUM('upi','bank') NOT NULL DEFAULT 'upi',
  `upi_id`              VARCHAR(120)    DEFAULT NULL COMMENT 'e.g. rahul@okaxis',
  `bank_account_name`   VARCHAR(120)    DEFAULT NULL,
  `bank_account_number` VARCHAR(40)     DEFAULT NULL,
  `bank_ifsc`           VARCHAR(20)     DEFAULT NULL,
  `status`              ENUM('pending','processing','completed','rejected','cancelled')
                        NOT NULL DEFAULT 'pending',
  `transaction_id`      VARCHAR(120)    DEFAULT NULL COMMENT 'Payout txid returned by the withdrawal API',
  `admin_note`          VARCHAR(500)    DEFAULT NULL,
  `rejection_reason`    VARCHAR(500)    DEFAULT NULL,
  `api_response`        JSON            DEFAULT NULL,
  `requested_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at`        DATETIME        DEFAULT NULL,
  `processed_by`        BIGINT UNSIGNED DEFAULT NULL COMMENT 'Admin who approved/rejected',
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_withdrawals_user` (`user_id`, `requested_at`),
  KEY `idx_withdrawals_status` (`status`, `requested_at`),
  KEY `idx_withdrawals_requested` (`requested_at`),
  CONSTRAINT `fk_withdrawals_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_withdrawals_admin`
    FOREIGN KEY (`processed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_withdrawals_amount` CHECK (`amount_t` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `withdrawal_status_history`;
CREATE TABLE `withdrawal_status_history` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `withdrawal_id`  BIGINT UNSIGNED NOT NULL,
  `from_status`    ENUM('pending','processing','completed','rejected','cancelled') DEFAULT NULL,
  `to_status`      ENUM('pending','processing','completed','rejected','cancelled') NOT NULL,
  `admin_id`       BIGINT UNSIGNED DEFAULT NULL,
  `note`           VARCHAR(500)    DEFAULT NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wsh_withdrawal` (`withdrawal_id`, `created_at`),
  CONSTRAINT `fk_wsh_withdrawal`
    FOREIGN KEY (`withdrawal_id`) REFERENCES `withdrawals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wsh_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  6. BROADCASTS  (adm/broadcast.php)
-- =====================================================================

DROP TABLE IF EXISTS `broadcasts`;
CREATE TABLE `broadcasts` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`             VARCHAR(190)    NOT NULL COMMENT 'Internal label, e.g. "Diwali Special Bonus!"',
  `message_text`      TEXT            DEFAULT NULL COMMENT 'Telegram markdown/HTML body',
  `parse_mode`        ENUM('none','Markdown','MarkdownV2','HTML') NOT NULL DEFAULT 'Markdown',
  `media_type`        ENUM('none','image','video','file') NOT NULL DEFAULT 'none',
  `media_path`        VARCHAR(500)    DEFAULT NULL,
  `audience`          ENUM('all','active','inactive','custom') NOT NULL DEFAULT 'all',
  `audience_filter`   JSON            DEFAULT NULL COMMENT 'Extra targeting rules for custom audiences',
  `status`            ENUM('draft','scheduled','sending','sent','failed') NOT NULL DEFAULT 'draft',
  `scheduled_at`      DATETIME        DEFAULT NULL,
  `sent_at`           DATETIME        DEFAULT NULL,
  `total_recipients`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `delivered_count`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `failed_count`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_by`        BIGINT UNSIGNED DEFAULT NULL,
  `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_broadcasts_status` (`status`, `scheduled_at`),
  KEY `idx_broadcasts_sent` (`sent_at`),
  CONSTRAINT `fk_broadcasts_admin`
    FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `broadcast_buttons`;
CREATE TABLE `broadcast_buttons` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `broadcast_id`  BIGINT UNSIGNED NOT NULL,
  `label`         VARCHAR(64)     NOT NULL COMMENT 'Button text, e.g. "Open App"',
  `url`           VARCHAR(500)    NOT NULL,
  `style`         ENUM('primary','success','danger') NOT NULL DEFAULT 'primary',
  `position`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_broadcast_buttons_pos` (`broadcast_id`, `position`),
  CONSTRAINT `fk_broadcast_buttons_broadcast`
    FOREIGN KEY (`broadcast_id`) REFERENCES `broadcasts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `broadcast_recipients`;
CREATE TABLE `broadcast_recipients` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `broadcast_id`   BIGINT UNSIGNED NOT NULL,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `status`         ENUM('pending','delivered','failed') NOT NULL DEFAULT 'pending',
  `error_message`  VARCHAR(255)    DEFAULT NULL,
  `delivered_at`   DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_broadcast_recipients` (`broadcast_id`, `user_id`),
  KEY `idx_broadcast_recipients_status` (`broadcast_id`, `status`),
  CONSTRAINT `fk_broadcast_recipients_broadcast`
    FOREIGN KEY (`broadcast_id`) REFERENCES `broadcasts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_broadcast_recipients_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  7. SETTINGS  (adm/settings.php — key/value store)
-- =====================================================================

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `key`         VARCHAR(100) NOT NULL,
  `value`       TEXT         DEFAULT NULL,
  `data_type`   ENUM('string','integer','decimal','boolean','json','url','secret') NOT NULL DEFAULT 'string',
  `group_name`  VARCHAR(50)  NOT NULL DEFAULT 'general',
  `label`       VARCHAR(150) DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`),
  KEY `idx_settings_group` (`group_name`),
  CONSTRAINT `fk_settings_admin`
    FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  8. AUDIT LOG  (admin actions on withdrawals / settings / broadcasts)
-- =====================================================================

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`     BIGINT UNSIGNED DEFAULT NULL,
  `action`       VARCHAR(80)     NOT NULL COMMENT 'e.g. withdrawal.approve, settings.update',
  `entity_type`  VARCHAR(50)     DEFAULT NULL,
  `entity_id`    BIGINT UNSIGNED DEFAULT NULL,
  `meta`         JSON            DEFAULT NULL,
  `ip_address`   VARCHAR(45)     DEFAULT NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_admin` (`admin_id`, `created_at`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_audit_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  9. REQUIRED CHANNELS  (channel gate — adm/channels.php, index.php)
-- =====================================================================

DROP TABLE IF EXISTS `required_channels`;
CREATE TABLE `required_channels` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id`       VARCHAR(64)     NOT NULL COMMENT 'e.g. @mychannel or -1001234567890',
  `title`         VARCHAR(150)    NOT NULL,
  `invite_url`    VARCHAR(500)    DEFAULT NULL COMMENT 't.me link opened by the Join button',
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `sort_order`    INT             NOT NULL DEFAULT 0,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_required_channels_chat` (`chat_id`),
  KEY `idx_required_channels_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `user_devices` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `device_id`   CHAR(36)        NOT NULL COMMENT 'Random UUID stored in cookie + localStorage + IndexedDB',
  `fingerprint` CHAR(64)        DEFAULT NULL COMMENT 'SHA-256 of browser/hardware traits (secondary signal)',
  `ip_address`  VARCHAR(45)     DEFAULT NULL,
  `user_agent`  VARCHAR(255)    DEFAULT NULL,
  `first_seen_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_device` (`user_id`, `device_id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_fp_ip` (`fingerprint`, `ip_address`),
  CONSTRAINT `fk_user_devices_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 10. TELEGRAM UPDATES  (webhook/index.php — raw update log)
-- =====================================================================

DROP TABLE IF EXISTS `telegram_updates`;
CREATE TABLE `telegram_updates` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `update_id`      BIGINT          DEFAULT NULL COMMENT 'Telegram update_id',
  `telegram_id`    BIGINT          DEFAULT NULL COMMENT 'Sender chat id, when present',
  `update_type`    VARCHAR(40)     DEFAULT NULL COMMENT 'message / callback_query / web_app_data ...',
  `command`        VARCHAR(64)     DEFAULT NULL COMMENT 'e.g. /start',
  `payload`        JSON            DEFAULT NULL COMMENT 'Raw update body',
  `handled`        TINYINT(1)      NOT NULL DEFAULT 0,
  `error_message`  VARCHAR(500)    DEFAULT NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_telegram_updates_update_id` (`update_id`),
  KEY `idx_telegram_updates_user` (`telegram_id`, `created_at`),
  KEY `idx_telegram_updates_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- 11. VIEWS  (dashboard KPI cards + leaderboard page)
-- =====================================================================

-- Leaderboard: rank users by credited referrals, then earnings.
--
-- Deliberately avoids RANK() OVER (...): window functions need MySQL 8.0+ or
-- MariaDB 10.2+, and older shared hosts still run MySQL 5.7. The correlated
-- subquery below produces identical RANK() semantics — users tied on both
-- referral_count and total_earned_t share a rank, and the next rank is skipped.
CREATE OR REPLACE VIEW `v_leaderboard` AS
SELECT
  `u`.`id`              AS `user_id`,
  `u`.`username`,
  `u`.`avatar_url`,
  `u`.`referral_count`,
  `u`.`total_earned_t`,
  (
    SELECT COUNT(*) + 1
    FROM `users` `x`
    WHERE `x`.`status` <> 'blocked'
      AND (
            `x`.`referral_count` > `u`.`referral_count`
         OR (`x`.`referral_count` = `u`.`referral_count`
             AND `x`.`total_earned_t` > `u`.`total_earned_t`)
      )
  ) AS `rank_position`
FROM `users` `u`
WHERE `u`.`status` <> 'blocked';

-- Withdrawal KPI cards on adm/withdrawal.php
CREATE OR REPLACE VIEW `v_withdrawal_stats` AS
SELECT
  COUNT(*)                                                        AS `total_requests`,
  SUM(`status` = 'pending')                                       AS `pending_count`,
  SUM(`status` = 'completed')                                     AS `completed_count`,
  SUM(`status` = 'rejected')                                      AS `rejected_count`,
  COALESCE(SUM(CASE WHEN `status` = 'completed' THEN `amount_t` END), 0) AS `total_withdrawn_t`,
  COALESCE(SUM(CASE WHEN `status` = 'pending'   THEN `amount_t` END), 0) AS `pending_amount_t`
FROM `withdrawals`;

-- Broadcast KPI cards on adm/broadcast.php
CREATE OR REPLACE VIEW `v_broadcast_stats` AS
SELECT
  COUNT(*)                        AS `total_broadcasts`,
  COALESCE(SUM(`total_recipients`), 0) AS `total_recipients`,
  COALESCE(SUM(`delivered_count`), 0)  AS `delivered_count`,
  COALESCE(SUM(`failed_count`), 0)     AS `failed_count`,
  COALESCE(SUM(`status` = 'sent'), 0)  AS `sent_broadcasts`,
  COALESCE(SUM(`status` = 'draft'), 0) AS `draft_broadcasts`
FROM `broadcasts`;

-- =====================================================================
-- 12. SEED DATA
-- =====================================================================

-- Default admin — password is "admin123" (bcrypt). CHANGE IT AFTER IMPORT.
INSERT INTO `admins` (`name`, `username`, `email`, `password_hash`, `role`) VALUES
  ('Super Admin', 'admin', 'admin@referearn.com',
   '$2y$10$9mHnk/bpG4ixmPu/NOuexOKFw64CC5FMEgpy7YIaArobClamRX9L.', 'super_admin');

-- Settings mirrored from adm/settings.php
INSERT INTO `settings` (`key`, `value`, `data_type`, `group_name`, `label`) VALUES
  ('referral_reward_t',        '150',                          'decimal', 'referral',  'Reward per Referral (T)'),
  ('min_referrals_withdrawal', '5',                            'integer', 'referral',  'Minimum Referrals per Withdrawal'),
  ('min_withdrawal_t',         '2000',                         'decimal', 'withdrawal','Minimum Withdrawal Amount (T) — also caps the first withdrawal'),
  ('max_withdrawal_t',         '50000',                        'decimal', 'withdrawal','Maximum Withdrawal Amount (T)'),
  ('auto_approve_withdrawals', '0',                            'boolean', 'withdrawal','Auto Approve Withdrawals'),
  ('withdrawal_api_url',       '',                             'url',     'withdrawal','Withdrawal API URL — auto-pays the first withdrawal'),
  ('coin_to_inr_rate',         '0.10',                         'decimal', 'withdrawal','Conversion rate: 1 T in ₹'),
  ('bot_token',                '',                             'secret',  'bot',       'Telegram Bot Token'),
  ('bot_web_app_url',          '',                             'url',     'bot',       'Bot Web App URL'),
  ('bot_username',             '',                             'string',  'bot',       'Telegram Bot Username (auto-filled)'),
  ('webhook_secret',           '',                             'secret',  'bot',       'Telegram Webhook Secret Token'),
  ('signup_bonus_t',           '50',                           'decimal', 'bonus',     'Signup Bonus (T)'),
  ('enable_signup_bonus',      '1',                            'boolean', 'bonus',     'Enable Signup Bonus'),
  ('allow_registration',       '1',                            'boolean', 'platform',  'Allow New User Registration'),
  ('withdrawals_enabled',      '1',                            'boolean', 'platform',  'Withdrawals Enabled'),
  ('withdrawal_processing_hours', '24',                        'integer', 'withdrawal','Processing time shown to users (hours)'),

  -- Channel gate: force users to join channels before the mini app unlocks.
  ('require_channel_join',     '0',                            'boolean', 'gate',      'Require Channel Membership'),
  ('require_bot_start',        '1',                            'boolean', 'gate',      'Require /start Before Using the App'),
  ('channel_gate_strict',      '0',                            'boolean', 'gate',      'Block Users When the Channel Check Fails'),
  ('channel_gate_message',     'Join our channels to unlock Refer & Earn and start earning rewards.', 'string', 'gate', 'Channel Gate Message');

-- =====================================================================
--  End of schema
-- =====================================================================
