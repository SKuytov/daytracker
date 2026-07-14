-- DayTracker Database Schema
-- Run this in phpMyAdmin to set up the database

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT 'Untitled Task',
  `description` TEXT,
  `category` VARCHAR(100) DEFAULT 'general',
  `status` ENUM('idle','running','paused','completed') NOT NULL DEFAULT 'idle',
  `start_time` DATETIME,
  `end_time` DATETIME,
  `total_seconds` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `interruptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `type` ENUM('phone','colleague','meeting','other') NOT NULL DEFAULT 'other',
  `note` TEXT,
  `started_at` DATETIME NOT NULL,
  `ended_at` DATETIME,
  `duration_seconds` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `task_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `session_start` DATETIME NOT NULL,
  `session_end` DATETIME,
  `seconds` INT DEFAULT 0,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
