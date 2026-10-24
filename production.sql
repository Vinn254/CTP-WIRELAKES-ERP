-- CTP-WireLakes production database
-- Generated 2026-10-24 11:01:29 by `php artisan db:export-production`
-- Structure for every module, plus the counties, fees, training catalogue and website content.
-- No members, no staff accounts, no test records. Import into an empty database.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

-- ---- account_invites ----
DROP TABLE IF EXISTS `account_invites`;
CREATE TABLE `account_invites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_type` enum('ctp','fundi') COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','accepted','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `accepted_user_id` bigint unsigned DEFAULT NULL,
  `invited_by` bigint unsigned DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_invites_member_type_member_id_unique` (`member_type`,`member_id`),
  UNIQUE KEY `account_invites_token_unique` (`token`),
  KEY `account_invites_accepted_user_id_foreign` (`accepted_user_id`),
  KEY `account_invites_invited_by_foreign` (`invited_by`),
  CONSTRAINT `account_invites_accepted_user_id_foreign` FOREIGN KEY (`accepted_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `account_invites_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- activity_log ----
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `actor_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `subject_label` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_log_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `activity_log_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `activity_log_created_at_index` (`created_at`),
  CONSTRAINT `activity_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- beneficiaries ----
DROP TABLE IF EXISTS `beneficiaries`;
CREATE TABLE `beneficiaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `community_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `age` tinyint unsigned DEFAULT NULL,
  `gender` enum('male','female','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('student','youth','household','business','organisation') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'youth',
  `benefited_on` date NOT NULL,
  `id_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_consent` tinyint(1) NOT NULL DEFAULT '0',
  `created_by_ctp` bigint unsigned DEFAULT NULL,
  `created_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `beneficiaries_project_id_foreign` (`project_id`),
  KEY `beneficiaries_created_by_ctp_foreign` (`created_by_ctp`),
  KEY `beneficiaries_created_by_user_foreign` (`created_by_user`),
  KEY `beneficiaries_community_id_type_index` (`community_id`,`type`),
  CONSTRAINT `beneficiaries_community_id_foreign` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `beneficiaries_created_by_ctp_foreign` FOREIGN KEY (`created_by_ctp`) REFERENCES `ctp_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `beneficiaries_created_by_user_foreign` FOREIGN KEY (`created_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `beneficiaries_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- boq_items ----
DROP TABLE IF EXISTS `boq_items`;
CREATE TABLE `boq_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `drawing_ref` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trade` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'each',
  `quantity` decimal(12,3) NOT NULL DEFAULT '0.000',
  `unit_cost_ksh` decimal(14,2) NOT NULL DEFAULT '0.00',
  `position` int unsigned NOT NULL DEFAULT '0',
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'design',
  `variation_order_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'live',
  `withdraw_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `withdrawn_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `boq_items_withdrawn_by_user_foreign` (`withdrawn_by_user`),
  KEY `boq_items_project_id_position_index` (`project_id`,`position`),
  CONSTRAINT `boq_items_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `boq_items_withdrawn_by_user_foreign` FOREIGN KEY (`withdrawn_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- budget_lines ----
DROP TABLE IF EXISTS `budget_lines`;
CREATE TABLE `budget_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fiscal_year` smallint unsigned NOT NULL,
  `category` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_ksh` decimal(14,2) NOT NULL DEFAULT '0.00',
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `set_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `budget_lines_fiscal_year_category_unique` (`fiscal_year`,`category`),
  KEY `budget_lines_set_by_user_foreign` (`set_by_user`),
  CONSTRAINT `budget_lines_set_by_user_foreign` FOREIGN KEY (`set_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- cache ----
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- cache_locks ----
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- certifications ----
DROP TABLE IF EXISTS `certifications`;
CREATE TABLE `certifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `enrollment_id` bigint unsigned NOT NULL,
  `certificate_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theory_score` decimal(5,2) DEFAULT NULL,
  `practical_score` decimal(5,2) DEFAULT NULL,
  `attendance_pct` decimal(5,2) DEFAULT NULL,
  `status` enum('recommended','approved','declined','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'recommended',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `recommended_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_on` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `certifications_enrollment_id_unique` (`enrollment_id`),
  UNIQUE KEY `certifications_certificate_code_unique` (`certificate_code`),
  KEY `certifications_recommended_by_foreign` (`recommended_by`),
  KEY `certifications_approved_by_foreign` (`approved_by`),
  CONSTRAINT `certifications_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `certifications_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certifications_recommended_by_foreign` FOREIGN KEY (`recommended_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- cohorts ----
DROP TABLE IF EXISTS `cohorts`;
CREATE TABLE `cohorts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `training_id` bigint unsigned NOT NULL,
  `venue_id` bigint unsigned DEFAULT NULL,
  `trainer_id` bigint unsigned DEFAULT NULL,
  `community_id` bigint unsigned DEFAULT NULL,
  `county` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_county` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ward` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `academic_year` smallint unsigned DEFAULT NULL,
  `term` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enrol_closes_on` date DEFAULT NULL,
  `seats` smallint unsigned NOT NULL DEFAULT '0',
  `theory_hours` smallint unsigned NOT NULL DEFAULT '0',
  `practical_hours` smallint unsigned NOT NULL DEFAULT '0',
  `fee_ksh` decimal(10,2) NOT NULL DEFAULT '0.00',
  `summary` text COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','open','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cohorts_reference_unique` (`reference`),
  KEY `cohorts_venue_id_foreign` (`venue_id`),
  KEY `cohorts_community_id_foreign` (`community_id`),
  KEY `cohorts_trainer_id_foreign` (`trainer_id`),
  KEY `cohorts_training_id_foreign` (`training_id`),
  KEY `cohorts_academic_year_term_index` (`academic_year`,`term`),
  CONSTRAINT `cohorts_community_id_foreign` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cohorts_trainer_id_foreign` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cohorts_training_id_foreign` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `cohorts_venue_id_foreign` FOREIGN KEY (`venue_id`) REFERENCES `training_venues` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- communities ----
DROP TABLE IF EXISTS `communities`;
CREATE TABLE `communities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `community_code` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `county` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_county` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ward` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `village` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_lat` decimal(10,7) DEFAULT NULL,
  `gps_lng` decimal(10,7) DEFAULT NULL,
  `population` int unsigned DEFAULT NULL,
  `households` int unsigned DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending_review','active','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_review',
  `rejection_reason` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_ctp` bigint unsigned DEFAULT NULL,
  `created_by_user` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `communities_community_code_unique` (`community_code`),
  KEY `communities_created_by_ctp_foreign` (`created_by_ctp`),
  KEY `communities_created_by_user_foreign` (`created_by_user`),
  KEY `communities_approved_by_foreign` (`approved_by`),
  CONSTRAINT `communities_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `communities_created_by_ctp_foreign` FOREIGN KEY (`created_by_ctp`) REFERENCES `ctp_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `communities_created_by_user_foreign` FOREIGN KEY (`created_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- community_ctp ----
DROP TABLE IF EXISTS `community_ctp`;
CREATE TABLE `community_ctp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `community_id` bigint unsigned NOT NULL,
  `ctp_id` bigint unsigned NOT NULL,
  `role` enum('lead','mobilizer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mobilizer',
  `assigned_by` bigint unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `community_ctp_community_id_ctp_id_unique` (`community_id`,`ctp_id`),
  KEY `community_ctp_ctp_id_foreign` (`ctp_id`),
  KEY `community_ctp_assigned_by_foreign` (`assigned_by`),
  CONSTRAINT `community_ctp_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `community_ctp_community_id_foreign` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `community_ctp_ctp_id_foreign` FOREIGN KEY (`ctp_id`) REFERENCES `ctp_registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- contact_messages ----
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- content_entries ----
DROP TABLE IF EXISTS `content_entries`;
CREATE TABLE `content_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `section_id` bigint unsigned NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `content_entries_section_id_foreign` (`section_id`),
  CONSTRAINT `content_entries_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `content_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- rows: 52
INSERT INTO `content_entries` (`id`, `section_id`, `sort_order`) VALUES
  ('52', '27', '0'),
  ('53', '28', '0'),
  ('54', '29', '0'),
  ('55', '30', '0'),
  ('56', '31', '0'),
  ('57', '31', '1'),
  ('58', '31', '2'),
  ('59', '32', '0'),
  ('60', '33', '0'),
  ('61', '33', '1'),
  ('62', '33', '2'),
  ('63', '33', '3'),
  ('64', '33', '4'),
  ('65', '33', '5'),
  ('66', '34', '0'),
  ('67', '35', '0'),
  ('68', '36', '0'),
  ('69', '37', '0'),
  ('70', '37', '1'),
  ('71', '37', '2'),
  ('72', '38', '0'),
  ('73', '39', '0'),
  ('74', '40', '0'),
  ('75', '40', '1'),
  ('76', '40', '2'),
  ('77', '40', '3'),
  ('78', '40', '4'),
  ('79', '40', '5'),
  ('80', '41', '0'),
  ('81', '42', '0'),
  ('82', '42', '1'),
  ('83', '42', '2'),
  ('84', '42', '3'),
  ('85', '42', '4'),
  ('86', '42', '5'),
  ('87', '42', '6'),
  ('88', '42', '7'),
  ('89', '43', '0'),
  ('90', '44', '0'),
  ('91', '45', '0'),
  ('92', '46', '0'),
  ('93', '47', '0'),
  ('94', '48', '0'),
  ('95', '49', '0'),
  ('96', '50', '0'),
  ('97', '51', '0'),
  ('98', '51', '1'),
  ('99', '52', '0'),
  ('100', '52', '1'),
  ('101', '52', '2'),
  ('102', '52', '3'),
  ('103', '53', '0');

-- ---- content_fields ----
DROP TABLE IF EXISTS `content_fields`;
CREATE TABLE `content_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `section_id` bigint unsigned NOT NULL,
  `key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('text','textarea','image','url','price') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_fields_section_id_key_unique` (`section_id`,`key`),
  CONSTRAINT `content_fields_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `content_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- rows: 84
INSERT INTO `content_fields` (`id`, `section_id`, `key`, `label`, `type`, `sort_order`) VALUES
  ('81', '27', 'logo', 'Site logo', 'image', '0'),
  ('82', '27', 'brand_name', 'Brand name', 'text', '1'),
  ('83', '27', 'theme_green', 'Brand colour (dark)', 'text', '2'),
  ('84', '27', 'theme_lime', 'Accent colour (lime)', 'text', '3'),
  ('85', '28', 'email', 'Email', 'text', '0'),
  ('86', '28', 'phone', 'Phone', 'text', '1'),
  ('87', '28', 'address', 'Address', 'textarea', '2'),
  ('88', '29', 'about_text', 'About blurb', 'textarea', '0'),
  ('89', '29', 'fine_print', 'Copyright line', 'text', '1'),
  ('90', '30', 'kicker', 'Kicker badge', 'text', '0'),
  ('91', '30', 'title', 'Headline', 'textarea', '1'),
  ('92', '30', 'lede', 'Intro paragraph', 'textarea', '2'),
  ('93', '30', 'cta1_label', 'Button 1 label', 'text', '3'),
  ('94', '30', 'cta1_url', 'Button 1 link', 'text', '4'),
  ('95', '30', 'cta2_label', 'Button 2 label', 'text', '5'),
  ('96', '30', 'cta2_url', 'Button 2 link', 'text', '6'),
  ('97', '31', 'image', 'Photo', 'image', '0'),
  ('98', '31', 'caption', 'Caption', 'text', '1'),
  ('99', '32', 'title', 'Title', 'text', '0'),
  ('100', '32', 'sub', 'Subtitle', 'text', '1'),
  ('101', '32', 'cta_label', 'Button label', 'text', '2'),
  ('102', '32', 'cta_url', 'Button link', 'text', '3'),
  ('103', '33', 'icon', 'Icon (emoji)', 'text', '0'),
  ('104', '33', 'title', 'Title', 'text', '1'),
  ('105', '33', 'desc', 'Description', 'textarea', '2'),
  ('106', '34', 'title', 'Title', 'text', '0'),
  ('107', '34', 'text', 'Text', 'text', '1'),
  ('108', '34', 'cta_label', 'Button label', 'text', '2'),
  ('109', '35', 'title', 'Title', 'text', '0'),
  ('110', '36', 'tag', 'Tag', 'text', '0'),
  ('111', '36', 'title', 'Title', 'text', '1'),
  ('112', '36', 'body1', 'Paragraph 1', 'textarea', '2'),
  ('113', '36', 'body2', 'Paragraph 2', 'textarea', '3'),
  ('114', '37', 'title', 'Title', 'text', '0'),
  ('115', '37', 'desc', 'Text', 'textarea', '1'),
  ('116', '38', 'title', 'Title', 'text', '0'),
  ('117', '38', 'sub', 'Subtitle', 'textarea', '1'),
  ('118', '38', 'card_icon', 'Card icon (emoji)', 'text', '2'),
  ('119', '38', 'card_title', 'Card title', 'text', '3'),
  ('120', '38', 'card_text', 'Card text', 'textarea', '4'),
  ('121', '39', 'tag', 'Tag', 'text', '0'),
  ('122', '39', 'title', 'Title', 'text', '1'),
  ('123', '39', 'sub', 'Subtitle', 'textarea', '2'),
  ('124', '40', 'icon', 'Icon (emoji)', 'text', '0'),
  ('125', '40', 'title', 'Title', 'text', '1'),
  ('126', '40', 'desc', 'Description', 'textarea', '2'),
  ('127', '40', 'cta_label', 'Link label', 'text', '3'),
  ('128', '40', 'cta_url', 'Link URL', 'text', '4'),
  ('129', '41', 'tag', 'Tag', 'text', '0'),
  ('130', '41', 'title', 'Title', 'text', '1'),
  ('131', '42', 'question', 'Question', 'text', '0'),
  ('132', '42', 'answer', 'Answer', 'textarea', '1'),
  ('133', '43', 'tag', 'Tag', 'text', '0'),
  ('134', '43', 'title', 'Title', 'text', '1'),
  ('135', '43', 'sub', 'Subtitle', 'textarea', '2'),
  ('136', '43', 'help', 'Payment note (below form copy)', 'textarea', '3'),
  ('137', '44', 'tag', 'Tag', 'text', '0'),
  ('138', '44', 'title', 'Title', 'text', '1'),
  ('139', '45', 'icon', 'Icon (emoji)', 'text', '0'),
  ('140', '45', 'title', 'Title', 'text', '1'),
  ('141', '45', 'text', 'Text', 'textarea', '2'),
  ('142', '46', 'title', 'Title', 'text', '0'),
  ('143', '46', 'sub', 'Subtitle', 'textarea', '1'),
  ('144', '47', 'tag', 'Tag', 'text', '0'),
  ('145', '47', 'title', 'Title', 'text', '1'),
  ('146', '48', 'card_title', 'Card title', 'text', '0'),
  ('147', '48', 'address', 'Address lines', 'textarea', '1'),
  ('148', '48', 'phone', 'Phone', 'text', '2'),
  ('149', '48', 'email', 'Email', 'text', '3'),
  ('150', '48', 'faq_note', 'FAQ link note', 'text', '4'),
  ('151', '49', 'title', 'Title', 'text', '0'),
  ('152', '50', 'fee_ctp', 'CTP fee (KSh)', 'price', '0'),
  ('153', '50', 'fee_fundi', 'Fundi fee (KSh)', 'price', '1'),
  ('154', '50', 'fee_hirer', 'Hirer fee (KSh — 0 = free)', 'price', '2'),
  ('155', '50', 'payment_note', 'Payment note on success page', 'textarea', '3'),
  ('156', '51', 'code', 'Code (do not change once live)', 'text', '0'),
  ('157', '51', 'label', 'Label', 'text', '1'),
  ('158', '51', 'desc', 'Help text', 'textarea', '2'),
  ('159', '52', 'code', 'Code (do not change once live)', 'text', '0'),
  ('160', '52', 'label', 'Tier name', 'text', '1'),
  ('161', '52', 'desc', 'Who it is for', 'textarea', '2'),
  ('163', '53', 'hero_photo', 'Hero background photo', 'image', '0'),
  ('164', '53', 'page_photo', 'Whole-site background photo', 'image', '1'),
  ('165', '53', 'photo_darkness', 'Photo darkness over hero (0.3 – 0.85)', 'text', '2');

-- ---- content_sections ----
DROP TABLE IF EXISTS `content_sections`;
CREATE TABLE `content_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `page` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` enum('single','repeater') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_sections_page_key_unique` (`page`,`key`),
  KEY `content_sections_page_index` (`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- rows: 27
INSERT INTO `content_sections` (`id`, `page`, `key`, `name`, `kind`, `sort_order`, `is_visible`, `created_at`, `updated_at`) VALUES
  ('27', 'site', 'branding', 'Branding & logo', 'single', '1', '1', '2026-10-12 10:38:49', '2026-10-12 11:13:58'),
  ('28', 'site', 'contact', 'Contact details', 'single', '2', '1', '2026-10-12 10:38:49', '2026-10-12 11:13:58'),
  ('29', 'site', 'footer', 'Footer', 'single', '3', '1', '2026-10-12 10:38:49', '2026-10-12 11:13:58'),
  ('30', 'home', 'hero', 'Hero (top banner)', 'single', '0', '1', '2026-10-12 10:38:50', '2026-10-19 06:52:55'),
  ('31', 'home', 'hero_slides', 'Hero sliding images', 'repeater', '0', '1', '2026-10-12 10:38:50', '2026-10-19 06:52:55'),
  ('32', 'home', 'programme', 'What the programme does — heading', 'single', '0', '1', '2026-10-12 10:38:51', '2026-10-19 06:52:55'),
  ('33', 'home', 'programme_cards', 'Programme cards', 'repeater', '0', '1', '2026-10-12 10:38:51', '2026-10-19 06:52:55'),
  ('34', 'home', 'donate_strip', 'Donate strip', 'single', '0', '1', '2026-10-12 10:38:51', '2026-10-19 06:52:55'),
  ('35', 'home', 'partners_heading', 'Partners heading (home)', 'single', '0', '1', '2026-10-12 10:38:51', '2026-10-19 06:52:55'),
  ('36', 'about', 'intro', 'Origin story', 'single', '0', '1', '2026-10-12 10:38:51', '2026-10-12 10:38:51'),
  ('37', 'about', 'pillars', 'Vision / Mission / Values cards', 'repeater', '0', '1', '2026-10-12 10:38:52', '2026-10-12 10:38:52'),
  ('38', 'about', 'operate', 'Where we operate', 'single', '0', '1', '2026-10-12 10:38:52', '2026-10-12 10:38:52'),
  ('39', 'what-we-do', 'intro', 'Page heading', 'single', '0', '1', '2026-10-12 10:38:52', '2026-10-12 10:38:52'),
  ('40', 'what-we-do', 'cards', 'Work-area cards', 'repeater', '0', '1', '2026-10-12 10:38:52', '2026-10-12 10:38:52'),
  ('41', 'faq', 'intro', 'Page heading', 'single', '0', '1', '2026-10-12 10:38:53', '2026-10-12 10:38:53'),
  ('42', 'faq', 'items', 'Questions & answers', 'repeater', '0', '1', '2026-10-12 10:38:53', '2026-10-12 10:38:53'),
  ('43', 'donate', 'intro', 'Page heading', 'single', '0', '1', '2026-10-12 10:38:54', '2026-10-12 10:38:54'),
  ('44', 'partners', 'intro', 'Page heading', 'single', '0', '1', '2026-10-12 10:38:54', '2026-10-12 10:38:54'),
  ('45', 'partners', 'empty_state', 'Empty partner wall message', 'single', '0', '1', '2026-10-12 10:38:54', '2026-10-12 10:38:54'),
  ('46', 'partners', 'form', 'Partner form heading', 'single', '0', '1', '2026-10-12 10:38:54', '2026-10-12 10:38:54'),
  ('47', 'contact', 'intro', 'Page heading', 'single', '0', '1', '2026-10-12 10:38:54', '2026-10-12 10:38:54'),
  ('48', 'contact', 'office', 'Office card', 'single', '0', '1', '2026-10-12 10:38:54', '2026-10-12 10:38:54'),
  ('49', 'contact', 'form', 'Message form heading', 'single', '0', '1', '2026-10-12 10:38:55', '2026-10-12 10:38:55'),
  ('50', 'membership', 'fees', 'Membership fees', 'single', '0', '1', '2026-10-12 10:38:55', '2026-10-12 10:38:55'),
  ('51', 'membership', 'ctp_tracks', 'CTP training tracks', 'repeater', '0', '1', '2026-10-12 10:38:55', '2026-10-12 10:38:55'),
  ('52', 'membership', 'fundi_tiers', 'E-Fundi tiers', 'repeater', '0', '1', '2026-10-12 10:38:55', '2026-10-12 10:38:55'),
  ('53', 'site', 'backgrounds', 'Background photos', 'single', '0', '1', '2026-10-12 11:16:38', '2026-10-12 11:16:38');

-- ---- content_values ----
DROP TABLE IF EXISTS `content_values`;
CREATE TABLE `content_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` bigint unsigned NOT NULL,
  `field_id` bigint unsigned NOT NULL,
  `text_value` text COLLATE utf8mb4_unicode_ci,
  `image_path` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_values_entry_id_field_id_unique` (`entry_id`,`field_id`),
  KEY `content_values_field_id_foreign` (`field_id`),
  CONSTRAINT `content_values_entry_id_foreign` FOREIGN KEY (`entry_id`) REFERENCES `content_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `content_values_field_id_foreign` FOREIGN KEY (`field_id`) REFERENCES `content_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- rows: 156
INSERT INTO `content_values` (`id`, `entry_id`, `field_id`, `text_value`, `image_path`) VALUES
  ('150', '52', '82', 'CTP-WireLakes', ''),
  ('151', '52', '83', '#066722', ''),
  ('152', '52', '84', '#4cb614', ''),
  ('153', '53', '85', 'info@ctp-wirelakes.ke', ''),
  ('154', '53', '86', '+254 700 000 000', ''),
  ('155', '53', '87', 'Kisumu, Kenya', ''),
  ('156', '54', '88', 'Community Technology Promoters — E-Fundis, Training & Innovation. From a community need, we create trained people, certified fundis, completed projects and lasting jobs.', ''),
  ('157', '54', '89', '2026 CTP-WireLakes Programme', ''),
  ('158', '55', '90', 'From a community need', ''),
  ('159', '55', '91', 'We train and certify people — then give the projects to our Fundis.', ''),
  ('160', '55', '92', 'Community-led', ''),
  ('161', '55', '93', 'Become a CTP', ''),
  ('162', '55', '94', '/register/ctp', ''),
  ('163', '55', '95', 'Become a Fundi', ''),
  ('164', '55', '96', '/register/fundi', ''),
  ('165', '56', '98', 'Training', ''),
  ('166', '57', '98', 'E-Fundis', ''),
  ('167', '58', '98', 'Projects', ''),
  ('168', '59', '99', 'What the programme does', ''),
  ('169', '59', '100', 'Six connected areas of work, run as one ecosystem.', ''),
  ('170', '59', '101', 'See all six areas of work', ''),
  ('171', '59', '102', '/what-we-do', ''),
  ('172', '60', '103', '🎓', ''),
  ('173', '60', '104', 'Training', ''),
  ('174', '60', '105', 'Community-based technical training with certification recognised across the industry.', ''),
  ('175', '61', '103', '🛠️', ''),
  ('176', '61', '104', 'E-Fundi Marketplace', ''),
  ('177', '61', '105', 'Verified fundis — intern, junior and senior tiers — matched to real projects.', ''),
  ('178', '62', '103', '🏗️', ''),
  ('179', '62', '104', 'Projects', ''),
  ('180', '62', '105', 'Schools, businesses and communities post projects; the right team is assigned.', ''),
  ('181', '63', '103', '♻️', ''),
  ('182', '63', '104', 'E-Waste', ''),
  ('183', '63', '105', 'Collection, repair, refurbishment and safe disposal — turning waste into work.', ''),
  ('184', '64', '103', '💼', ''),
  ('185', '64', '104', 'Entrepreneurship', ''),
  ('186', '64', '105', 'Business support for fundis and CTPs so skills become enterprises.', ''),
  ('187', '65', '103', '🌱', ''),
  ('188', '65', '104', 'Green Economy', ''),
  ('189', '65', '105', 'Solar, green tech and community technology serving a sustainable Kenya.', ''),
  ('190', '66', '106', 'Every donation goes directly to programme work', ''),
  ('191', '66', '107', 'Training fundis, certifying skills and equipping communities across Kenya.', ''),
  ('192', '66', '108', 'Donate now', ''),
  ('193', '67', '109', 'Our partners', ''),
  ('194', '68', '110', 'About the programme', ''),
  ('195', '68', '111', 'Our origin', ''),
  ('196', '68', '112', 'CTP-WireLakes (Community Technology Promoters) started in Kisumu, Kenya, with a simple idea: every community has needs — and the same community has people who can be trained, certified and deployed to meet those needs. What began as a skills initiative for school leavers and junior graduates has grown into a multi-county programme spanning training, the E-Fundi workforce, projects, construction, technology services, e-waste and the green economy.', ''),
  ('197', '68', '113', 'The programme is built around the CTP — the Community Technology Promoter — an on-the-ground agent who is simultaneously a trainee, a mobilizer and a community agent.', ''),
  ('198', '69', '114', '🎯 Vision', ''),
  ('199', '69', '115', 'From a community need, the CTP system creates trained people, certified fundis, completed projects, refurbished e-waste, new businesses and lasting jobs — then loops the impact back into the community.', ''),
  ('200', '70', '114', '🚀 Mission', ''),
  ('201', '70', '115', 'To connect people with technical training, skilled technicians (E-Fundis), employment opportunities, enterprise development, digital services, e-waste management and community technology projects at scale across Kenya.', ''),
  ('202', '71', '114', '💎 Values', ''),
  ('203', '71', '115', 'Community first · Skills with proof · Integrity in every certification · Green by design · Jobs that stay local · Transparency to donors and partners.', ''),
  ('204', '72', '116', 'Where we operate', ''),
  ('205', '72', '117', 'CTP-WireLakes works county-by-county across Kenya, starting from the Lake Victoria region and expanding through CTP mobilization. The interactive Kenya-wide impact map arrives with the M&E / GIS department (Module 12).', ''),
  ('206', '72', '118', '🗺️', ''),
  ('207', '72', '119', 'Kisumu · Kenya', ''),
  ('208', '72', '120', 'Head office and programme birthplace. CTPs active across wards, with county expansion driven by Modules 2 and 3.', ''),
  ('209', '73', '121', 'What we do', ''),
  ('210', '73', '122', 'Six areas of work, one connected ecosystem', ''),
  ('211', '73', '123', 'Train and certify → deploy to projects → build and maintain → recycle → start businesses → employ the community. Then the loop repeats.', ''),
  ('212', '74', '124', '🎓', ''),
  ('213', '74', '125', 'Training & Certification', ''),
  ('214', '74', '126', 'Courses and cohorts at community sites and partner schools — theory, practical work, assessment and a certificate with a verifiable ID.', ''),
  ('215', '74', '127', 'Join as a CTP trainee', ''),
  ('216', '74', '128', '/register/ctp', ''),
  ('217', '75', '124', '🛠️', ''),
  ('218', '75', '125', 'E-Fundi Marketplace', ''),
  ('219', '75', '126', 'Verified skilled workers — electricians, masons, ICT and solar technicians — matched to real projects by specialisation and county.', ''),
  ('220', '75', '127', 'Register as a Fundi', ''),
  ('221', '75', '128', '/register/fundi', ''),
  ('222', '76', '124', '🏗️', ''),
  ('223', '76', '125', 'Projects & Construction', ''),
  ('224', '76', '126', 'Hirers post projects — from computer labs to roofs and fences. We assess, quote, assign, execute and sign off.', ''),
  ('225', '76', '127', 'Post a Project', ''),
  ('226', '76', '128', '/register/hirer', ''),
  ('227', '77', '124', '♻️', ''),
  ('228', '77', '125', 'E-Waste Management', ''),
  ('229', '77', '126', 'Collection, weighing, sorting, testing — then repair, refurbish, recycle or dispose safely. Refurbished gear returns to schools and communities.', ''),
  ('230', '77', '127', 'Partner with us', ''),
  ('231', '77', '128', '/contact', ''),
  ('232', '78', '124', '💼', ''),
  ('233', '78', '125', 'Entrepreneurship', ''),
  ('234', '78', '126', 'Fundis and CTPs get business support, toolkits and first contracts so a skill becomes an enterprise that hires locally.', ''),
  ('235', '78', '127', 'Fund a toolkit', ''),
  ('236', '78', '128', '/donate', ''),
  ('237', '79', '124', '🌱', ''),
  ('238', '79', '125', 'Green Economy & Technology', ''),
  ('239', '79', '126', 'Solar, ICT, networking, robotics, programming and AI services delivered by our own trained workforce.', ''),
  ('240', '79', '127', 'Learn more', ''),
  ('241', '79', '128', '/about', ''),
  ('242', '80', '129', 'FAQ', ''),
  ('243', '80', '130', 'Frequently asked questions', ''),
  ('244', '81', '131', 'What is a CTP?', ''),
  ('245', '81', '132', 'A Community Technology Promoter — the on-the-ground agent who is simultaneously a trainee (learning a skill), a mobilizer (bringing people into the programme) and a community agent (identifying needs and registering communities).', ''),
  ('246', '82', '131', 'Who can become a CTP?', ''),
  ('247', '82', '132', 'School leavers (training track) and junior graduates wanting practical experience (junior practical-check track). There is a one-time membership fee set by the programme.', ''),
  ('248', '83', '131', 'What is the difference between a Fundi-Intern, an Attachment applicant, a Junior Fundi and a Senior Fundi?', ''),
  ('249', '83', '132', 'Fundi-Intern: graduated, no experience, seeking an internship. Attachment applicant: still in school and not yet graduated — only they can apply for industrial attachment. Junior Fundi: recent graduate who already has internship experience. Senior Fundi: long industry experience, fully eligible to lead projects and mentor.', ''),
  ('250', '84', '131', 'Do I have to be a CTP first to become a Fundi?', ''),
  ('251', '84', '132', 'No. Fundi registration is a standalone path. Skilled workers apply directly with their certificates and a referee.', ''),
  ('252', '85', '131', 'What documents do I need to register?', ''),
  ('253', '85', '132', 'CTP: national ID scan, passport photo, and any school certificate. Fundi: ID, business certificate (if any), trade certificates and up to 6 portfolio photos. Hirer: an ID or organisation document.', ''),
  ('254', '86', '131', 'Is registering as a hirer free?', ''),
  ('255', '86', '132', 'Yes — hirer registration is free, but every organisation is verified by an admin to prevent fraud.', ''),
  ('256', '87', '131', 'How is the membership fee paid?', ''),
  ('257', '87', '132', 'Via M-Pesa STK Push or bank transfer. The payment module is being switched on in the next phase; your application simply waits at \"pending payment\" until then.', ''),
  ('258', '88', '131', 'How long does approval take?', ''),
  ('259', '88', '132', 'Directors and admins review applications with all documents. You will be notified by email and SMS once a decision is made.', ''),
  ('260', '89', '133', 'Donate', ''),
  ('261', '89', '134', 'Fund a skill. Change a life.', ''),
  ('262', '89', '135', 'Every donation goes directly to programme work. One-time or recurring — M-Pesa, card or bank.', ''),
  ('263', '89', '136', 'Secure payment (M-Pesa STK Push, card, bank) is being switched on in the next phase. Your pledge is recorded and our team will contact you to complete it.', ''),
  ('264', '90', '137', 'Partners', ''),
  ('265', '90', '138', 'Organisations that power the programme', ''),
  ('266', '91', '139', '🤝', ''),
  ('267', '91', '140', 'The partner wall is being filled', ''),
  ('268', '91', '141', 'Our first partner logos will appear here. Want to be one of them? Use the form below.', ''),
  ('269', '92', '142', 'Become a Partner', ''),
  ('270', '92', '143', 'Tiers: Platinum · Gold · Silver. Listed partners appear on the logo wall and home page.', ''),
  ('271', '93', '144', 'Contact', ''),
  ('272', '93', '145', 'Talk to us', ''),
  ('273', '94', '146', 'Kisumu head office', ''),
  ('274', '94', '147', 'CTP-WireLakes Programme\nKisumu, Kenya', ''),
  ('275', '94', '148', '+254 700 000 000', ''),
  ('276', '94', '149', 'info@ctp-wirelakes.ke', ''),
  ('277', '94', '150', 'Read the FAQ first? Many answers are there →', ''),
  ('278', '95', '151', 'Send a message', ''),
  ('279', '96', '152', '1000', ''),
  ('280', '96', '153', '1000', ''),
  ('281', '96', '154', '0', ''),
  ('282', '96', '155', 'Membership fees are payable by M-Pesa or bank transfer. Online payment is being switched on in the next phase — our team will contact you with instructions.', ''),
  ('283', '97', '156', 'school_leaver', ''),
  ('284', '97', '157', 'School-leaver training track', ''),
  ('285', '97', '158', 'You have not yet learned a trade — the programme trains and certifies you from zero.', ''),
  ('286', '98', '156', 'junior', ''),
  ('287', '98', '157', 'Junior practical-check track', ''),
  ('288', '98', '158', 'You already hold a basic certificate and want practical experience or a job.', ''),
  ('289', '99', '159', 'fundi_intern', ''),
  ('290', '99', '160', 'Fundi-Intern', ''),
  ('291', '99', '161', 'Graduated, no experience, seeking an internship', ''),
  ('292', '100', '159', 'attachment', ''),
  ('293', '100', '160', 'Attachment applicant', ''),
  ('294', '100', '161', 'Still in school, not yet graduated', ''),
  ('295', '101', '159', 'junior_fundi', ''),
  ('296', '101', '160', 'Junior Fundi', ''),
  ('297', '101', '161', 'Recent graduate with internship experience', ''),
  ('298', '102', '159', 'senior_fundi', ''),
  ('299', '102', '160', 'Senior Fundi', ''),
  ('300', '102', '161', 'Long industry experience', ''),
  ('301', '52', '81', '', 'up-f81-yarqau.png'),
  ('302', '103', '165', '0.66', ''),
  ('303', '56', '97', '', 'from-training-to-fieldwork-f97-aEhAjL.png'),
  ('304', '57', '97', '', 'solar-training-f97-u0JNPd.png'),
  ('305', '58', '97', '', 'ctp-f97-SJ5gIe.png');

-- ---- counties ----
DROP TABLE IF EXISTS `counties`;
CREATE TABLE `counties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `counties_code_unique` (`code`),
  UNIQUE KEY `counties_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- rows: 47
INSERT INTO `counties` (`id`, `code`, `name`) VALUES
  ('1', '001', 'Mombasa'),
  ('2', '002', 'Kwale'),
  ('3', '003', 'Kilifi'),
  ('4', '004', 'Tana River'),
  ('5', '005', 'Lamu'),
  ('6', '006', 'Taita Taveta'),
  ('7', '007', 'Garissa'),
  ('8', '008', 'Wajir'),
  ('9', '009', 'Mandera'),
  ('10', '010', 'Marsabit'),
  ('11', '011', 'Isiolo'),
  ('12', '012', 'Meru'),
  ('13', '013', 'Tharaka-Nithi'),
  ('14', '014', 'Embu'),
  ('15', '015', 'Kitui'),
  ('16', '016', 'Machakos'),
  ('17', '017', 'Makueni'),
  ('18', '018', 'Nyandarua'),
  ('19', '019', 'Nyeri'),
  ('20', '020', 'Kirinyaga'),
  ('21', '021', 'Murang\'a'),
  ('22', '022', 'Kiambu'),
  ('23', '023', 'Turkana'),
  ('24', '024', 'West Pokot'),
  ('25', '025', 'Samburu'),
  ('26', '026', 'Trans Nzoia'),
  ('27', '027', 'Uasin Gishu'),
  ('28', '028', 'Elgeyo-Marakwet'),
  ('29', '029', 'Nandi'),
  ('30', '030', 'Baringo'),
  ('31', '031', 'Laikipia'),
  ('32', '032', 'Nakuru'),
  ('33', '033', 'Narok'),
  ('34', '034', 'Kajiado'),
  ('35', '035', 'Kericho'),
  ('36', '036', 'Bomet'),
  ('37', '037', 'Kakamega'),
  ('38', '038', 'Vihiga'),
  ('39', '039', 'Bungoma'),
  ('40', '040', 'Busia'),
  ('41', '041', 'Siaya'),
  ('42', '042', 'Kisumu'),
  ('43', '043', 'Homa Bay'),
  ('44', '044', 'Migori'),
  ('45', '045', 'Kisii'),
  ('46', '046', 'Nyamira'),
  ('47', '047', 'Nairobi');

-- ---- ctp_registrations ----
DROP TABLE IF EXISTS `ctp_registrations`;
CREATE TABLE `ctp_registrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `national_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dob` date NOT NULL,
  `gender` enum('male','female','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `county` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_county` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ward` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `village` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gps_lat` decimal(10,7) DEFAULT NULL,
  `gps_lng` decimal(10,7) DEFAULT NULL,
  `education_level` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `year_left_school` smallint unsigned DEFAULT NULL,
  `certificates_held` json DEFAULT NULL,
  `track` enum('school_leaver','junior') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fundi_tier` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referral_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referred_by` bigint unsigned DEFAULT NULL,
  `referred_at` timestamp NULL DEFAULT NULL,
  `service_counties` json DEFAULT NULL,
  `available_for_mobilisation` tinyint(1) NOT NULL DEFAULT '1',
  `trainer_opt_in` tinyint(1) NOT NULL DEFAULT '0',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `status_note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_scan_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passport_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending_payment','pending_review','active','suspended','graduated','deployed','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_payment',
  `fee_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'awaiting_payment',
  `fee_amount` decimal(10,2) DEFAULT NULL,
  `fee_method` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee_reference` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee_paid_at` timestamp NULL DEFAULT NULL,
  `sponsor_id` bigint unsigned DEFAULT NULL,
  `source` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'self',
  `added_by` bigint unsigned DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `ctp_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ctp_registrations_reference_unique` (`reference`),
  UNIQUE KEY `ctp_registrations_national_id_unique` (`national_id`),
  UNIQUE KEY `ctp_registrations_phone_unique` (`phone`),
  UNIQUE KEY `ctp_registrations_email_unique` (`email`),
  UNIQUE KEY `ctp_registrations_ctp_code_unique` (`ctp_code`),
  KEY `ctp_registrations_referral_code_index` (`referral_code`),
  KEY `ctp_registrations_user_id_foreign` (`user_id`),
  KEY `ctp_registrations_referred_by_foreign` (`referred_by`),
  KEY `ctp_registrations_approved_by_foreign` (`approved_by`),
  KEY `ctp_registrations_status_county_index` (`status`,`county`),
  KEY `ctp_registrations_sponsor_id_foreign` (`sponsor_id`),
  KEY `ctp_registrations_added_by_foreign` (`added_by`),
  CONSTRAINT `ctp_registrations_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ctp_registrations_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ctp_registrations_referred_by_foreign` FOREIGN KEY (`referred_by`) REFERENCES `ctp_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ctp_registrations_sponsor_id_foreign` FOREIGN KEY (`sponsor_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ctp_registrations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- donations ----
DROP TABLE IF EXISTS `donations`;
CREATE TABLE `donations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `donor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'KES',
  `method` enum('mpesa','card','bank') COLLATE utf8mb4_unicode_ci NOT NULL,
  `frequency` enum('one_time','monthly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'one_time',
  `impact_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `anonymous` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- enrollments ----
DROP TABLE IF EXISTS `enrollments`;
CREATE TABLE `enrollments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cohort_id` bigint unsigned NOT NULL,
  `ctp_id` bigint unsigned NOT NULL,
  `state` enum('pending','approved','declined','withdrawn') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `stage` enum('enrolled','theory','practical','assessment','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'enrolled',
  `fee_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'awaiting_payment',
  `fee_amount` decimal(10,2) DEFAULT NULL,
  `fee_method` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee_reference` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee_paid_at` timestamp NULL DEFAULT NULL,
  `fee_recorded_by` bigint unsigned DEFAULT NULL,
  `source` enum('self','office') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'self',
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `approved_on` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enrollments_cohort_id_ctp_id_unique` (`cohort_id`,`ctp_id`),
  KEY `enrollments_ctp_id_foreign` (`ctp_id`),
  KEY `enrollments_reviewed_by_foreign` (`reviewed_by`),
  KEY `enrollments_fee_recorded_by_foreign` (`fee_recorded_by`),
  CONSTRAINT `enrollments_cohort_id_foreign` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ctp_id_foreign` FOREIGN KEY (`ctp_id`) REFERENCES `ctp_registrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_fee_recorded_by_foreign` FOREIGN KEY (`fee_recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `enrollments_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- ewaste_collections ----
DROP TABLE IF EXISTS `ewaste_collections`;
CREATE TABLE `ewaste_collections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `collected_on` date NOT NULL,
  `community_id` bigint unsigned DEFAULT NULL,
  `county` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ward` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ctp_id` bigint unsigned DEFAULT NULL,
  `fundi_id` bigint unsigned DEFAULT NULL,
  `item_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `estimated_weight_kg` decimal(9,2) DEFAULT NULL,
  `weighed_weight_kg` decimal(9,2) DEFAULT NULL,
  `condition` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mixed',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_name` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_size` int unsigned DEFAULT NULL,
  `forwarded_by_fundi` bigint unsigned DEFAULT NULL,
  `forwarded_on` timestamp NULL DEFAULT NULL,
  `forward_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_by_user` bigint unsigned DEFAULT NULL,
  `received_on` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ewaste_collections_reference_unique` (`reference`),
  KEY `ewaste_collections_community_id_foreign` (`community_id`),
  KEY `ewaste_collections_ctp_id_foreign` (`ctp_id`),
  KEY `ewaste_collections_fundi_id_foreign` (`fundi_id`),
  KEY `ewaste_collections_forwarded_by_fundi_foreign` (`forwarded_by_fundi`),
  KEY `ewaste_collections_received_by_user_foreign` (`received_by_user`),
  KEY `ewaste_collections_status_collected_on_index` (`status`,`collected_on`),
  KEY `ewaste_collections_county_item_type_index` (`county`,`item_type`),
  CONSTRAINT `ewaste_collections_community_id_foreign` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ewaste_collections_ctp_id_foreign` FOREIGN KEY (`ctp_id`) REFERENCES `ctp_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ewaste_collections_forwarded_by_fundi_foreign` FOREIGN KEY (`forwarded_by_fundi`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ewaste_collections_fundi_id_foreign` FOREIGN KEY (`fundi_id`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ewaste_collections_received_by_user_foreign` FOREIGN KEY (`received_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- failed_jobs ----
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- finance_entries ----
DROP TABLE IF EXISTS `finance_entries`;
CREATE TABLE `finance_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fiscal_year` smallint unsigned NOT NULL,
  `direction` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `budget_category` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_ksh` decimal(14,2) NOT NULL,
  `method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mpesa',
  `happened_on` date NOT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `cohort_id` bigint unsigned DEFAULT NULL,
  `community_id` bigint unsigned DEFAULT NULL,
  `ctp_id` bigint unsigned DEFAULT NULL,
  `fundi_id` bigint unsigned DEFAULT NULL,
  `source_type` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `recorded_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `finance_entries_reference_unique` (`reference`),
  KEY `finance_entries_project_id_foreign` (`project_id`),
  KEY `finance_entries_cohort_id_foreign` (`cohort_id`),
  KEY `finance_entries_community_id_foreign` (`community_id`),
  KEY `finance_entries_ctp_id_foreign` (`ctp_id`),
  KEY `finance_entries_fundi_id_foreign` (`fundi_id`),
  KEY `finance_entries_recorded_by_user_foreign` (`recorded_by_user`),
  KEY `finance_entries_fiscal_year_direction_index` (`fiscal_year`,`direction`),
  KEY `finance_entries_kind_happened_on_index` (`kind`,`happened_on`),
  KEY `finance_entries_budget_category_index` (`budget_category`),
  KEY `finance_entries_source_type_source_id_index` (`source_type`,`source_id`),
  CONSTRAINT `finance_entries_cohort_id_foreign` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_entries_community_id_foreign` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_entries_ctp_id_foreign` FOREIGN KEY (`ctp_id`) REFERENCES `ctp_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_entries_fundi_id_foreign` FOREIGN KEY (`fundi_id`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_entries_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_entries_recorded_by_user_foreign` FOREIGN KEY (`recorded_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- fundi_registrations ----
DROP TABLE IF EXISTS `fundi_registrations`;
CREATE TABLE `fundi_registrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `national_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dob` date NOT NULL,
  `gender` enum('male','female','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tier` enum('fundi_intern','attachment','junior_fundi','senior_fundi') COLLATE utf8mb4_unicode_ci NOT NULL,
  `county` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_county` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ward` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobilised_by_ctp` bigint unsigned DEFAULT NULL,
  `business_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_reg_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `years_in_business` tinyint unsigned DEFAULT NULL,
  `specialisations` json NOT NULL,
  `service_counties` json NOT NULL,
  `referee_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referee_phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referee_role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_scan_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passport_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_cert_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trade_cert_paths` json DEFAULT NULL,
  `portfolio_paths` json DEFAULT NULL,
  `status` enum('pending_payment','pending_review','active','rejected','suspended','blacklisted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_payment',
  `availability` enum('available','on_job','on_leave') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `availability_note` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'awaiting_payment',
  `fee_amount` decimal(10,2) DEFAULT NULL,
  `fee_method` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee_reference` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fee_paid_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `ef_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fundi_registrations_reference_unique` (`reference`),
  UNIQUE KEY `fundi_registrations_national_id_unique` (`national_id`),
  UNIQUE KEY `fundi_registrations_phone_unique` (`phone`),
  UNIQUE KEY `fundi_registrations_email_unique` (`email`),
  UNIQUE KEY `fundi_registrations_ef_code_unique` (`ef_code`),
  KEY `fundi_registrations_user_id_foreign` (`user_id`),
  KEY `fundi_registrations_mobilised_by_ctp_foreign` (`mobilised_by_ctp`),
  CONSTRAINT `fundi_registrations_mobilised_by_ctp_foreign` FOREIGN KEY (`mobilised_by_ctp`) REFERENCES `ctp_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fundi_registrations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- hirer_registrations ----
DROP TABLE IF EXISTS `hirer_registrations`;
CREATE TABLE `hirer_registrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `organisation` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `county` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_county` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ward` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `org_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `org_doc_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending_review','active','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_review',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `hirer_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hirer_registrations_reference_unique` (`reference`),
  UNIQUE KEY `hirer_registrations_email_unique` (`email`),
  UNIQUE KEY `hirer_registrations_phone_unique` (`phone`),
  UNIQUE KEY `hirer_registrations_hirer_code_unique` (`hirer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- inventory_items ----
DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE `inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sku` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs',
  `qty_on_hand` decimal(12,2) NOT NULL DEFAULT '0.00',
  `reorder_level` decimal(12,2) NOT NULL DEFAULT '0.00',
  `location` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_cost_ksh` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_items_sku_unique` (`sku`),
  KEY `inventory_items_category_status_index` (`category`,`status`),
  KEY `inventory_items_qty_on_hand_reorder_level_index` (`qty_on_hand`,`reorder_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- job_batches ----
DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- jobs ----
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- labour_entries ----
DROP TABLE IF EXISTS `labour_entries`;
CREATE TABLE `labour_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `worked_on` date NOT NULL,
  `worker_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fundi_id` bigint unsigned DEFAULT NULL,
  `sub_contractor` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trade` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hours` decimal(6,2) NOT NULL DEFAULT '8.00',
  `rate_ksh` decimal(12,2) NOT NULL DEFAULT '0.00',
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `labour_entries_work_order_id_foreign` (`work_order_id`),
  KEY `labour_entries_fundi_id_foreign` (`fundi_id`),
  KEY `labour_entries_recorded_by_user_foreign` (`recorded_by_user`),
  KEY `labour_entries_project_id_worked_on_index` (`project_id`,`worked_on`),
  CONSTRAINT `labour_entries_fundi_id_foreign` FOREIGN KEY (`fundi_id`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `labour_entries_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `labour_entries_recorded_by_user_foreign` FOREIGN KEY (`recorded_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `labour_entries_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- migrations ----
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- rows: 44
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
  ('1', '0001_01_01_000000_create_users_table', '1'),
  ('2', '0001_01_01_000001_create_cache_table', '1'),
  ('3', '0001_01_01_000002_create_jobs_table', '1'),
  ('4', '2026_10_12_045815_create_personal_access_tokens_table', '1'),
  ('5', '2026_10_12_100000_add_role_to_users_table', '1'),
  ('6', '2026_10_12_100010_create_counties_and_settings_tables', '1'),
  ('7', '2026_10_12_100100_create_ctp_registrations_table', '1'),
  ('8', '2026_10_12_100110_create_fundi_registrations_table', '1'),
  ('9', '2026_10_12_100120_create_hirers_partners_donations_tables', '1'),
  ('10', '2026_10_12_200000_create_content_tables', '2'),
  ('11', '2026_10_12_200100_create_staff_invites_table', '2'),
  ('13', '2026_10_12_210000_add_caption_source_to_partners_table', '3'),
  ('14', '2026_10_12_220000_create_projects_tables', '4'),
  ('15', '2026_10_12_221000_add_member_roles_to_users_table', '5'),
  ('16', '2026_10_12_230000_add_fundi_tier_to_ctp_registrations_table', '6'),
  ('17', '2026_10_12_233000_add_subcounty_ward_to_hirer_registrations_table', '7'),
  ('18', '2026_10_12_234000_make_hirer_contacts_nullable', '8'),
  ('19', '2026_10_12_235000_add_materials_to_projects_table', '9'),
  ('20', '2026_10_13_090000_add_ctp_profile_and_lifecycle', '10'),
  ('21', '2026_10_13_140000_create_communities_module', '11'),
  ('22', '2026_10_13_160000_community_roster_mobilisers', '12'),
  ('23', '2026_10_13_161000_link_fundis_to_their_mobiliser', '12'),
  ('24', '2026_10_13_180000_create_training_module', '13'),
  ('25', '2026_10_13_200000_create_trainings_catalogue', '14'),
  ('26', '2026_10_14_100000_add_membership_fee_and_office_entry', '15'),
  ('27', '2026_10_14_140000_create_training_modules', '16'),
  ('28', '2026_10_14_150000_create_training_documents', '16'),
  ('29', '2026_10_14_170000_add_efundi_availability_and_trainer_sites', '17'),
  ('30', '2026_10_14_190000_create_ewaste_collections_table', '18'),
  ('31', '2026_10_14_210000_add_passport_photo_to_fundi_registrations_table', '19'),
  ('32', '2026_10_15_090000_add_academic_period_to_cohorts_table', '20'),
  ('33', '2026_10_15_120000_create_finance_budget_and_ledger_tables', '21'),
  ('34', '2026_10_15_120500_create_inventory_items_table', '22'),
  ('35', '2026_10_15_121000_create_procurement_tables', '22'),
  ('36', '2026_10_15_122000_create_stock_movements_table', '22'),
  ('37', '2026_10_15_123000_create_fundi_payouts_table', '22'),
  ('38', '2026_10_15_140000_create_project_materials_table', '23'),
  ('39', '2026_10_15_141000_add_enrolment_fee_fields', '23'),
  ('40', '2026_10_16_100000_create_activity_log_table', '24'),
  ('41', '2026_10_16_110000_create_project_matches_table', '25'),
  ('42', '2026_10_16_140000_create_work_order_tables', '26'),
  ('43', '2026_10_16_180000_create_construction_tables', '27'),
  ('44', '2026_10_16_190000_create_technology_tables', '28'),
  ('45', '2026_10_16_210000_add_departments_to_users_table', '29');

-- ---- module_completions ----
DROP TABLE IF EXISTS `module_completions`;
CREATE TABLE `module_completions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `enrollment_id` bigint unsigned NOT NULL,
  `training_module_id` bigint unsigned NOT NULL,
  `done_on` date NOT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by_user` bigint unsigned DEFAULT NULL,
  `recorded_by_fundi` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_completions_enrollment_id_training_module_id_unique` (`enrollment_id`,`training_module_id`),
  KEY `module_completions_training_module_id_foreign` (`training_module_id`),
  KEY `module_completions_recorded_by_user_foreign` (`recorded_by_user`),
  KEY `module_completions_recorded_by_fundi_foreign` (`recorded_by_fundi`),
  CONSTRAINT `module_completions_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_completions_recorded_by_fundi_foreign` FOREIGN KEY (`recorded_by_fundi`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `module_completions_recorded_by_user_foreign` FOREIGN KEY (`recorded_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `module_completions_training_module_id_foreign` FOREIGN KEY (`training_module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- needs ----
DROP TABLE IF EXISTS `needs`;
CREATE TABLE `needs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `community_id` bigint unsigned NOT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci,
  `priority` enum('low','standard','high','urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `status` enum('proposed','prioritised','converted','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proposed',
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_lat` decimal(10,7) DEFAULT NULL,
  `gps_lng` decimal(10,7) DEFAULT NULL,
  `assessed_by_ctp` bigint unsigned DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `needs_assessed_by_ctp_foreign` (`assessed_by_ctp`),
  KEY `needs_reviewed_by_foreign` (`reviewed_by`),
  KEY `needs_project_id_foreign` (`project_id`),
  KEY `needs_community_id_status_index` (`community_id`,`status`),
  CONSTRAINT `needs_assessed_by_ctp_foreign` FOREIGN KEY (`assessed_by_ctp`) REFERENCES `ctp_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `needs_community_id_foreign` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `needs_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `needs_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- partners ----
DROP TABLE IF EXISTS `partners`;
CREATE TABLE `partners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tier` enum('platinum','gold','silver') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'request',
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_listed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- password_reset_tokens ----
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- payouts ----
DROP TABLE IF EXISTS `payouts`;
CREATE TABLE `payouts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stream` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stage` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'awaiting_hirer_payment',
  `budget_category` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fundi_id` bigint unsigned NOT NULL,
  `trainer_id` bigint unsigned DEFAULT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `cohort_id` bigint unsigned DEFAULT NULL,
  `agreed_amount_ksh` decimal(14,2) DEFAULT NULL,
  `materials_ksh` decimal(14,2) NOT NULL DEFAULT '0.00',
  `commission_ksh` decimal(14,2) NOT NULL DEFAULT '0.00',
  `mobilization_ksh` decimal(14,2) NOT NULL DEFAULT '0.00',
  `penalty_ksh` decimal(14,2) NOT NULL DEFAULT '0.00',
  `hirer_paid_on` date DEFAULT NULL,
  `hirer_payment_ref` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certified_count` int unsigned DEFAULT NULL,
  `per_trainee_fee_ksh` decimal(14,2) DEFAULT NULL,
  `hosting_fee_ksh` decimal(14,2) NOT NULL DEFAULT '0.00',
  `report_submitted_on` date DEFAULT NULL,
  `report_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gross_ksh` decimal(14,2) NOT NULL DEFAULT '0.00',
  `net_ksh` decimal(14,2) NOT NULL DEFAULT '0.00',
  `calculation` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mpesa',
  `payee_phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payee_account` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_ref` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raised_by_user` bigint unsigned DEFAULT NULL,
  `approved_by_user` bigint unsigned DEFAULT NULL,
  `approved_on` timestamp NULL DEFAULT NULL,
  `released_by_user` bigint unsigned DEFAULT NULL,
  `released_on` timestamp NULL DEFAULT NULL,
  `held_reason` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payouts_reference_unique` (`reference`),
  KEY `payouts_trainer_id_foreign` (`trainer_id`),
  KEY `payouts_raised_by_user_foreign` (`raised_by_user`),
  KEY `payouts_approved_by_user_foreign` (`approved_by_user`),
  KEY `payouts_released_by_user_foreign` (`released_by_user`),
  KEY `payouts_stream_stage_index` (`stream`,`stage`),
  KEY `payouts_fundi_id_stream_index` (`fundi_id`,`stream`),
  KEY `payouts_project_id_index` (`project_id`),
  KEY `payouts_cohort_id_index` (`cohort_id`),
  CONSTRAINT `payouts_approved_by_user_foreign` FOREIGN KEY (`approved_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payouts_cohort_id_foreign` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payouts_fundi_id_foreign` FOREIGN KEY (`fundi_id`) REFERENCES `fundi_registrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payouts_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payouts_raised_by_user_foreign` FOREIGN KEY (`raised_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payouts_released_by_user_foreign` FOREIGN KEY (`released_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payouts_trainer_id_foreign` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- personal_access_tokens ----
DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- progress_entries ----
DROP TABLE IF EXISTS `progress_entries`;
CREATE TABLE `progress_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `enrollment_id` bigint unsigned NOT NULL,
  `entry_date` date NOT NULL,
  `kind` enum('attendance','theory','practical','assessment','note') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'note',
  `present` tinyint(1) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `hours` smallint unsigned DEFAULT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by_user` bigint unsigned DEFAULT NULL,
  `recorded_by_fundi` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `progress_entries_recorded_by_user_foreign` (`recorded_by_user`),
  KEY `progress_entries_recorded_by_fundi_foreign` (`recorded_by_fundi`),
  KEY `progress_entries_enrollment_id_entry_date_index` (`enrollment_id`,`entry_date`),
  CONSTRAINT `progress_entries_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progress_entries_recorded_by_fundi_foreign` FOREIGN KEY (`recorded_by_fundi`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `progress_entries_recorded_by_user_foreign` FOREIGN KEY (`recorded_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- project_applications ----
DROP TABLE IF EXISTS `project_applications`;
CREATE TABLE `project_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `applicant_type` enum('ctp','fundi') COLLATE utf8mb4_unicode_ci NOT NULL,
  `applicant_id` bigint unsigned NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending_review','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_review',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_applications_member_unique` (`project_id`,`applicant_type`,`applicant_id`),
  KEY `project_applications_reviewed_by_foreign` (`reviewed_by`),
  KEY `project_applications_member_index` (`applicant_type`,`applicant_id`),
  CONSTRAINT `project_applications_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_applications_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- project_matches ----
DROP TABLE IF EXISTS `project_matches`;
CREATE TABLE `project_matches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `fundi_id` bigint unsigned NOT NULL,
  `round` tinyint unsigned NOT NULL DEFAULT '1',
  `score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `matched` json DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shortlisted',
  `invited_on` timestamp NULL DEFAULT NULL,
  `responded_on` timestamp NULL DEFAULT NULL,
  `widened` tinyint(1) NOT NULL DEFAULT '0',
  `below_bar` tinyint(1) NOT NULL DEFAULT '0',
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `awarded_by_user` bigint unsigned DEFAULT NULL,
  `awarded_on` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_matches_project_id_fundi_id_round_unique` (`project_id`,`fundi_id`,`round`),
  KEY `project_matches_awarded_by_user_foreign` (`awarded_by_user`),
  KEY `project_matches_project_id_round_index` (`project_id`,`round`),
  KEY `project_matches_fundi_id_status_index` (`fundi_id`,`status`),
  CONSTRAINT `project_matches_awarded_by_user_foreign` FOREIGN KEY (`awarded_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_matches_fundi_id_foreign` FOREIGN KEY (`fundi_id`) REFERENCES `fundi_registrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_matches_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- project_materials ----
DROP TABLE IF EXISTS `project_materials`;
CREATE TABLE `project_materials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned DEFAULT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs',
  `unit_cost_ksh` decimal(12,2) NOT NULL DEFAULT '0.00',
  `supplied_on` date DEFAULT NULL,
  `supplied_by_user` bigint unsigned DEFAULT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_materials_project_id_foreign` (`project_id`),
  KEY `project_materials_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `project_materials_supplied_by_user_foreign` (`supplied_by_user`),
  CONSTRAINT `project_materials_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_materials_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_materials_supplied_by_user_foreign` FOREIGN KEY (`supplied_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- projects ----
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hirer_id` bigint unsigned DEFAULT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `materials` text COLLATE utf8mb4_unicode_ci,
  `category` enum('attachment','internship','junior_fundi','senior_fundi') COLLATE utf8mb4_unicode_ci NOT NULL,
  `trade` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `county` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ward` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `community_id` bigint unsigned DEFAULT NULL,
  `budget` decimal(12,2) DEFAULT NULL,
  `duration` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('draft','open','awarded','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `awarded_at` timestamp NULL DEFAULT NULL,
  `posted_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projects_reference_unique` (`reference`),
  KEY `projects_hirer_id_foreign` (`hirer_id`),
  KEY `projects_posted_by_foreign` (`posted_by`),
  KEY `projects_community_id_foreign` (`community_id`),
  CONSTRAINT `projects_community_id_foreign` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `projects_hirer_id_foreign` FOREIGN KEY (`hirer_id`) REFERENCES `hirer_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `projects_posted_by_foreign` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- purchase_orders ----
DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_request_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_cost_ksh` decimal(14,2) NOT NULL,
  `amount_ksh` decimal(14,2) NOT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raised_by_user` bigint unsigned DEFAULT NULL,
  `issued_on` timestamp NULL DEFAULT NULL,
  `delivery_note_ref` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_qty` decimal(12,2) DEFAULT NULL,
  `receipt_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_by_user` bigint unsigned DEFAULT NULL,
  `received_on` timestamp NULL DEFAULT NULL,
  `invoice_ref` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_amount_ksh` decimal(14,2) DEFAULT NULL,
  `matched_by_user` bigint unsigned DEFAULT NULL,
  `matched_on` timestamp NULL DEFAULT NULL,
  `paid_on` date DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_orders_reference_unique` (`reference`),
  KEY `purchase_orders_purchase_request_id_foreign` (`purchase_request_id`),
  KEY `purchase_orders_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_orders_raised_by_user_foreign` (`raised_by_user`),
  KEY `purchase_orders_received_by_user_foreign` (`received_by_user`),
  KEY `purchase_orders_matched_by_user_foreign` (`matched_by_user`),
  KEY `purchase_orders_status_supplier_id_index` (`status`,`supplier_id`),
  CONSTRAINT `purchase_orders_matched_by_user_foreign` FOREIGN KEY (`matched_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_raised_by_user_foreign` FOREIGN KEY (`raised_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_received_by_user_foreign` FOREIGN KEY (`received_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- purchase_quotations ----
DROP TABLE IF EXISTS `purchase_quotations`;
CREATE TABLE `purchase_quotations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_request_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `amount_ksh` decimal(14,2) NOT NULL,
  `quotation_ref` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quoted_on` date NOT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_selected` tinyint(1) NOT NULL DEFAULT '0',
  `added_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_quotations_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_quotations_added_by_user_foreign` (`added_by_user`),
  KEY `purchase_quotations_purchase_request_id_amount_ksh_index` (`purchase_request_id`,`amount_ksh`),
  CONSTRAINT `purchase_quotations_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_quotations_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_quotations_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- purchase_requests ----
DROP TABLE IF EXISTS `purchase_requests`;
CREATE TABLE `purchase_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs',
  `estimated_cost_ksh` decimal(14,2) DEFAULT NULL,
  `budget_category` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `justification` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `needed_by` date DEFAULT NULL,
  `county` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `cohort_id` bigint unsigned DEFAULT NULL,
  `inventory_item_id` bigint unsigned DEFAULT NULL,
  `raised_by_user` bigint unsigned DEFAULT NULL,
  `ctp_id` bigint unsigned DEFAULT NULL,
  `fundi_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `decided_by_user` bigint unsigned DEFAULT NULL,
  `decided_on` timestamp NULL DEFAULT NULL,
  `decision_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_requests_reference_unique` (`reference`),
  KEY `purchase_requests_project_id_foreign` (`project_id`),
  KEY `purchase_requests_cohort_id_foreign` (`cohort_id`),
  KEY `purchase_requests_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `purchase_requests_raised_by_user_foreign` (`raised_by_user`),
  KEY `purchase_requests_ctp_id_foreign` (`ctp_id`),
  KEY `purchase_requests_fundi_id_foreign` (`fundi_id`),
  KEY `purchase_requests_decided_by_user_foreign` (`decided_by_user`),
  KEY `purchase_requests_status_needed_by_index` (`status`,`needed_by`),
  KEY `purchase_requests_budget_category_index` (`budget_category`),
  CONSTRAINT `purchase_requests_cohort_id_foreign` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_ctp_id_foreign` FOREIGN KEY (`ctp_id`) REFERENCES `ctp_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_decided_by_user_foreign` FOREIGN KEY (`decided_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_fundi_id_foreign` FOREIGN KEY (`fundi_id`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_raised_by_user_foreign` FOREIGN KEY (`raised_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- sessions ----
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- settings ----
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- rows: 4
INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
  ('1', 'membership_fee_ctp', '1000', '2026-10-12 07:37:47', '2026-10-14 05:21:44'),
  ('2', 'membership_fee_fundi', '1000', '2026-10-12 07:37:47', '2026-10-14 05:21:44'),
  ('3', 'programme_email', 'info@ctp-wirelakes.ke', '2026-10-12 07:37:47', '2026-10-12 07:37:47'),
  ('4', 'programme_phone', '+254 700 000 000', '2026-10-12 07:37:47', '2026-10-15 15:28:05');

-- ---- site_assessments ----
DROP TABLE IF EXISTS `site_assessments`;
CREATE TABLE `site_assessments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `visited_on` date NOT NULL,
  `ground_conditions` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `utilities` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `risks` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `findings` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `recommendation` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proceed',
  `photo_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_size` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assessed_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `site_assessments_assessed_by_user_foreign` (`assessed_by_user`),
  KEY `site_assessments_project_id_visited_on_index` (`project_id`,`visited_on`),
  CONSTRAINT `site_assessments_assessed_by_user_foreign` FOREIGN KEY (`assessed_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_assessments_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- site_inspection_items ----
DROP TABLE IF EXISTS `site_inspection_items`;
CREATE TABLE `site_inspection_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inspection_id` bigint unsigned NOT NULL,
  `item` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `result` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proof_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proof_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proof_size` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proof_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proof_on` timestamp NULL DEFAULT NULL,
  `proved_by_fundi` bigint unsigned DEFAULT NULL,
  `rechecked_on` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `site_inspection_items_proved_by_fundi_foreign` (`proved_by_fundi`),
  KEY `site_inspection_items_inspection_id_result_index` (`inspection_id`,`result`),
  CONSTRAINT `site_inspection_items_inspection_id_foreign` FOREIGN KEY (`inspection_id`) REFERENCES `site_inspections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_inspection_items_proved_by_fundi_foreign` FOREIGN KEY (`proved_by_fundi`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- site_inspections ----
DROP TABLE IF EXISTS `site_inspections`;
CREATE TABLE `site_inspections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `scheduled_on` date NOT NULL,
  `inspected_on` date DEFAULT NULL,
  `purpose` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `report_note` text COLLATE utf8mb4_unicode_ci,
  `photo_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_size` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inspector_user` bigint unsigned DEFAULT NULL,
  `signed_off_on` timestamp NULL DEFAULT NULL,
  `signed_off_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `site_inspections_work_order_id_foreign` (`work_order_id`),
  KEY `site_inspections_inspector_user_foreign` (`inspector_user`),
  KEY `site_inspections_signed_off_by_user_foreign` (`signed_off_by_user`),
  KEY `site_inspections_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `site_inspections_inspector_user_foreign` FOREIGN KEY (`inspector_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_inspections_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_inspections_signed_off_by_user_foreign` FOREIGN KEY (`signed_off_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_inspections_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- staff_invites ----
DROP TABLE IF EXISTS `staff_invites`;
CREATE TABLE `staff_invites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','superadmin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `department` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invited_by` bigint unsigned DEFAULT NULL,
  `status` enum('pending','accepted','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `accepted_user_id` bigint unsigned DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_invites_token_unique` (`token`),
  KEY `staff_invites_invited_by_foreign` (`invited_by`),
  KEY `staff_invites_accepted_user_id_foreign` (`accepted_user_id`),
  CONSTRAINT `staff_invites_accepted_user_id_foreign` FOREIGN KEY (`accepted_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_invites_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- stock_movements ----
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint unsigned NOT NULL,
  `direction` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_cost_ksh` decimal(14,2) DEFAULT NULL,
  `reason` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `cohort_id` bigint unsigned DEFAULT NULL,
  `purchase_order_id` bigint unsigned DEFAULT NULL,
  `source_type` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `moved_on` date NOT NULL,
  `recorded_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_movements_project_id_foreign` (`project_id`),
  KEY `stock_movements_cohort_id_foreign` (`cohort_id`),
  KEY `stock_movements_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `stock_movements_recorded_by_user_foreign` (`recorded_by_user`),
  KEY `stock_movements_inventory_item_id_moved_on_index` (`inventory_item_id`,`moved_on`),
  KEY `stock_movements_direction_reason_index` (`direction`,`reason`),
  CONSTRAINT `stock_movements_cohort_id_foreign` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_recorded_by_user_foreign` FOREIGN KEY (`recorded_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- suppliers ----
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `county` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplies` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mpesa_paybill` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `added_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_code_unique` (`code`),
  KEY `suppliers_added_by_user_foreign` (`added_by_user`),
  KEY `suppliers_status_supplies_index` (`status`,`supplies`),
  CONSTRAINT `suppliers_added_by_user_foreign` FOREIGN KEY (`added_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- technology_assets ----
DROP TABLE IF EXISTS `technology_assets`;
CREATE TABLE `technology_assets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `serial_number` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operational',
  `qr_payload` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_id` bigint unsigned DEFAULT NULL,
  `assigned_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `county` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost_ksh` decimal(14,2) NOT NULL DEFAULT '0.00',
  `supplier` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warranty_expires_on` date DEFAULT NULL,
  `licence_key` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `licence_seats` smallint unsigned DEFAULT NULL,
  `licence_renews_on` date DEFAULT NULL,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'purchase',
  `ewaste_collection_id` bigint unsigned DEFAULT NULL,
  `decommissioned_on` date DEFAULT NULL,
  `decommission_note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `technology_assets_asset_code_unique` (`asset_code`),
  KEY `technology_assets_ewaste_collection_id_foreign` (`ewaste_collection_id`),
  KEY `technology_assets_created_by_user_foreign` (`created_by_user`),
  KEY `technology_assets_category_status_index` (`category`,`status`),
  KEY `technology_assets_warranty_expires_on_index` (`warranty_expires_on`),
  CONSTRAINT `technology_assets_created_by_user_foreign` FOREIGN KEY (`created_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `technology_assets_ewaste_collection_id_foreign` FOREIGN KEY (`ewaste_collection_id`) REFERENCES `ewaste_collections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- technology_projects ----
DROP TABLE IF EXISTS `technology_projects`;
CREATE TABLE `technology_projects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `problem` text COLLATE utf8mb4_unicode_ci,
  `technology` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `team_size` smallint unsigned NOT NULL DEFAULT '0',
  `mentor_fundi_id` bigint unsigned DEFAULT NULL,
  `community_id` bigint unsigned DEFAULT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'identified',
  `progress_percent` tinyint unsigned NOT NULL DEFAULT '0',
  `created_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `technology_projects_reference_unique` (`reference`),
  KEY `technology_projects_mentor_fundi_id_foreign` (`mentor_fundi_id`),
  KEY `technology_projects_community_id_foreign` (`community_id`),
  KEY `technology_projects_project_id_foreign` (`project_id`),
  KEY `technology_projects_created_by_user_foreign` (`created_by_user`),
  KEY `technology_projects_category_status_index` (`category`,`status`),
  CONSTRAINT `technology_projects_community_id_foreign` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `technology_projects_created_by_user_foreign` FOREIGN KEY (`created_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `technology_projects_mentor_fundi_id_foreign` FOREIGN KEY (`mentor_fundi_id`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `technology_projects_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- technology_tickets ----
DROP TABLE IF EXISTS `technology_tickets`;
CREATE TABLE `technology_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `asset_id` bigint unsigned NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fault` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `opened_by` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opened_by_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_fundi_id` bigint unsigned DEFAULT NULL,
  `priority` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `resolution` text COLLATE utf8mb4_unicode_ci,
  `resolved_on` timestamp NULL DEFAULT NULL,
  `closed_on` timestamp NULL DEFAULT NULL,
  `created_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `technology_tickets_reference_unique` (`reference`),
  KEY `technology_tickets_asset_id_foreign` (`asset_id`),
  KEY `technology_tickets_created_by_user_foreign` (`created_by_user`),
  KEY `technology_tickets_status_priority_index` (`status`,`priority`),
  KEY `technology_tickets_assigned_fundi_id_status_index` (`assigned_fundi_id`,`status`),
  CONSTRAINT `technology_tickets_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `technology_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `technology_tickets_assigned_fundi_id_foreign` FOREIGN KEY (`assigned_fundi_id`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `technology_tickets_created_by_user_foreign` FOREIGN KEY (`created_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- trainer_training ----
DROP TABLE IF EXISTS `trainer_training`;
CREATE TABLE `trainer_training` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trainer_id` bigint unsigned NOT NULL,
  `training_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trainer_training_trainer_id_training_id_unique` (`trainer_id`,`training_id`),
  KEY `trainer_training_training_id_foreign` (`training_id`),
  CONSTRAINT `trainer_training_trainer_id_foreign` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainer_training_training_id_foreign` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- trainers ----
DROP TABLE IF EXISTS `trainers`;
CREATE TABLE `trainers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fundi_id` bigint unsigned NOT NULL,
  `venue_id` bigint unsigned DEFAULT NULL,
  `county` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_county` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ward` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weekly_hours` tinyint unsigned NOT NULL DEFAULT '0',
  `rate_ksh` decimal(10,2) DEFAULT NULL,
  `status` enum('applied','approved','declined','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'applied',
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trainers_fundi_id_venue_id_unique` (`fundi_id`,`venue_id`),
  KEY `trainers_venue_id_foreign` (`venue_id`),
  KEY `trainers_reviewed_by_foreign` (`reviewed_by`),
  CONSTRAINT `trainers_fundi_id_foreign` FOREIGN KEY (`fundi_id`) REFERENCES `fundi_registrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainers_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trainers_venue_id_foreign` FOREIGN KEY (`venue_id`) REFERENCES `training_venues` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- training_documents ----
DROP TABLE IF EXISTS `training_documents`;
CREATE TABLE `training_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `enrollment_id` bigint unsigned NOT NULL,
  `training_module_id` bigint unsigned DEFAULT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` enum('school_report','assessment_paper','competency_sheet','photo','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `dated_on` date NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by_user` bigint unsigned DEFAULT NULL,
  `uploaded_by_fundi` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_documents_training_module_id_foreign` (`training_module_id`),
  KEY `training_documents_uploaded_by_user_foreign` (`uploaded_by_user`),
  KEY `training_documents_uploaded_by_fundi_foreign` (`uploaded_by_fundi`),
  KEY `training_documents_enrollment_id_dated_on_index` (`enrollment_id`,`dated_on`),
  CONSTRAINT `training_documents_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_documents_training_module_id_foreign` FOREIGN KEY (`training_module_id`) REFERENCES `training_modules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `training_documents_uploaded_by_fundi_foreign` FOREIGN KEY (`uploaded_by_fundi`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `training_documents_uploaded_by_user_foreign` FOREIGN KEY (`uploaded_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- training_modules ----
DROP TABLE IF EXISTS `training_modules`;
CREATE TABLE `training_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `training_id` bigint unsigned NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `outcome` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hours` smallint unsigned NOT NULL DEFAULT '0',
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_modules_training_id_position_index` (`training_id`,`position`),
  CONSTRAINT `training_modules_training_id_foreign` FOREIGN KEY (`training_id`) REFERENCES `trainings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- rows: 3
INSERT INTO `training_modules` (`id`, `training_id`, `title`, `outcome`, `hours`, `position`, `created_at`, `updated_at`) VALUES
  ('1', '1', 'Safety and isolation', 'Work dead and prove the supply is off before touching a conductor.', '12', '1', '2026-10-14 08:46:17', '2026-10-14 08:46:17'),
  ('2', '1', 'Wiring a distribution board', '', '20', '2', '2026-10-14 08:46:17', '2026-10-14 08:46:17'),
  ('3', '1', 'Testing and handover', '', '8', '3', '2026-10-14 08:46:17', '2026-10-14 08:46:17');

-- ---- training_venues ----
DROP TABLE IF EXISTS `training_venues`;
CREATE TABLE `training_venues` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('partner_school','community_site','programme_centre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'community_site',
  `partner_id` bigint unsigned DEFAULT NULL,
  `community_id` bigint unsigned DEFAULT NULL,
  `county` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_county` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ward` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` smallint unsigned NOT NULL DEFAULT '0',
  `contact_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_venues_partner_id_foreign` (`partner_id`),
  KEY `training_venues_community_id_foreign` (`community_id`),
  CONSTRAINT `training_venues_community_id_foreign` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `training_venues_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- trainings ----
DROP TABLE IF EXISTS `trainings`;
CREATE TABLE `trainings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trainings_name_unique` (`name`),
  UNIQUE KEY `trainings_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- rows: 9
INSERT INTO `trainings` (`id`, `name`, `code`, `summary`, `status`, `created_at`, `updated_at`) VALUES
  ('1', 'ICT', 'ICT', 'Computer hardware and software, diagnostics and repair', 'active', '2026-10-13 11:12:51', '2026-10-13 11:12:51'),
  ('2', 'Networking', 'NETWORKING', 'Cabling, routers, switching and network setup', 'active', '2026-10-13 11:12:51', '2026-10-13 11:12:51'),
  ('3', 'Programming', 'PROGRAMMING', 'Writing and testing software', 'active', '2026-10-13 11:12:51', '2026-10-13 11:12:51'),
  ('4', 'Robotics', 'ROBOTICS', 'Building and programming robots, STEM kits', 'active', '2026-10-13 11:12:51', '2026-10-13 11:12:51'),
  ('5', 'AI', 'AI', 'Applied artificial intelligence tools', 'active', '2026-10-13 11:12:51', '2026-10-13 11:12:51'),
  ('6', 'Electrical', 'ELECTRICAL', 'Household and building electrical work', 'active', '2026-10-13 11:12:51', '2026-10-13 11:12:51'),
  ('7', 'Construction', 'CONSTRUCTION', 'Building work, masonry, plumbing and finishing', 'active', '2026-10-13 11:12:51', '2026-10-14 08:28:09'),
  ('8', 'E-Waste', 'EWASTE', 'Collection, sorting, dismantling and safe disposal of e-waste', 'active', '2026-10-13 11:12:51', '2026-10-13 11:40:28'),
  ('9', 'Solar Installation', 'SOLAR', 'Roof mounting, batteries and inverters', 'active', '2026-10-13 11:39:49', '2026-10-13 11:47:22');

-- ---- users ----
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('user','ctp','fundi','admin','superadmin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `departments` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- variation_orders ----
DROP TABLE IF EXISTS `variation_orders`;
CREATE TABLE `variation_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `scope` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extra_cost_ksh` decimal(14,2) NOT NULL DEFAULT '0.00',
  `extra_materials` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extra_days` smallint unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proposed',
  `raised_by_user` bigint unsigned DEFAULT NULL,
  `decided_by_user` bigint unsigned DEFAULT NULL,
  `decided_on` timestamp NULL DEFAULT NULL,
  `decision_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `variation_orders_reference_unique` (`reference`),
  KEY `variation_orders_work_order_id_foreign` (`work_order_id`),
  KEY `variation_orders_raised_by_user_foreign` (`raised_by_user`),
  KEY `variation_orders_decided_by_user_foreign` (`decided_by_user`),
  KEY `variation_orders_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `variation_orders_decided_by_user_foreign` FOREIGN KEY (`decided_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `variation_orders_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `variation_orders_raised_by_user_foreign` FOREIGN KEY (`raised_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `variation_orders_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- work_order_disputes ----
DROP TABLE IF EXISTS `work_order_disputes`;
CREATE TABLE `work_order_disputes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned NOT NULL,
  `issue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_size` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raised_by_user` bigint unsigned DEFAULT NULL,
  `raised_on` timestamp NOT NULL,
  `responded_on` timestamp NULL DEFAULT NULL,
  `response` text COLLATE utf8mb4_unicode_ci,
  `responded_by_fundi` bigint unsigned DEFAULT NULL,
  `mediated_by_user` bigint unsigned DEFAULT NULL,
  `mediation_note` text COLLATE utf8mb4_unicode_ci,
  `outcome` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `penalty_ksh` decimal(14,2) DEFAULT NULL,
  `outcome_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decided_by_user` bigint unsigned DEFAULT NULL,
  `decided_on` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_order_disputes_raised_by_user_foreign` (`raised_by_user`),
  KEY `work_order_disputes_responded_by_fundi_foreign` (`responded_by_fundi`),
  KEY `work_order_disputes_mediated_by_user_foreign` (`mediated_by_user`),
  KEY `work_order_disputes_decided_by_user_foreign` (`decided_by_user`),
  KEY `work_order_disputes_work_order_id_outcome_index` (`work_order_id`,`outcome`),
  CONSTRAINT `work_order_disputes_decided_by_user_foreign` FOREIGN KEY (`decided_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_order_disputes_mediated_by_user_foreign` FOREIGN KEY (`mediated_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_order_disputes_raised_by_user_foreign` FOREIGN KEY (`raised_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_order_disputes_responded_by_fundi_foreign` FOREIGN KEY (`responded_by_fundi`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_order_disputes_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- work_order_milestones ----
DROP TABLE IF EXISTS `work_order_milestones`;
CREATE TABLE `work_order_milestones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `share_percent` tinyint unsigned NOT NULL DEFAULT '0',
  `amount_ksh` decimal(14,2) DEFAULT NULL,
  `due_on` date DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `signed_on` timestamp NULL DEFAULT NULL,
  `signed_by_user` bigint unsigned DEFAULT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_order_milestones_signed_by_user_foreign` (`signed_by_user`),
  KEY `work_order_milestones_work_order_id_status_index` (`work_order_id`,`status`),
  CONSTRAINT `work_order_milestones_signed_by_user_foreign` FOREIGN KEY (`signed_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_order_milestones_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- work_order_updates ----
DROP TABLE IF EXISTS `work_order_updates`;
CREATE TABLE `work_order_updates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned NOT NULL,
  `kind` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'progress',
  `entry_date` date NOT NULL,
  `percent` tinyint unsigned DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `photo_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_size` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by_user` bigint unsigned DEFAULT NULL,
  `recorded_by_fundi` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_order_updates_recorded_by_user_foreign` (`recorded_by_user`),
  KEY `work_order_updates_recorded_by_fundi_foreign` (`recorded_by_fundi`),
  KEY `work_order_updates_work_order_id_entry_date_index` (`work_order_id`,`entry_date`),
  CONSTRAINT `work_order_updates_recorded_by_fundi_foreign` FOREIGN KEY (`recorded_by_fundi`) REFERENCES `fundi_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_order_updates_recorded_by_user_foreign` FOREIGN KEY (`recorded_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_order_updates_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- work_orders ----
DROP TABLE IF EXISTS `work_orders`;
CREATE TABLE `work_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `assignee_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fundi',
  `assignee_id` bigint unsigned DEFAULT NULL,
  `assignee_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'issued',
  `scope` text COLLATE utf8mb4_unicode_ci,
  `deliverables` text COLLATE utf8mb4_unicode_ci,
  `start_on` date DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `schedule` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `split` json DEFAULT NULL,
  `agreed_amount_ksh` decimal(14,2) DEFAULT NULL,
  `materials_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accepted_on` timestamp NULL DEFAULT NULL,
  `signature` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accept_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percent_complete` tinyint unsigned NOT NULL DEFAULT '0',
  `completion_requested_on` timestamp NULL DEFAULT NULL,
  `confirmed_on` timestamp NULL DEFAULT NULL,
  `confirmed_by_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmation_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmation_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmation_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_on` timestamp NULL DEFAULT NULL,
  `closed_on` timestamp NULL DEFAULT NULL,
  `closure_note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raised_by_user` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_orders_reference_unique` (`reference`),
  KEY `work_orders_raised_by_user_foreign` (`raised_by_user`),
  KEY `work_orders_project_id_status_index` (`project_id`,`status`),
  KEY `work_orders_assignee_type_assignee_id_status_index` (`assignee_type`,`assignee_id`,`status`),
  CONSTRAINT `work_orders_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_orders_raised_by_user_foreign` FOREIGN KEY (`raised_by_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
