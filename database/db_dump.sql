-- RC Convergio CRM Database Schema
-- Generated: 2025-09-03 08:55:35
-- Database: rc_convergio_s
-- Structure only (no data)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Table structure for table `activities`
--

DROP TABLE IF EXISTS `activities`;
CREATE TABLE IF NOT EXISTS `activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'scheduled',
  `owner_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `related_type` varchar(255) DEFAULT NULL,
  `related_id` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activities_related_type_related_id_index` (`related_type`,`related_id`),
  KEY `activities_tenant_id_index` (`tenant_id`),
  KEY `activities_owner_id_index` (`owner_id`),
  KEY `activities_type_index` (`type`),
  KEY `activities_status_index` (`status`),
  KEY `activities_scheduled_at_index` (`scheduled_at`),
  KEY `activities_completed_at_index` (`completed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `activities`
--

ALTER TABLE `activities` ADD INDEX `activities_related_type_related_id_index` (`related_type`);
ALTER TABLE `activities` ADD INDEX `activities_related_type_related_id_index` (`related_id`);
ALTER TABLE `activities` ADD INDEX `activities_tenant_id_index` (`tenant_id`);
ALTER TABLE `activities` ADD INDEX `activities_owner_id_index` (`owner_id`);
ALTER TABLE `activities` ADD INDEX `activities_type_index` (`type`);
ALTER TABLE `activities` ADD INDEX `activities_status_index` (`status`);
ALTER TABLE `activities` ADD INDEX `activities_scheduled_at_index` (`scheduled_at`);
ALTER TABLE `activities` ADD INDEX `activities_completed_at_index` (`completed_at`);


--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `campaign_recipients`
--

DROP TABLE IF EXISTS `campaign_recipients`;
CREATE TABLE IF NOT EXISTS `campaign_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` bigint(20) unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `status` enum('pending','sent','delivered','opened','clicked','bounced','failed') NOT NULL DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `bounced_at` timestamp NULL DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaign_recipients_campaign_id_email_unique` (`campaign_id`,`email`),
  KEY `campaign_recipients_campaign_id_index` (`campaign_id`),
  KEY `campaign_recipients_email_index` (`email`),
  KEY `campaign_recipients_status_index` (`status`),
  CONSTRAINT `campaign_recipients_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `campaign_recipients`
--

ALTER TABLE `campaign_recipients` ADD INDEX `campaign_recipients_campaign_id_email_unique` (`campaign_id`);
ALTER TABLE `campaign_recipients` ADD INDEX `campaign_recipients_campaign_id_email_unique` (`email`);
ALTER TABLE `campaign_recipients` ADD INDEX `campaign_recipients_campaign_id_index` (`campaign_id`);
ALTER TABLE `campaign_recipients` ADD INDEX `campaign_recipients_email_index` (`email`);
ALTER TABLE `campaign_recipients` ADD INDEX `campaign_recipients_status_index` (`status`);


--
-- Table structure for table `campaigns`
--

