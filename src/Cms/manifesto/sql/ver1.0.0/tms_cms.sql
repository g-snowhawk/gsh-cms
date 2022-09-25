CREATE TABLE IF NOT EXISTS `table::site` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userkey` int unsigned DEFAULT NULL,
  `url` varchar(255) NOT NULL,
  `mobileurl` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `lang` varchar(20) DEFAULT 'ja',
  `encoding` varchar(20) DEFAULT 'UTF-8',
  `openpath` varchar(255) DEFAULT NULL,
  `mobilepath` varchar(255) DEFAULT NULL,
  `defaultpage` varchar(32) NOT NULL DEFAULT 'index',
  `defaultextension` varchar(5) NOT NULL DEFAULT '.html',
  `styledir` varchar(32) DEFAULT NULL,
  `uploaddir` varchar(32) DEFAULT NULL,
  `maskdir` varchar(4) DEFAULT '0777',
  `maskfile` varchar(4) DEFAULT '0644',
  `maskexec` varchar(4) DEFAULT '0755',
  `maxentry` int DEFAULT NULL,
  `maxcategory` int DEFAULT NULL,
  `maxrevision` int DEFAULT '0',
  `noroot` enum('0','1') NOT NULL DEFAULT '0',
  `type` enum('static','dynamic') NOT NULL DEFAULT 'static',
  `contract` date NOT NULL DEFAULT '1970-01-01',
  `expire` int NOT NULL DEFAULT '1',
  `runlevel` enum('normal','maintenance','emergency','stealth') NOT NULL DEFAULT 'normal',
  `announce` text,
  `create_date` datetime NOT NULL,
  `modify_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `table::template` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sitekey` int unsigned NOT NULL,
  `title` varchar(40) NOT NULL,
  `description` text,
  `sourcecode` text,
  `kind` int DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `identifier` int DEFAULT '0',
  `revision` int DEFAULT '0',
  `active` tinyint(1) DEFAULT '0',
  `create_date` datetime NOT NULL,
  `modify_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `IDENTIFIER` (`identifier`,`revision`),
  UNIQUE KEY `FILEPATH` (`sitekey`,`revision`,`path`),
  KEY `sitekey` (`sitekey`),
  CONSTRAINT `table::template_ibfk_1` FOREIGN KEY (`sitekey`) REFERENCES `table::site` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `table::category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sitekey` int unsigned NOT NULL,
  `userkey` int unsigned NOT NULL,
  `template` int unsigned DEFAULT NULL,
  `default_template` int unsigned DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  `filepath` varchar(511) DEFAULT NULL,
  `archive_format` text,
  `title` varchar(255) NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `description` text,
  `priority` int DEFAULT NULL,
  `inheritance` tinyint(1) NOT NULL DEFAULT '0',
  `reserved` enum('0','1') NOT NULL DEFAULT '0',
  `trash` enum('0','1') NOT NULL,
  `author_date` datetime DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `modify_date` datetime DEFAULT NULL,
  `lft` int unsigned DEFAULT NULL,
  `rgt` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `SITEKEY` (`sitekey`),
  KEY `NESTED_LEFT` (`lft`),
  KEY `NESTED_RIGHT` (`rgt`),
  KEY `TEMPLATE` (`template`),
  KEY `DEFAULT_TEMPLATE` (`default_template`),
  CONSTRAINT `table::category_ibfk_1` FOREIGN KEY (`sitekey`) REFERENCES `table::site` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `table::category_ibfk_2` FOREIGN KEY (`template`) REFERENCES `table::template` (`id`),
  CONSTRAINT `table::category_ibfk_3` FOREIGN KEY (`default_template`) REFERENCES `table::template` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `table::custom` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sitekey` int unsigned NOT NULL,
  `relkey` int NOT NULL,
  `kind` varchar(32) NOT NULL,
  `name` varchar(255) NOT NULL,
  `mime` varchar(255) DEFAULT NULL,
  `alternate` text,
  `data` text,
  `note` text,
  `option1` text,
  `option2` text,
  `option3` text,
  `sort` int DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `SITEKEY` (`sitekey`),
  UNIQUE KEY `IDENT` (`sitekey`,`relkey`,`kind`,`name`,`mime`),
  CONSTRAINT `table::custom_ibfk_1` FOREIGN KEY (`sitekey`) REFERENCES `table::site` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `table::entry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sitekey` int unsigned NOT NULL,
  `userkey` int unsigned NOT NULL,
  `template` int unsigned DEFAULT NULL,
  `category` int NOT NULL,
  `path` varchar(255) NOT NULL DEFAULT '',
  `filepath` varchar(511) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `description` text,
  `body` text,
  `identifier` int DEFAULT '0',
  `revision` int DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(32) DEFAULT NULL,
  `trash` enum('0','1') NOT NULL,
  `acl` int unsigned NOT NULL DEFAULT 0,
  `release_date` datetime DEFAULT NULL,
  `close_date` datetime DEFAULT NULL,
  `author_date` datetime DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `modify_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifier` (`identifier`,`revision`),
  KEY `sitekey` (`sitekey`,`category`,`path`),
  KEY `REVISION` (`revision`),
  KEY `template` (`template`),
  KEY `ACL` (`acl`),
  CONSTRAINT `table::entry_ibfk_1` FOREIGN KEY (`sitekey`) REFERENCES `table::site` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `table::entry_ibfk_2` FOREIGN KEY (`template`) REFERENCES `table::template` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `table::section_level_key` (
  `level` int NOT NULL DEFAULT '2',
  PRIMARY KEY (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `table::section` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sitekey` int unsigned DEFAULT NULL,
  `entrykey` int unsigned NOT NULL,
  `level` int DEFAULT '2',
  `title` varchar(255) NOT NULL,
  `body` text,
  `identifier` int DEFAULT '0',
  `revision` int DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(32) DEFAULT NULL,
  `author_date` datetime DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `modify_date` datetime DEFAULT NULL,
  `lft` int unsigned DEFAULT NULL,
  `rgt` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifire` (`identifier`,`revision`),
  KEY `SITEKEY` (`sitekey`),
  KEY `ENTRYKEY` (`entrykey`),
  KEY `NESTED_LEFT` (`lft`),
  KEY `NESTED_RIGHT` (`rgt`),
  KEY `level` (`level`),
  CONSTRAINT `table::section_ibfk_1` FOREIGN KEY (`sitekey`) REFERENCES `table::site` (`id`),
  CONSTRAINT `table::section_ibfk_2` FOREIGN KEY (`entrykey`) REFERENCES `table::entry` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `table::section_ibfk_3` FOREIGN KEY (`level`) REFERENCES `table::section_level_key` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `table::relation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `entrykey` int unsigned NOT NULL,
  `relkey` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifire` (`entrykey`,`relkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

START TRANSACTION;
LOCK TABLES `table::section_level_key` WRITE;
INSERT INTO `table::section_level_key` (`level`) VALUES (2) ON DUPLICATE KEY UPDATE `level` = '2';
INSERT INTO `table::section_level_key` (`level`) VALUES (3) ON DUPLICATE KEY UPDATE `level` = '3';
INSERT INTO `table::section_level_key` (`level`) VALUES (4) ON DUPLICATE KEY UPDATE `level` = '4';
INSERT INTO `table::section_level_key` (`level`) VALUES (5) ON DUPLICATE KEY UPDATE `level` = '5';
INSERT INTO `table::section_level_key` (`level`) VALUES (6) ON DUPLICATE KEY UPDATE `level` = '6';
UNLOCK TABLES;
COMMIT;
