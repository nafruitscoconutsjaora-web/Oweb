-- ==============================================================================
-- SMS Verification & Virtual Number Reseller Platform
-- Production MySQL 8.0+ Database Schema
-- Strict Foreign Keys, UTC Timestamps, Financial DECIMAL(12,4) precision
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. ROLES & PERMISSIONS
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `module` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. USER GROUPS & TIERS (Wholesale / Retail / Business / API)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_groups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `priority` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. USERS & ADMINS
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    `user_group_id` INT UNSIGNED NULL,
    `status` ENUM('active', 'suspended', 'unverified') NOT NULL DEFAULT 'active',
    `email_verified_at` DATETIME NULL,
    `remember_token` VARCHAR(100) NULL,
    `last_login_at` DATETIME NULL,
    `last_login_ip` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_group` FOREIGN KEY (`user_group_id`) REFERENCES `user_groups` (`id`) ON DELETE SET NULL,
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `role_id` INT UNSIGNED NOT NULL,
    `department` VARCHAR(50) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_admins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_admins_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. WALLET & FINANCIAL LEDGER (Atomic Balance + Append-only Transactions)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wallets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `balance` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `total_deposited` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `total_spent` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `total_refunded` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_wallets_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `transaction_ref` VARCHAR(64) NOT NULL UNIQUE,
    `wallet_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('credit', 'debit', 'refund', 'adjustment', 'payment') NOT NULL,
    `amount` DECIMAL(12,4) NOT NULL,
    `balance_before` DECIMAL(12,4) NOT NULL,
    `balance_after` DECIMAL(12,4) NOT NULL,
    `status` ENUM('completed', 'pending', 'failed', 'cancelled') NOT NULL DEFAULT 'completed',
    `description` VARCHAR(255) NOT NULL,
    `reference_type` VARCHAR(50) NULL,
    `reference_id` VARCHAR(100) NULL,
    `created_by_admin_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_wt_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_wt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    INDEX `idx_wt_user_date` (`user_id`, `created_at`),
    INDEX `idx_wt_ref` (`reference_type`, `reference_id`),
    INDEX `idx_wt_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. PAYMENT GATEWAY TRANSACTIONS
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `payment_ref` VARCHAR(64) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `gateway` VARCHAR(50) NOT NULL DEFAULT 'razorpay',
    `order_id` VARCHAR(100) NULL UNIQUE,
    `gateway_payment_id` VARCHAR(100) NULL UNIQUE,
    `gateway_signature` VARCHAR(255) NULL,
    `amount` DECIMAL(12,4) NOT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
    `idempotency_key` VARCHAR(64) NULL UNIQUE,
    `raw_response` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    INDEX `idx_payments_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. COUNTRIES & SERVICES
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `countries` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `iso_code` CHAR(2) NOT NULL UNIQUE,
    `dial_code` VARCHAR(10) NOT NULL,
    `flag_emoji` VARCHAR(20) NOT NULL DEFAULT '🌐',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_countries_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `services` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `icon_svg` TEXT NULL,
    `description` VARCHAR(255) NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'general',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_services_active` (`is_active`, `sort_order`),
    INDEX `idx_services_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. PROVIDERS & ROUTING ADAPTER CONFIGURATION
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `providers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `api_endpoint` VARCHAR(255) NOT NULL,
    `api_key_encrypted` TEXT NULL,
    `api_secret_encrypted` TEXT NULL,
    `adapter_class` VARCHAR(100) NOT NULL,
    `priority` INT NOT NULL DEFAULT 10,
    `balance` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `balance_currency` CHAR(3) NOT NULL DEFAULT 'USD',
    `timeout_sec` INT NOT NULL DEFAULT 15,
    `retry_limit` INT NOT NULL DEFAULT 2,
    `supports_cancellation` TINYINT(1) NOT NULL DEFAULT 1,
    `supports_webhooks` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_health_check` DATETIME NULL,
    `health_status` ENUM('healthy', 'degraded', 'offline') NOT NULL DEFAULT 'healthy',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_providers_active` (`is_active`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `provider_countries` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `provider_id` INT UNSIGNED NOT NULL,
    `country_id` INT UNSIGNED NOT NULL,
    `provider_country_code` VARCHAR(50) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT `fk_pc_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pc_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_provider_country` (`provider_id`, `country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `provider_services` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `provider_id` INT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    `provider_service_code` VARCHAR(50) NOT NULL,
    `provider_cost` DECIMAL(12,4) NOT NULL DEFAULT 0.1000,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT `fk_ps_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ps_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_provider_service` (`provider_id`, `service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. PRICING ENGINE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pricing_rules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `rule_type` ENUM('global', 'country', 'service', 'country_service', 'provider') NOT NULL DEFAULT 'global',
    `provider_id` INT UNSIGNED NULL,
    `country_id` INT UNSIGNED NULL,
    `service_id` INT UNSIGNED NULL,
    `user_group_id` INT UNSIGNED NULL,
    `margin_type` ENUM('fixed', 'percentage') NOT NULL DEFAULT 'percentage',
    `margin_value` DECIMAL(10,4) NOT NULL DEFAULT 20.0000,
    `priority` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pr_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pr_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pr_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pr_group` FOREIGN KEY (`user_group_id`) REFERENCES `user_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 9. ORDERS & ACTIVATIONS (Live SMS Flow)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(32) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    `country_id` INT UNSIGNED NOT NULL,
    `provider_id` INT UNSIGNED NOT NULL,
    `price` DECIMAL(12,4) NOT NULL,
    `provider_cost` DECIMAL(12,4) NOT NULL,
    `margin` DECIMAL(12,4) NOT NULL,
    `status` ENUM('active', 'completed', 'expired', 'cancelled', 'refunded', 'failed') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE RESTRICT,
    INDEX `idx_orders_user_status` (`user_id`, `status`),
    INDEX `idx_orders_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `provider_id` INT UNSIGNED NOT NULL,
    `provider_activation_id` VARCHAR(100) NOT NULL,
    `phone_number` VARCHAR(30) NOT NULL,
    `full_phone_number` VARCHAR(40) NOT NULL,
    `status` ENUM('waiting', 'number_assigned', 'waiting_sms', 'sms_received', 'completed', 'expired', 'cancelled', 'refunded', 'provider_error') NOT NULL DEFAULT 'waiting_sms',
    `expires_at` DATETIME NOT NULL,
    `sms_received_at` DATETIME NULL,
    `raw_provider_payload` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_act_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_act_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_act_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE RESTRICT,
    INDEX `idx_act_user_status` (`user_id`, `status`),
    INDEX `idx_act_provider_ref` (`provider_id`, `provider_activation_id`),
    INDEX `idx_act_expires` (`expires_at`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sms_messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `activation_id` BIGINT UNSIGNED NOT NULL,
    `sender` VARCHAR(50) NULL,
    `message_text` TEXT NOT NULL,
    `verification_code` VARCHAR(50) NULL,
    `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `raw_payload` JSON NULL,
    CONSTRAINT `fk_sms_activation` FOREIGN KEY (`activation_id`) REFERENCES `activations` (`id`) ON DELETE CASCADE,
    INDEX `idx_sms_activation` (`activation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 10. REFUNDS ENGINE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `refunds` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `activation_id` BIGINT UNSIGNED NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `amount` DECIMAL(12,4) NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `initiated_by` ENUM('system', 'admin', 'user') NOT NULL DEFAULT 'system',
    `admin_id` BIGINT UNSIGNED NULL,
    `status` ENUM('completed', 'failed') NOT NULL DEFAULT 'completed',
    `wallet_transaction_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_refunds_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_refunds_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    INDEX `idx_refunds_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 11. NOTIFICATIONS
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_notif_user` (`user_id`, `is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 12. SUPPORT TICKETS
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_ref` VARCHAR(32) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'general',
    `subject` VARCHAR(200) NOT NULL,
    `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    `status` ENUM('open', 'pending', 'waiting', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_st_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_st_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
    INDEX `idx_st_user_status` (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` BIGINT UNSIGNED NOT NULL,
    `sender_type` ENUM('user', 'admin') NOT NULL,
    `sender_id` BIGINT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sm_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
    INDEX `idx_sm_ticket` (`ticket_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 13. FUTURE B2B API CUSTOMERS & KEYS
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_customers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `company_name` VARCHAR(150) NULL,
    `rate_limit_per_min` INT NOT NULL DEFAULT 60,
    `daily_request_limit` INT NOT NULL DEFAULT 10000,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_api_cust_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_keys` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `key_prefix` VARCHAR(16) NOT NULL,
    `key_hash` VARCHAR(255) NOT NULL,
    `label` VARCHAR(100) NOT NULL DEFAULT 'Default API Key',
    `permissions` JSON NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_used_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ak_customer` FOREIGN KEY (`customer_id`) REFERENCES `api_customers` (`id`) ON DELETE CASCADE,
    INDEX `idx_ak_prefix` (`key_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_usage_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `api_key_id` BIGINT UNSIGNED NULL,
    `endpoint` VARCHAR(150) NOT NULL,
    `method` VARCHAR(10) NOT NULL,
    `status_code` INT NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `response_time_ms` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_aul_customer_date` (`customer_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 14. AUDIT LOGS & SECURITY SESSIONS
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `actor_type` ENUM('admin', 'user', 'system') NOT NULL,
    `actor_id` BIGINT UNSIGNED NULL,
    `actor_email` VARCHAR(191) NULL,
    `action` VARCHAR(100) NOT NULL,
    `target_type` VARCHAR(100) NULL,
    `target_id` VARCHAR(100) NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_actor` (`actor_type`, `actor_id`),
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_activity` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `email` VARCHAR(191) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `status` ENUM('success', 'failed') NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_login_email` (`email`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(191) NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pr_email` (`email`),
    INDEX `idx_pr_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 15. SYSTEM SETTINGS
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT NULL,
    `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
    `description` VARCHAR(255) NULL,
    `is_public` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- SEED ESSENTIAL SYSTEM METADATA & PERMISSIONS (NO FAKE USER DATA)
-- ------------------------------------------------------------------------------
INSERT IGNORE INTO `roles` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Super Admin', 'super_admin', 'Full platform operational & financial governance'),
(2, 'Admin', 'admin', 'Standard platform administrator'),
(3, 'Finance', 'finance', 'Wallet, payments, transactions, and refund officer'),
(4, 'Support', 'support', 'Customer assistance and ticket handling'),
(5, 'Operations', 'operations', 'Service, country, and provider configuration');

INSERT IGNORE INTO `permissions` (`id`, `module`, `name`, `slug`, `description`) VALUES
(1, 'users', 'View Users', 'users.view', 'View registered user accounts and profiles'),
(2, 'users', 'Manage Users', 'users.manage', 'Suspend, update, or edit user accounts'),
(3, 'wallet', 'View Ledger', 'wallet.view', 'View balances and financial transactions'),
(4, 'wallet', 'Adjust Balance', 'wallet.adjust', 'Credit or debit customer wallet balances manually'),
(5, 'providers', 'Manage Providers', 'providers.manage', 'Configure endpoints, API keys, and routing weights'),
(6, 'services', 'Manage Services', 'services.manage', 'Create or toggle supported verification services'),
(7, 'pricing', 'Configure Pricing', 'pricing.manage', 'Configure margins, formulas, and user group tiers'),
(8, 'orders', 'Manage Orders', 'orders.manage', 'View and issue manual refunds for activations'),
(9, 'support', 'Handle Tickets', 'support.manage', 'Reply to and close customer support tickets'),
(10, 'settings', 'System Settings', 'settings.manage', 'Update core platform settings and credentials'),
(11, 'audit', 'View Audit Logs', 'audit.view', 'Inspect security audit logs and staff actions');

-- Super Admin gets all permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Initial Default System Settings
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `description`, `is_public`) VALUES
('site_name', 'Verifex SMS Hub', 'general', 'Platform public title', 1),
('site_currency', 'USD', 'financial', 'Base platform accounting currency', 1),
('currency_symbol', '$', 'financial', 'Platform currency symbol', 1),
('min_deposit', '5.00', 'financial', 'Minimum deposit limit for user wallets', 1),
('max_deposit', '1000.00', 'financial', 'Maximum deposit limit for user wallets', 1),
('activation_timeout_minutes', '20', 'activation', 'Standard expiry window for numbers waiting for SMS', 1),
('default_margin_percentage', '25.00', 'pricing', 'Default markup added to provider base wholesale cost', 0),
('razorpay_enabled', '0', 'payment', 'Toggle Razorpay payment gateway active state', 0),
('razorpay_key_id', '', 'payment', 'Razorpay API Key ID', 0),
('razorpay_key_secret', '', 'payment', 'Razorpay Key Secret', 0),
('maintenance_mode', '0', 'general', 'Toggle maintenance mode status', 1),
('contact_email', 'support@verifex.net', 'general', 'Public support contact email', 1);

-- Default User Groups
INSERT IGNORE INTO `user_groups` (`id`, `name`, `slug`, `discount_percentage`, `priority`, `is_active`) VALUES
(1, 'Retail Customer', 'retail', 0.00, 0, 1),
(2, 'Wholesale / Reseller', 'wholesale', 10.00, 10, 1),
(3, 'Enterprise / API', 'enterprise', 15.00, 20, 1);

SET FOREIGN_KEY_CHECKS = 1;