DROP TABLE IF EXISTS `campaigns`;
CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `type` enum('email','sms') NOT NULL DEFAULT 'email',
  `status` enum('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `tenant_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `total_recipients` int(11) NOT NULL DEFAULT 0,
  `sent_count` int(11) NOT NULL DEFAULT 0,
  `delivered_count` int(11) NOT NULL DEFAULT 0,
  `opened_count` int(11) NOT NULL DEFAULT 0,
  `clicked_count` int(11) NOT NULL DEFAULT 0,
  `bounced_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaigns_tenant_id_index` (`tenant_id`),
  KEY `campaigns_created_by_index` (`created_by`),
  KEY `campaigns_status_index` (`status`),
  KEY `campaigns_type_index` (`type`),
  KEY `campaigns_scheduled_at_index` (`scheduled_at`),
  CONSTRAINT `campaigns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `campaigns`
--

ALTER TABLE `campaigns` ADD INDEX `campaigns_tenant_id_index` (`tenant_id`);
ALTER TABLE `campaigns` ADD INDEX `campaigns_created_by_index` (`created_by`);
ALTER TABLE `campaigns` ADD INDEX `campaigns_status_index` (`status`);
ALTER TABLE `campaigns` ADD INDEX `campaigns_type_index` (`type`);
ALTER TABLE `campaigns` ADD INDEX `campaigns_scheduled_at_index` (`scheduled_at`);


--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
CREATE TABLE IF NOT EXISTS `companies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`address`)),
  `annual_revenue` decimal(15,2) DEFAULT NULL,
  `timezone` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `linkedin_page` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `owner_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `companies_domain_tenant_unique` (`domain`,`tenant_id`),
  KEY `companies_tenant_id_index` (`tenant_id`),
  KEY `companies_owner_id_index` (`owner_id`),
  KEY `companies_domain_index` (`domain`),
  KEY `companies_industry_index` (`industry`),
  KEY `companies_type_index` (`type`),
  CONSTRAINT `companies_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `companies_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `companies`
--

ALTER TABLE `companies` ADD INDEX `companies_domain_tenant_unique` (`domain`);
ALTER TABLE `companies` ADD INDEX `companies_domain_tenant_unique` (`tenant_id`);
ALTER TABLE `companies` ADD INDEX `companies_tenant_id_index` (`tenant_id`);
ALTER TABLE `companies` ADD INDEX `companies_owner_id_index` (`owner_id`);
ALTER TABLE `companies` ADD INDEX `companies_domain_index` (`domain`);
ALTER TABLE `companies` ADD INDEX `companies_industry_index` (`industry`);
ALTER TABLE `companies` ADD INDEX `companies_type_index` (`type`);


--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `owner_id` bigint(20) unsigned NOT NULL,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `lifecycle_stage` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `tenant_id` bigint(20) unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contacts_email_index` (`email`),
  KEY `contacts_phone_index` (`phone`),
  KEY `contacts_tenant_id_index` (`tenant_id`),
  KEY `contacts_owner_id_index` (`owner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `contacts`
--

ALTER TABLE `contacts` ADD INDEX `contacts_email_index` (`email`);
ALTER TABLE `contacts` ADD INDEX `contacts_phone_index` (`phone`);
ALTER TABLE `contacts` ADD INDEX `contacts_tenant_id_index` (`tenant_id`);
ALTER TABLE `contacts` ADD INDEX `contacts_owner_id_index` (`owner_id`);


--
-- Table structure for table `deals`
--

DROP TABLE IF EXISTS `deals`;
CREATE TABLE IF NOT EXISTS `deals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `value` decimal(15,2) DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `expected_close_date` date DEFAULT NULL,
  `closed_date` date DEFAULT NULL,
  `close_reason` varchar(255) DEFAULT NULL,
  `probability` int(11) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `pipeline_id` bigint(20) unsigned NOT NULL,
  `stage_id` bigint(20) unsigned NOT NULL,
  `owner_id` bigint(20) unsigned NOT NULL,
  `contact_id` bigint(20) unsigned DEFAULT NULL,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deals_tenant_id_index` (`tenant_id`),
  KEY `deals_pipeline_id_index` (`pipeline_id`),
  KEY `deals_stage_id_index` (`stage_id`),
  KEY `deals_owner_id_index` (`owner_id`),
  KEY `deals_contact_id_index` (`contact_id`),
  KEY `deals_company_id_index` (`company_id`),
  KEY `deals_status_index` (`status`),
  KEY `deals_expected_close_date_index` (`expected_close_date`),
  CONSTRAINT `deals_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `deals_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`),
  CONSTRAINT `deals_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`),
  CONSTRAINT `deals_pipeline_id_foreign` FOREIGN KEY (`pipeline_id`) REFERENCES `pipelines` (`id`),
  CONSTRAINT `deals_stage_id_foreign` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `deals`
--

