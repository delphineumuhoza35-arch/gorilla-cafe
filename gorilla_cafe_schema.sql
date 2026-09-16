-- ============================================================
-- Gorilla Cafe Database Schema
-- Database: gorilla_cafe
-- Engine: InnoDB | Charset: utf8mb4
-- ============================================================

CREATE TABLE IF NOT EXISTS `USER` (
  `user_id`          INT           NOT NULL AUTO_INCREMENT,
  `full_name`        VARCHAR(255)  NOT NULL,
  `role`             ENUM('admin','kitchen','waiter') NOT NULL,
  `phone`            VARCHAR(255)  DEFAULT NULL,
  `password_hash`    VARCHAR(255)  NOT NULL,
  `reset_token`      VARCHAR(255)  DEFAULT NULL,
  `reset_expires_at` DATETIME      DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_user_full_name` (`full_name`),
  KEY `idx_user_phone` (`phone`),
  KEY `idx_user_role` (`role`),
  KEY `idx_user_reset_token` (`reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `CATEGORY` (
  `category_id`   INT          NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `MENU_ITEM` (
  `item_id`              INT           NOT NULL AUTO_INCREMENT,
  `category_id`          INT           NOT NULL,
  `item_name`            VARCHAR(255)  NOT NULL,
  `description`          VARCHAR(255)  NOT NULL DEFAULT '',
  `price`                DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `availability_status`  ENUM('available','unavailable') NOT NULL DEFAULT 'available',
  PRIMARY KEY (`item_id`),
  KEY `idx_menu_item_category` (`category_id`),
  KEY `idx_menu_item_availability` (`availability_status`),
  CONSTRAINT `fk_menu_item_category` FOREIGN KEY (`category_id`)
    REFERENCES `CATEGORY` (`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `CAFE_TABLE` (
  `table_id`       INT          NOT NULL AUTO_INCREMENT,
  `table_number`   INT          NOT NULL,
  `qr_code_value`  VARCHAR(255) NOT NULL,
  PRIMARY KEY (`table_id`),
  UNIQUE KEY `uq_table_number` (`table_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ORDER` (
  `order_id`        INT           NOT NULL AUTO_INCREMENT,
  `table_id`        INT           NOT NULL,
  `customer_phone`  VARCHAR(255)  DEFAULT NULL,
  `order_status`    ENUM('received','preparing','ready','served','cancelled') NOT NULL DEFAULT 'received',
  `total_amount`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `order_time`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_updated_by` INT           DEFAULT NULL,
  `last_updated_at` DATETIME      DEFAULT NULL,
  `served_at`       DATETIME      DEFAULT NULL,
  PRIMARY KEY (`order_id`),
  KEY `idx_order_table` (`table_id`),
  KEY `idx_order_status` (`order_status`),
  KEY `idx_order_time` (`order_time`),
  KEY `idx_order_updated_by` (`last_updated_by`),
  CONSTRAINT `fk_order_table` FOREIGN KEY (`table_id`)
    REFERENCES `CAFE_TABLE` (`table_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_order_user` FOREIGN KEY (`last_updated_by`)
    REFERENCES `USER` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ORDER_ITEM` (
  `order_id`  INT           NOT NULL,
  `item_id`   INT           NOT NULL,
  `quantity`  INT           NOT NULL,
  `subtotal`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`order_id`, `item_id`),
  KEY `idx_order_item_item` (`item_id`),
  CONSTRAINT `fk_order_item_order` FOREIGN KEY (`order_id`)
    REFERENCES `ORDER` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_item_menu_item` FOREIGN KEY (`item_id`)
    REFERENCES `MENU_ITEM` (`item_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
