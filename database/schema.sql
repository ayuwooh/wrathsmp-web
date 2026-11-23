-- Estrutura do banco de dados do sistema VIP.
-- Importe com: mysql -u root -p wrathsmp < database/schema.sql
--
-- CREATE DATABASE wrathsmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username    VARCHAR(16)  NOT NULL COMMENT 'nick do Minecraft (sempre em minúsculas)',
    uuid        CHAR(36)     NULL     DEFAULT NULL COMMENT 'UUID do jogador, quando disponível',
    email       VARCHAR(255) NOT NULL,
    vip_active  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'espelho do status enviado ao plugin',
    vip_until   DATETIME     NULL     DEFAULT NULL COMMENT 'data/hora UTC em que o VIP termina',
    created_at  DATETIME     NOT NULL,
    updated_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mercado_pago_id  VARCHAR(64)  NOT NULL COMMENT 'id do pagamento no MercadoPago',
    user_id          INT UNSIGNED NOT NULL,
    preference_id    VARCHAR(64)  NULL     DEFAULT NULL,
    status           VARCHAR(32)  NOT NULL COMMENT 'approved, rejected, pending, refunded...',
    amount           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    days_granted     INT          NOT NULL DEFAULT 0,
    paid_at          DATETIME     NULL     DEFAULT NULL,
    granted_at       DATETIME     NULL     DEFAULT NULL COMMENT 'quando os dias foram somados ao usuário',
    notified_at      DATETIME     NULL     DEFAULT NULL COMMENT 'quando o plugin confirmou a ativação',
    notify_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error       TEXT         NULL     DEFAULT NULL,
    raw_payload      TEXT         NULL     DEFAULT NULL COMMENT 'JSON completo devolvido pelo MercadoPago',
    created_at       DATETIME     NOT NULL,
    updated_at       DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_mp (mercado_pago_id),
    KEY idx_payments_user (user_id),
    KEY idx_payments_notify (status, notified_at, notify_attempts),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vip_events (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    action     ENUM('GRANT', 'EXTEND', 'REVOKE') NOT NULL,
    days       INT          NULL DEFAULT NULL,
    reason     VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_vip_events_user (user_id),
    CONSTRAINT fk_vip_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
