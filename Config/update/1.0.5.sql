SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE `shopimind` ADD `confirmed_statuses` LONGTEXT AFTER `is_connected`;

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