
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- shopimind
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `shopimind`;

CREATE TABLE `shopimind`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `api_id` VARCHAR(255),
    `api_password` VARCHAR(255),
    `real_time_synchronization` TINYINT(1),
    `nominative_reductions` TINYINT(1),
    `cumulative_vouchers` TINYINT(1),
    `out_of_stock_product_disabling` TINYINT(1),
    `script_tag` TINYINT(1),
    `is_connected` TINYINT(1),
    `log` TINYINT(1),
    `confirmed_statuses` LONGTEXT,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- shopimind_sync_status
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `shopimind_sync_status`;

CREATE TABLE `shopimind_sync_status`
(
    `id` BIGINT NOT NULL,
    `current_data_type` VARCHAR(50),
    `global_state` VARCHAR(50),
    `first_call` TIMESTAMP,
    `statuses` JSON,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- shopimind_sync_errors
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `shopimind_sync_errors`;

CREATE TABLE `shopimind_sync_errors`
(
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `id_shop_ask_syncs` BIGINT,
    `object_type` VARCHAR(50),
    `error_code` INTEGER,
    `error_message` JSON,
    `data` JSON,
    `timestamp` TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