ALTER TABLE `deals` ADD INDEX `deals_tenant_id_index` (`tenant_id`);
ALTER TABLE `deals` ADD INDEX `deals_pipeline_id_index` (`pipeline_id`);
ALTER TABLE `deals` ADD INDEX `deals_stage_id_index` (`stage_id`);
ALTER TABLE `deals` ADD INDEX `deals_owner_id_index` (`owner_id`);
ALTER TABLE `deals` ADD INDEX `deals_contact_id_index` (`contact_id`);
ALTER TABLE `deals` ADD INDEX `deals_company_id_index` (`company_id`);
ALTER TABLE `deals` ADD INDEX `deals_status_index` (`status`);
ALTER TABLE `deals` ADD INDEX `deals_expected_close_date_index` (`expected_close_date`);


--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `failed_jobs`
--

ALTER TABLE `failed_jobs` ADD INDEX `failed_jobs_uuid_unique` (`uuid`);


--
-- Table structure for table `form_submissions`
--

DROP TABLE IF EXISTS `form_submissions`;
CREATE TABLE IF NOT EXISTS `form_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `contact_id` bigint(20) unsigned DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `ip_address` varchar(255) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `consent_given` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `form_submissions_form_id_created_at_index` (`form_id`,`created_at`),
  KEY `form_submissions_contact_id_created_at_index` (`contact_id`,`created_at`),
  CONSTRAINT `form_submissions_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `form_submissions_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `form_submissions`
--

ALTER TABLE `form_submissions` ADD INDEX `form_submissions_form_id_created_at_index` (`form_id`);
ALTER TABLE `form_submissions` ADD INDEX `form_submissions_form_id_created_at_index` (`created_at`);
ALTER TABLE `form_submissions` ADD INDEX `form_submissions_contact_id_created_at_index` (`contact_id`);
ALTER TABLE `form_submissions` ADD INDEX `form_submissions_contact_id_created_at_index` (`created_at`);


--
-- Table structure for table `forms`
--

DROP TABLE IF EXISTS `forms`;
CREATE TABLE IF NOT EXISTS `forms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `status` enum('active','draft','inactive') NOT NULL DEFAULT 'draft',
  `fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`fields`)),
  `consent_required` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `forms_created_by_foreign` (`created_by`),
  KEY `forms_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  CONSTRAINT `forms_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forms_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `forms`
--

ALTER TABLE `forms` ADD INDEX `forms_created_by_foreign` (`created_by`);
ALTER TABLE `forms` ADD INDEX `forms_tenant_id_created_at_index` (`tenant_id`);
ALTER TABLE `forms` ADD INDEX `forms_tenant_id_created_at_index` (`created_at`);


--
-- Table structure for table `idempotency_keys`
--

DROP TABLE IF EXISTS `idempotency_keys`;
CREATE TABLE IF NOT EXISTS `idempotency_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `route` varchar(255) NOT NULL,
  `key` varchar(255) NOT NULL,
  `response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`response`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_keys_user_id_route_key_unique` (`user_id`,`route`,`key`),
  KEY `idempotency_keys_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `idempotency_keys`
--

ALTER TABLE `idempotency_keys` ADD INDEX `idempotency_keys_user_id_route_key_unique` (`user_id`);
ALTER TABLE `idempotency_keys` ADD INDEX `idempotency_keys_user_id_route_key_unique` (`route`);
ALTER TABLE `idempotency_keys` ADD INDEX `idempotency_keys_user_id_route_key_unique` (`key`);
ALTER TABLE `idempotency_keys` ADD INDEX `idempotency_keys_created_at_index` (`created_at`);


--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `jobs`
--

ALTER TABLE `jobs` ADD INDEX `jobs_queue_index` (`queue`);


--
-- Table structure for table `list_members`
--

DROP TABLE IF EXISTS `list_members`;
CREATE TABLE IF NOT EXISTS `list_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `list_id` bigint(20) unsigned NOT NULL,
  `contact_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `list_members_list_id_contact_id_unique` (`list_id`,`contact_id`),
  KEY `list_members_list_id_created_at_index` (`list_id`,`created_at`),
  KEY `list_members_contact_id_created_at_index` (`contact_id`,`created_at`),
  CONSTRAINT `list_members_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `list_members_list_id_foreign` FOREIGN KEY (`list_id`) REFERENCES `lists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `list_members`
--

ALTER TABLE `list_members` ADD INDEX `list_members_list_id_contact_id_unique` (`list_id`);
ALTER TABLE `list_members` ADD INDEX `list_members_list_id_contact_id_unique` (`contact_id`);
ALTER TABLE `list_members` ADD INDEX `list_members_list_id_created_at_index` (`list_id`);
ALTER TABLE `list_members` ADD INDEX `list_members_list_id_created_at_index` (`created_at`);
ALTER TABLE `list_members` ADD INDEX `list_members_contact_id_created_at_index` (`contact_id`);
ALTER TABLE `list_members` ADD INDEX `list_members_contact_id_created_at_index` (`created_at`);


--
-- Table structure for table `lists`
--

DROP TABLE IF EXISTS `lists`;
CREATE TABLE IF NOT EXISTS `lists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('static','dynamic') NOT NULL,
  `rule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rule`)),
  `created_by` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lists_created_by_foreign` (`created_by`),
  KEY `lists_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `lists_type_tenant_id_index` (`type`,`tenant_id`),
  CONSTRAINT `lists_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lists_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `lists`
--

ALTER TABLE `lists` ADD INDEX `lists_created_by_foreign` (`created_by`);
ALTER TABLE `lists` ADD INDEX `lists_tenant_id_created_at_index` (`tenant_id`);
ALTER TABLE `lists` ADD INDEX `lists_tenant_id_created_at_index` (`created_at`);
ALTER TABLE `lists` ADD INDEX `lists_type_tenant_id_index` (`type`);
ALTER TABLE `lists` ADD INDEX `lists_type_tenant_id_index` (`tenant_id`);


--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
CREATE TABLE IF NOT EXISTS `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `model_has_permissions`
--

