<?php

use Phinx\Migration\AbstractMigration;

class PaymentsModuleInitMigration extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `payment_gateways` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `sorting` int(11) NOT NULL DEFAULT '10',
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `is_recurrent` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `sorting` (`sorting`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `variable_symbol` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `additional_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `additional_type` varchar(255) DEFAULT NULL,
  `payment_gateway_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `subscription_type_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'form',
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `referer` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `note` text,
  `ip` varchar(255) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `subscription_start_at` datetime DEFAULT NULL,
  `subscription_end_at` datetime DEFAULT NULL,
  `upgrade_type` varchar(255) DEFAULT NULL,
  `address_id` int(11) DEFAULT NULL,
  `recurrent_charge` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `subscription_id` (`subscription_id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  KEY `variable_symbol` (`variable_symbol`),
  KEY `payment_type_id` (`payment_gateway_id`),
  KEY `subscription_type_id` (`subscription_type_id`),
  KEY `paid_at` (`paid_at`),
  KEY `modified_at` (`modified_at`),
  KEY `address_id` (`address_id`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`subscription_type_id`) REFERENCES `subscription_types` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE NO ACTION,
  CONSTRAINT `payments_ibfk_4` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON UPDATE NO ACTION,
  CONSTRAINT `payments_ibfk_8` FOREIGN KEY (`payment_gateway_id`) REFERENCES `payment_gateways` (`id`),
  CONSTRAINT `payments_ibfk_9` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `payment_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'subscription_type',
  `subscription_type_id` int(11) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `count` int(11) NOT NULL DEFAULT '1',
  `amount` decimal(10,2) NOT NULL,
  `vat` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  KEY `subscription_type_id` (`subscription_type_id`),
  CONSTRAINT `payment_items_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  CONSTRAINT `payment_items_ibfk_2` FOREIGN KEY (`subscription_type_id`) REFERENCES `subscription_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `payment_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `status` varchar(255) NOT NULL,
  `message` text,
  `source_url` text NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `payment_meta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_id` (`payment_id`,`key`),
  CONSTRAINT `payment_meta_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `recurrent_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cid` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `payment_gateway_id` int(11) NOT NULL,
  `charge_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `retries` int(11) NOT NULL DEFAULT '3',
  `status` varchar(255) DEFAULT NULL,
  `approval` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `subscription_type_id` int(11) NOT NULL,
  `next_subscription_type_id` int(11) DEFAULT NULL,
  `parent_payment_id` int(11) DEFAULT NULL,
  `state` varchar(255) NOT NULL DEFAULT 'active',
  `note` varchar(255) DEFAULT NULL,
  `custom_amount` float DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `charge_at` (`charge_at`),
  KEY `subscription_type_id` (`subscription_type_id`),
  KEY `user_id` (`user_id`),
  KEY `payment_type_id` (`payment_gateway_id`),
  KEY `parent_payment_id` (`parent_payment_id`),
  KEY `created_at` (`created_at`),
  KEY `next_subscription_type_id` (`next_subscription_type_id`),
  KEY `payment_id` (`payment_id`),
  CONSTRAINT `recurrent_payments_ibfk_1` FOREIGN KEY (`subscription_type_id`) REFERENCES `subscription_types` (`id`),
  CONSTRAINT `recurrent_payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `recurrent_payments_ibfk_4` FOREIGN KEY (`parent_payment_id`) REFERENCES `payments` (`id`) ON UPDATE NO ACTION,
  CONSTRAINT `recurrent_payments_ibfk_5` FOREIGN KEY (`payment_gateway_id`) REFERENCES `payment_gateways` (`id`),
  CONSTRAINT `recurrent_payments_ibfk_6` FOREIGN KEY (`next_subscription_type_id`) REFERENCES `subscription_types` (`id`),
  CONSTRAINT `recurrent_payments_ibfk_7` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `parsed_mail_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `delivered_at` datetime NOT NULL,
  `variable_symbol` varchar(255) DEFAULT NULL,
  `amount` float DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `state` varchar(255) NOT NULL,
  `message` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`),
  KEY `payment_id` (`payment_id`),
  KEY `variable_symbol` (`variable_symbol`),
  KEY `state` (`state`),
  CONSTRAINT `parsed_mail_logs_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $this->execute($sql);
    }

    public function down()
    {
        // TODO: [refactoring] add down migrations for module init migrations
        $this->output->writeln('Down migration is not available.');
    }
}
