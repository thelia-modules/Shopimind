
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
    `cache_control` TINYINT(1),
    `nominative_reductions` TINYINT(1),
    `cumulative_vouchers` TINYINT(1),
    `out_of_stock_product_disabling` TINYINT(1),
    `email_product_image_format` VARCHAR(255),
    `synchronize_newsletter_subscribers` TINYINT(1),
    `script_tag` TINYINT(1),
    `is_connected` TINYINT(1),
    `log` TINYINT(1),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