ALTER TABLE `model_has_permissions` ADD INDEX `model_has_permissions_model_id_model_type_index` (`model_id`);
ALTER TABLE `model_has_permissions` ADD INDEX `model_has_permissions_model_id_model_type_index` (`model_type`);


--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
CREATE TABLE IF NOT EXISTS `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `model_has_roles`
--

ALTER TABLE `model_has_roles` ADD INDEX `model_has_roles_model_id_model_type_index` (`model_id`);
ALTER TABLE `model_has_roles` ADD INDEX `model_has_roles_model_id_model_type_index` (`model_type`);


--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `permissions`
--

ALTER TABLE `permissions` ADD INDEX `permissions_name_guard_name_unique` (`name`);
ALTER TABLE `permissions` ADD INDEX `permissions_name_guard_name_unique` (`guard_name`);


--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=192 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `personal_access_tokens`
--

ALTER TABLE `personal_access_tokens` ADD INDEX `personal_access_tokens_token_unique` (`token`);
ALTER TABLE `personal_access_tokens` ADD INDEX `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`);
ALTER TABLE `personal_access_tokens` ADD INDEX `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_id`);
ALTER TABLE `personal_access_tokens` ADD INDEX `personal_access_tokens_expires_at_index` (`expires_at`);


--
-- Table structure for table `pipelines`
--

DROP TABLE IF EXISTS `pipelines`;
CREATE TABLE IF NOT EXISTS `pipelines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pipelines_tenant_id_index` (`tenant_id`),
  KEY `pipelines_created_by_index` (`created_by`),
  KEY `pipelines_is_active_index` (`is_active`),
  KEY `pipelines_sort_order_index` (`sort_order`),
  CONSTRAINT `pipelines_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `pipelines`
--

