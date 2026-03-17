  -- ============================================================
  -- KHADEMNI — Database Schema
  -- Run this in phpMyAdmin or MySQL CLI to set up the database
  -- ============================================================

  CREATE DATABASE IF NOT EXISTS `khademni`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

  USE `khademni`;

  -- ==================== USERS ====================
  CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('candidate', 'company', 'admin') NOT NULL DEFAULT 'candidate',
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `verification_token` VARCHAR(64) NULL DEFAULT NULL,
    `verification_token_expires` TIMESTAMP NULL DEFAULT NULL,
    `reset_token` VARCHAR(64) NULL DEFAULT NULL,
    `reset_token_expires` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_verification_token` (`verification_token`),
    INDEX `idx_reset_token` (`reset_token`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ==================== CANDIDATE PROFILES ====================
  CREATE TABLE IF NOT EXISTS `candidate_profiles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL UNIQUE,
    `location` VARCHAR(255) NULL DEFAULT NULL,
    `bio` TEXT NULL DEFAULT NULL,
    `experience_years` INT UNSIGNED NULL DEFAULT 0,
    `skills` JSON NULL DEFAULT NULL,
    `cv_path` VARCHAR(500) NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `fk_candidate_user` FOREIGN KEY (`user_id`)
      REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ==================== COMPANY PROFILES ====================
  CREATE TABLE IF NOT EXISTS `company_profiles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL UNIQUE,
    `company_name` VARCHAR(255) NULL DEFAULT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `website` VARCHAR(500) NULL DEFAULT NULL,
    `location` VARCHAR(255) NULL DEFAULT NULL,
    `logo_path` VARCHAR(500) NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `fk_company_user` FOREIGN KEY (`user_id`)
      REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ==================== LOGIN ATTEMPTS (Rate Limiting) ====================
  CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_email_ip` (`email`, `ip_address`),
    INDEX `idx_attempted_at` (`attempted_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ==================== Cleanup old login attempts (optional event) ====================
  -- Run manually or set up as a cron:
  -- DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 HOUR;