ALTER TABLE `pipelines` ADD INDEX `pipelines_tenant_id_index` (`tenant_id`);
ALTER TABLE `pipelines` ADD INDEX `pipelines_created_by_index` (`created_by`);
ALTER TABLE `pipelines` ADD INDEX `pipelines_is_active_index` (`is_active`);
ALTER TABLE `pipelines` ADD INDEX `pipelines_sort_order_index` (`sort_order`);


--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
CREATE TABLE IF NOT EXISTS `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `role_has_permissions`
--

ALTER TABLE `role_has_permissions` ADD INDEX `role_has_permissions_role_id_foreign` (`role_id`);


--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `roles`
--

ALTER TABLE `roles` ADD INDEX `roles_name_guard_name_unique` (`name`);
ALTER TABLE `roles` ADD INDEX `roles_name_guard_name_unique` (`guard_name`);


--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `sessions`
--

ALTER TABLE `sessions` ADD INDEX `sessions_user_id_index` (`user_id`);
ALTER TABLE `sessions` ADD INDEX `sessions_last_activity_index` (`last_activity`);


--
-- Table structure for table `stages`
--

DROP TABLE IF EXISTS `stages`;
CREATE TABLE IF NOT EXISTS `stages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(255) NOT NULL DEFAULT '#3B82F6',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `pipeline_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stages_tenant_id_index` (`tenant_id`),
  KEY `stages_pipeline_id_index` (`pipeline_id`),
  KEY `stages_created_by_index` (`created_by`),
  KEY `stages_is_active_index` (`is_active`),
  KEY `stages_sort_order_index` (`sort_order`),
  CONSTRAINT `stages_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `stages_pipeline_id_foreign` FOREIGN KEY (`pipeline_id`) REFERENCES `pipelines` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `stages`
--

ALTER TABLE `stages` ADD INDEX `stages_tenant_id_index` (`tenant_id`);
ALTER TABLE `stages` ADD INDEX `stages_pipeline_id_index` (`pipeline_id`);
ALTER TABLE `stages` ADD INDEX `stages_created_by_index` (`created_by`);
ALTER TABLE `stages` ADD INDEX `stages_is_active_index` (`is_active`);
ALTER TABLE `stages` ADD INDEX `stages_sort_order_index` (`sort_order`);


--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `due_date` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `owner_id` bigint(20) unsigned NOT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `related_type` varchar(255) DEFAULT NULL,
  `related_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_tenant_id_index` (`tenant_id`),
  KEY `tasks_owner_id_index` (`owner_id`),
  KEY `tasks_assigned_to_index` (`assigned_to`),
  KEY `tasks_related_type_related_id_index` (`related_type`,`related_id`),
  KEY `tasks_status_index` (`status`),
  KEY `tasks_priority_index` (`priority`),
  KEY `tasks_due_date_index` (`due_date`),
  CONSTRAINT `tasks_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `tasks`
--

ALTER TABLE `tasks` ADD INDEX `tasks_tenant_id_index` (`tenant_id`);
ALTER TABLE `tasks` ADD INDEX `tasks_owner_id_index` (`owner_id`);
ALTER TABLE `tasks` ADD INDEX `tasks_assigned_to_index` (`assigned_to`);
ALTER TABLE `tasks` ADD INDEX `tasks_related_type_related_id_index` (`related_type`);
ALTER TABLE `tasks` ADD INDEX `tasks_related_type_related_id_index` (`related_id`);
ALTER TABLE `tasks` ADD INDEX `tasks_status_index` (`status`);
ALTER TABLE `tasks` ADD INDEX `tasks_priority_index` (`priority`);
ALTER TABLE `tasks` ADD INDEX `tasks_due_date_index` (`due_date`);


--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `organization_name` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_status_created_at_index` (`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for table `users`
--

ALTER TABLE `users` ADD INDEX `users_email_unique` (`email`);
ALTER TABLE `users` ADD INDEX `users_status_created_at_index` (`status`);
ALTER TABLE `users` ADD INDEX `users_status_created_at_index` (`created_at`);


COMMIT;
