-- ============================================================
-- CSA Command Hub  |  MySQL / MariaDB dump (schema + demo data)
-- Database : csa_command_hub  (new, separate from any existing data)
-- Import   : phpMyAdmin > Import, or:  mysql -u root < csa_command_hub.sql
-- Login    : fauzi@csa.test / password  (all 14 users use "password")
-- Laravel  : `migrations` table is pre-filled, so run NO migrate command.
-- ============================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';
CREATE DATABASE IF NOT EXISTS `csa_command_hub` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `csa_command_hub`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `job_position` varchar(255) DEFAULT NULL,
  `role` varchar(40) NOT NULL DEFAULT 'BD Executive',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_code_unique` (`code`),
  KEY `users_role_index` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `payload` longtext NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `sector` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `parent` varchar(255) DEFAULT NULL,
  `relationship` varchar(255) NOT NULL DEFAULT 'Warm',
  `owner_id` bigint unsigned DEFAULT NULL,
  `last_engagement_at` timestamp NULL DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_code_unique` (`code`),
  KEY `customers_owner_id_foreign` (`owner_id`),
  CONSTRAINT `customers_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `buying_role` varchar(255) DEFAULT NULL,
  `division` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contacts_customer_id_foreign` (`customer_id`),
  CONSTRAINT `contacts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `partners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `classification` varchar(255) DEFAULT NULL,
  `preferred` tinyint(1) NOT NULL DEFAULT 0,
  `rating` tinyint unsigned NOT NULL DEFAULT 3,
  `status` varchar(255) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `partner_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_partner_id_foreign` (`partner_id`),
  CONSTRAINT `products_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `competitors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `encounters` int unsigned NOT NULL DEFAULT 0,
  `won_against` int unsigned NOT NULL DEFAULT 0,
  `lost_against` int unsigned NOT NULL DEFAULT 0,
  `notes` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `opportunities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(24) NOT NULL,
  `name` varchar(255) NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `owner_id` bigint unsigned DEFAULT NULL,
  `stage` varchar(255) NOT NULL,
  `value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `probability` tinyint unsigned NOT NULL DEFAULT 10,
  `next_action` varchar(255) DEFAULT NULL,
  `next_action_date` date DEFAULT NULL,
  `lost_reason` varchar(255) DEFAULT NULL,
  `competitor_id` bigint unsigned DEFAULT NULL,
  `is_renewal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `opportunities_code_unique` (`code`),
  KEY `opportunities_stage_index` (`stage`),
  KEY `opportunities_customer_id_foreign` (`customer_id`),
  KEY `opportunities_owner_id_foreign` (`owner_id`),
  KEY `opportunities_competitor_id_foreign` (`competitor_id`),
  CONSTRAINT `opportunities_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `opportunities_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `opportunities_competitor_id_foreign` FOREIGN KEY (`competitor_id`) REFERENCES `competitors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `opportunity_stage_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opportunity_id` bigint unsigned NOT NULL,
  `stage` varchar(255) NOT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `osh_opportunity_id_foreign` (`opportunity_id`),
  KEY `osh_changed_by_foreign` (`changed_by`),
  CONSTRAINT `osh_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `osh_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tenders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(24) NOT NULL,
  `title` varchar(255) NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `opportunity_id` bigint unsigned DEFAULT NULL,
  `owner_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `closing_at` datetime DEFAULT NULL,
  `risk` varchar(255) NOT NULL DEFAULT 'Low',
  `cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pricing_approved` tinyint(1) NOT NULL DEFAULT 0,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenders_code_unique` (`code`),
  KEY `tenders_status_index` (`status`),
  KEY `tenders_customer_id_foreign` (`customer_id`),
  KEY `tenders_opportunity_id_foreign` (`opportunity_id`),
  KEY `tenders_owner_id_foreign` (`owner_id`),
  CONSTRAINT `tenders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenders_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tenders_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tender_checklist_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tender_id` bigint unsigned NOT NULL,
  `label` varchar(255) NOT NULL,
  `mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `done` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tci_tender_id_foreign` (`tender_id`),
  CONSTRAINT `tci_tender_id_foreign` FOREIGN KEY (`tender_id`) REFERENCES `tenders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tender_status_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tender_id` bigint unsigned NOT NULL,
  `status` varchar(255) NOT NULL,
  `changed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tsh_tender_id_foreign` (`tender_id`),
  KEY `tsh_changed_by_foreign` (`changed_by`),
  CONSTRAINT `tsh_tender_id_foreign` FOREIGN KEY (`tender_id`) REFERENCES `tenders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tsh_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(24) NOT NULL,
  `type` varchar(255) NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `requester_id` bigint unsigned NOT NULL,
  `approver_id` bigint unsigned NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Pending',
  `snapshot` json DEFAULT NULL,
  `decision_note` text,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `approvals_code_unique` (`code`),
  KEY `approvals_status_index` (`status`),
  KEY `approvals_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `approvals_requester_id_foreign` (`requester_id`),
  KEY `approvals_approver_id_foreign` (`approver_id`),
  CONSTRAINT `approvals_requester_id_foreign` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`),
  CONSTRAINT `approvals_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `awards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(24) NOT NULL,
  `title` varchar(255) NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `opportunity_id` bigint unsigned DEFAULT NULL,
  `tender_id` bigint unsigned DEFAULT NULL,
  `value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `award_date` date NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'PENDING_EVIDENCE',
  `revenue_qualified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `awards_code_unique` (`code`),
  KEY `awards_revenue_qualified_index` (`revenue_qualified`),
  KEY `awards_customer_id_foreign` (`customer_id`),
  KEY `awards_opportunity_id_foreign` (`opportunity_id`),
  KEY `awards_tender_id_foreign` (`tender_id`),
  CONSTRAINT `awards_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `awards_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `awards_tender_id_foreign` FOREIGN KEY (`tender_id`) REFERENCES `tenders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `award_evidence` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `award_id` bigint unsigned NOT NULL,
  `type` enum('PO','LOA','OFFICIAL_AWARD') NOT NULL,
  `reference` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `award_evidence_award_id_foreign` (`award_id`),
  CONSTRAINT `award_evidence_award_id_foreign` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `projects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(24) NOT NULL,
  `award_id` bigint unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Kickoff',
  `owner_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projects_code_unique` (`code`),
  UNIQUE KEY `projects_award_id_unique` (`award_id`),
  KEY `projects_owner_id_foreign` (`owner_id`),
  CONSTRAINT `projects_award_id_foreign` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `projects_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_milestones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `weight` tinyint unsigned NOT NULL,
  `progress` tinyint unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pm_project_id_foreign` (`project_id`),
  CONSTRAINT `pm_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(255) NOT NULL,
  `award_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_invoice_no_unique` (`invoice_no`),
  KEY `invoices_award_id_foreign` (`award_id`),
  KEY `invoices_project_id_foreign` (`project_id`),
  CONSTRAINT `invoices_award_id_foreign` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `renewals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `expiry_date` date NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'ACTIVE',
  `owner_id` bigint unsigned DEFAULT NULL,
  `opportunity_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `renewals_opportunity_id_unique` (`opportunity_id`),
  KEY `renewals_customer_id_foreign` (`customer_id`),
  KEY `renewals_project_id_foreign` (`project_id`),
  KEY `renewals_owner_id_foreign` (`owner_id`),
  CONSTRAINT `renewals_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `renewals_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `renewals_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `renewals_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(24) NOT NULL,
  `title` varchar(255) NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `contact_id` bigint unsigned DEFAULT NULL,
  `channel` varchar(255) NOT NULL,
  `priority` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `assignee_id` bigint unsigned DEFAULT NULL,
  `opened_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sla_hours` int unsigned NOT NULL,
  `paused_minutes` int unsigned NOT NULL DEFAULT 0,
  `paused_at` timestamp NULL DEFAULT NULL,
  `confirmation_method` varchar(255) DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `confirmation_remarks` text,
  `csat` tinyint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cases_code_unique` (`code`),
  KEY `cases_status_index` (`status`),
  KEY `cases_customer_id_foreign` (`customer_id`),
  KEY `cases_contact_id_foreign` (`contact_id`),
  KEY `cases_assignee_id_foreign` (`assignee_id`),
  CONSTRAINT `cases_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cases_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cases_assignee_id_foreign` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `case_timeline` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint unsigned NOT NULL,
  `text` varchar(255) NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `case_timeline_case_id_foreign` (`case_id`),
  KEY `case_timeline_user_id_foreign` (`user_id`),
  CONSTRAINT `case_timeline_case_id_foreign` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `case_timeline_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kpis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `source` enum('SYSTEM','MANUAL_EVIDENCE') NOT NULL,
  `target` decimal(10,2) NOT NULL,
  `actual` decimal(10,2) NOT NULL,
  `weight` tinyint unsigned NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'On Track',
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `evidence` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kpis_user_id_foreign` (`user_id`),
  CONSTRAINT `kpis_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kpi_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kpi_id` bigint unsigned NOT NULL,
  `old_actual` decimal(10,2) NOT NULL,
  `new_actual` decimal(10,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `adjusted_by` bigint unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `kpi_adjustments_kpi_id_foreign` (`kpi_id`),
  KEY `kpi_adjustments_adjusted_by_foreign` (`adjusted_by`),
  CONSTRAINT `kpi_adjustments_kpi_id_foreign` FOREIGN KEY (`kpi_id`) REFERENCES `kpis` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kpi_adjustments_adjusted_by_foreign` FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(24) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `version` varchar(10) NOT NULL DEFAULT '1.0',
  `status` varchar(255) NOT NULL DEFAULT 'Draft',
  `restricted` tinyint(1) NOT NULL DEFAULT 0,
  `context_type` varchar(255) DEFAULT NULL,
  `context_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documents_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(255) DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `detail` json DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

-- ---------------------------- DATA ----------------------------
SET FOREIGN_KEY_CHECKS=0;
/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

INSERT INTO `approvals` (`id`, `code`, `type`, `subject_type`, `subject_id`, `requester_id`, `approver_id`, `status`, `snapshot`, `decision_note`, `decided_at`, `created_at`, `updated_at`) VALUES (1,'APR-2026-000001','Tender Pricing/Submission','tender',3,4,2,'Pending','{\"summary\": \"Controlled submission snapshot #1\", \"amount\": 500000}',NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,'APR-2026-000002','Bid/No-Bid',NULL,NULL,5,2,'Pending','{\"summary\": \"Controlled submission snapshot #2\", \"amount\": 625000}',NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,'APR-2026-000003','KPI Manual',NULL,NULL,6,2,'Returned','{\"summary\": \"Controlled submission snapshot #3\", \"amount\": 750000}',NULL,'2026-10-17 09:00:00','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,'APR-2026-000004','Event/Budget',NULL,NULL,4,2,'Approved','{\"summary\": \"Controlled submission snapshot #4\", \"amount\": 875000}',NULL,'2026-10-16 09:00:00','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,'APR-2026-000005','Major Opportunity Value Change',NULL,NULL,5,2,'Pending','{\"summary\": \"Controlled submission snapshot #5\", \"amount\": 1000000}',NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,'APR-2026-000006','MoM',NULL,NULL,6,2,'Rejected','{\"summary\": \"Controlled submission snapshot #6\", \"amount\": 1125000}',NULL,'2026-10-14 09:00:00','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(7,'APR-2026-000007','Award/Loss',NULL,NULL,4,2,'Pending','{\"summary\": \"Controlled submission snapshot #7\", \"amount\": 1250000}',NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(8,'APR-2026-000008','Quotation',NULL,NULL,5,2,'Approved','{\"summary\": \"Controlled submission snapshot #8\", \"amount\": 1375000}',NULL,'2026-10-12 09:00:00','2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `detail`, `ip`, `created_at`) VALUES (1,2,'LOGIN',NULL,NULL,NULL,'127.0.0.1','2026-10-19 09:00:00'),
(2,2,'TENDER_SUBMITTED','tender',4,NULL,'127.0.0.1','2026-10-19 09:00:00'),
(3,2,'AWARD_QUALIFIED','award',1,NULL,'127.0.0.1','2026-10-19 09:00:00'),
(4,2,'ACCESS_DENIED','document',1,NULL,'127.0.0.1','2026-10-19 09:00:00');

INSERT INTO `award_evidence` (`id`, `award_id`, `type`, `reference`, `created_at`, `updated_at`) VALUES (1,1,'PO','PO-2026-100','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,2,'LOA','LOA-2026-101','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,3,'PO','PO-2026-102','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,4,'LOA','LOA-2026-103','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,5,'PO','PO-2026-104','2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `awards` (`id`, `code`, `title`, `customer_id`, `opportunity_id`, `tender_id`, `value`, `award_date`, `status`, `revenue_qualified`, `created_at`, `updated_at`) VALUES (1,'AWD-2026-000001','Award — MyGeoServe State Expansion',2,2,4,900000.00,'2026-07-21','QUALIFIED',1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,'AWD-2026-000002','Award — JPS Data Lake & Integration',3,3,5,1400000.00,'2026-08-03','QUALIFIED',1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,'AWD-2026-000003','Award — KPKT Datasarana Platform',4,4,6,1900000.00,'2026-08-16','QUALIFIED',1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,'AWD-2026-000004','Award — JPS URS + LiDAR Survey',5,5,7,2400000.00,'2026-08-29','QUALIFIED',1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,'AWD-2026-000005','Award — JPS GIS Retender',6,6,NULL,2900000.00,'2026-09-11','QUALIFIED',1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,'AWD-2026-000006','Award — National IFSAR Programme',7,7,NULL,3400000.00,'2026-09-24','PENDING_EVIDENCE',0,'2026-10-19 09:00:00','2026-10-19 09:00:00');



INSERT INTO `case_timeline` (`id`, `case_id`, `text`, `user_id`, `created_at`) VALUES (1,1,'Case opened',7,'2026-10-18 09:00:00'),
(2,2,'Case opened',7,'2026-10-17 09:00:00'),
(3,3,'Case opened',7,'2026-10-16 09:00:00'),
(4,4,'Case opened',7,'2026-10-15 09:00:00'),
(5,5,'Case opened',7,'2026-10-14 09:00:00'),
(6,6,'Case opened',7,'2026-10-13 09:00:00'),
(7,7,'Case opened',7,'2026-10-12 09:00:00'),
(8,8,'Case opened',7,'2026-10-11 09:00:00'),
(9,9,'Case opened',7,'2026-10-10 09:00:00'),
(10,10,'Case opened',7,'2026-10-09 09:00:00'),
(11,11,'Case opened',7,'2026-10-08 09:00:00'),
(12,12,'Case opened',7,'2026-10-07 09:00:00'),
(13,13,'Case opened',7,'2026-10-06 09:00:00');

INSERT INTO `cases` (`id`, `code`, `title`, `customer_id`, `contact_id`, `channel`, `priority`, `status`, `assignee_id`, `opened_at`, `sla_hours`, `paused_minutes`, `paused_at`, `confirmation_method`, `confirmed_at`, `confirmation_remarks`, `csat`, `created_at`, `updated_at`) VALUES (1,'CAS-2026-000001','PACS access issue #1',1,NULL,'Phone','Low','Open',11,'2026-10-18 09:00:00',48,0,NULL,NULL,NULL,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,'CAS-2026-000002','Data sync enquiry #2',2,NULL,'Email','Medium','Assigned',12,'2026-10-17 09:00:00',24,0,NULL,NULL,NULL,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,'CAS-2026-000003','Project support request #3',3,NULL,'WhatsApp','High','In Progress',13,'2026-10-16 09:00:00',8,0,NULL,NULL,NULL,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,'CAS-2026-000004','License assistance #4',4,NULL,'Internal Referral','Critical','Pending Customer',11,'2026-10-15 09:00:00',4,0,'2026-10-19 03:00:00',NULL,NULL,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,'CAS-2026-000005','PACS access issue #5',5,NULL,'Phone','Low','Pending Internal',12,'2026-10-14 09:00:00',48,0,NULL,NULL,NULL,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,'CAS-2026-000006','Data sync enquiry #6',6,NULL,'Email','Medium','Resolved',13,'2026-10-13 09:00:00',24,0,NULL,NULL,NULL,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(7,'CAS-2026-000007','Project support request #7',7,NULL,'WhatsApp','High','Closed',11,'2026-10-12 09:00:00',8,0,NULL,'Email','2026-10-18 09:00:00','Customer confirmed resolution',4,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(8,'CAS-2026-000008','License assistance #8',8,NULL,'Internal Referral','Critical','Open',12,'2026-10-11 09:00:00',4,0,NULL,NULL,NULL,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(9,'CAS-2026-000009','PACS access issue #9',9,NULL,'Phone','Low','Assigned',13,'2026-10-10 09:00:00',48,0,NULL,NULL,NULL,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(10,'CAS-2026-000010','Data sync enquiry #10',10,NULL,'Email','Medium','In Progress',11,'2026-10-09 09:00:00',24,0,NULL,NULL,NULL,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(11,'CAS-2026-000011','Project support request #11',11,NULL,'WhatsApp','High','Pending Customer',12,'2026-10-08 09:00:00',8,0,'2026-10-19 03:00:00',NULL,NULL,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(12,'CAS-2026-000012','License assistance #12',12,NULL,'Internal Referral','Critical','Pending Internal',13,'2026-10-07 09:00:00',4,0,NULL,NULL,NULL,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(13,'CAS-2026-000013','PACS access issue #13',13,NULL,'Phone','Low','Resolved',11,'2026-10-06 09:00:00',48,0,NULL,NULL,NULL,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `competitors` (`id`, `name`, `encounters`, `won_against`, `lost_against`, `notes`, `created_at`, `updated_at`) VALUES (1,'MAP2U',2,0,1,'Lawful market intelligence only.','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,'Antaragrafik Systems',3,1,2,'Lawful market intelligence only.','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,'Vendor Alpha',4,2,0,'Lawful market intelligence only.','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,'Vendor Beta',5,0,1,'Lawful market intelligence only.','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,'Systems Integrator X',6,1,2,'Lawful market intelligence only.','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,'Geospatial Consortium Y',7,2,0,'Lawful market intelligence only.','2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `contacts` (`id`, `customer_id`, `name`, `email`, `mobile`, `buying_role`, `division`, `created_at`, `updated_at`) VALUES (1,1,'Dato Ahmad 1','contact1@example.gov.my','011-5550010','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,1,'Dato Ahmad 2','contact2@example.gov.my','012-5550011','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,2,'Puan Siti 1','contact3@example.gov.my','013-5550012','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,2,'Puan Siti 2','contact4@example.gov.my','014-5550013','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,3,'Encik Hafiz 1','contact5@example.gov.my','015-5550014','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,3,'Encik Hafiz 2','contact6@example.gov.my','016-5550015','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(7,4,'Puan Nadia 1','contact7@example.gov.my','017-5550016','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(8,4,'Puan Nadia 2','contact8@example.gov.my','018-5550017','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(9,5,'Encik Razak 1','contact9@example.gov.my','019-5550018','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(10,5,'Encik Razak 2','contact10@example.gov.my','011-5550019','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(11,6,'Dato Ahmad 1','contact11@example.gov.my','012-5550020','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(12,6,'Dato Ahmad 2','contact12@example.gov.my','013-5550021','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(13,7,'Puan Siti 1','contact13@example.gov.my','014-5550022','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(14,7,'Puan Siti 2','contact14@example.gov.my','015-5550023','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(15,8,'Encik Hafiz 1','contact15@example.gov.my','016-5550024','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(16,8,'Encik Hafiz 2','contact16@example.gov.my','017-5550025','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(17,9,'Puan Nadia 1','contact17@example.gov.my','018-5550026','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(18,9,'Puan Nadia 2','contact18@example.gov.my','019-5550027','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(19,10,'Encik Razak 1','contact19@example.gov.my','011-5550028','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(20,10,'Encik Razak 2','contact20@example.gov.my','012-5550029','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(21,11,'Dato Ahmad 1','contact21@example.gov.my','013-5550030','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(22,11,'Dato Ahmad 2','contact22@example.gov.my','014-5550031','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(23,12,'Puan Siti 1','contact23@example.gov.my','015-5550032','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(24,12,'Puan Siti 2','contact24@example.gov.my','016-5550033','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(25,13,'Encik Hafiz 1','contact25@example.gov.my','017-5550034','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(26,13,'Encik Hafiz 2','contact26@example.gov.my','018-5550035','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(27,14,'Puan Nadia 1','contact27@example.gov.my','019-5550036','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(28,14,'Puan Nadia 2','contact28@example.gov.my','011-5550037','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(29,15,'Encik Razak 1','contact29@example.gov.my','012-5550038','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(30,15,'Encik Razak 2','contact30@example.gov.my','013-5550039','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(31,16,'Dato Ahmad 1','contact31@example.gov.my','014-5550040','Decision Maker','ICT','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(32,16,'Dato Ahmad 2','contact32@example.gov.my','015-5550041','Technical Evaluator','Planning','2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `customers` (`id`, `code`, `name`, `type`, `sector`, `state`, `parent`, `relationship`, `owner_id`, `last_engagement_at`, `archived`, `created_at`, `updated_at`) VALUES (1,'CUS-000001','Jabatan Ukur dan Pemetaan Malaysia (JUPEM)','Federal Government','Geospatial','Federal','Government of Malaysia','Strong',4,'2026-10-07 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,'CUS-000002','Jabatan Pengairan dan Saliran Malaysia (JPS)','Federal Government','Water','Federal','Government of Malaysia','Good',5,'2026-10-03 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,'CUS-000003','Jabatan Perhutanan Semenanjung Malaysia (JPSM)','Federal Government','Forestry','Federal','Government of Malaysia','Warm',3,'2026-09-29 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,'CUS-000004','METMalaysia','Federal Government','Meteorology','Federal','Government of Malaysia','Strong',4,'2026-09-25 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,'CUS-000005','Kementerian Perumahan dan Kerajaan Tempatan (KPKT)','Federal Government','Local Government','Federal','Government of Malaysia','Good',5,'2026-09-21 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,'CUS-000006','NADMA','Federal Government','Disaster','Federal','Government of Malaysia','Warm',3,'2026-09-17 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(7,'CUS-000007','NAHRIM','Federal Government','Research','Federal','Government of Malaysia','Strong',4,'2026-09-13 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(8,'CUS-000008','PLANMalaysia','Federal Government','Planning','Federal','Government of Malaysia','Good',5,'2026-09-09 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(9,'CUS-000009','Tenaga Nasional Berhad (TNB)','GLC','Utility','Federal','Government of Malaysia','Warm',3,'2026-09-05 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(10,'CUS-000010','Air Selangor','GLC','Utility','Selangor',NULL,'Strong',4,'2026-09-01 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(11,'CUS-000011','Majlis Bandaraya Subang Jaya (MBSJ)','Local Municipality','Municipal','Selangor',NULL,'Good',5,'2026-08-28 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(12,'CUS-000012','Majlis Bandaraya Johor Bahru (MBJB)','Local Municipality','Municipal','Selangor',NULL,'Warm',3,'2026-08-24 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(13,'CUS-000013','Majlis Bandaraya Pulau Pinang (MBPP)','Local Municipality','Municipal','Selangor',NULL,'Strong',4,'2026-08-20 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(14,'CUS-000014','JAKOA','Federal Government','Indigenous Affairs','Selangor',NULL,'Good',5,'2026-08-16 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(15,'CUS-000015','FGV Holdings','GLC','Agriculture','Selangor',NULL,'Warm',3,'2026-08-12 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(16,'CUS-000016','KUSEL','GLC','Utility','Selangor',NULL,'Strong',4,'2026-08-08 09:00:00',0,'2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `documents` (`id`, `code`, `title`, `category`, `version`, `status`, `restricted`, `context_type`, `context_id`, `created_at`, `updated_at`) VALUES (1,'DOC-2026-000001','Tender Pricing Strategy 1','Proposal','1.0','Draft',1,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,'DOC-2026-000002','LOA / Award Evidence 2','Award Evidence','2.0','Under Review',0,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,'DOC-2026-000003','Customer Meeting Minutes 3','MoM','3.0','Approved',0,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,'DOC-2026-000004','Technical Proposal 4','Tender','1.0','Final',0,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,'DOC-2026-000005','Project UAT Document 5','Project','2.0','Superseded',1,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,'DOC-2026-000006','Partner Agreement 6','Agreement','3.0','Draft',0,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(7,'DOC-2026-000007','KPI Evidence 7','KPI','1.0','Under Review',0,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(8,'DOC-2026-000008','Tender Pricing Strategy 8','Proposal','2.0','Approved',0,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(9,'DOC-2026-000009','LOA / Award Evidence 9','Award Evidence','3.0','Final',1,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(10,'DOC-2026-000010','Customer Meeting Minutes 10','MoM','1.0','Superseded',0,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(11,'DOC-2026-000011','Technical Proposal 11','Tender','2.0','Draft',0,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(12,'DOC-2026-000012','Project UAT Document 12','Project','3.0','Under Review',0,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(13,'DOC-2026-000013','Partner Agreement 13','Agreement','1.0','Approved',1,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(14,'DOC-2026-000014','KPI Evidence 14','KPI','2.0','Final',0,NULL,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00');


INSERT INTO `invoices` (`id`, `invoice_no`, `award_id`, `project_id`, `amount`, `paid`, `invoice_date`, `due_date`, `created_at`, `updated_at`) VALUES (1,'INV/26/1000',1,1,180000.00,0.00,'2026-08-20','2026-09-29','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,'INV/26/1001',2,2,250000.00,50000.00,'2026-08-25','2026-10-07','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,'INV/26/1002',3,3,320000.00,220000.00,'2026-08-30','2026-10-15','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,'INV/26/1003',4,4,390000.00,390000.00,'2026-09-04','2026-10-23','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,'INV/26/1004',5,5,460000.00,0.00,'2026-09-09','2026-10-31','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,'INV/26/1005',1,1,530000.00,180000.00,'2026-09-14','2026-11-08','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(7,'INV/26/1006',2,2,600000.00,300000.00,'2026-09-19','2026-11-16','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(8,'INV/26/1007',3,3,670000.00,0.00,'2026-09-24','2026-11-24','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(9,'INV/26/1008',4,4,740000.00,460000.00,'2026-09-29','2026-12-02','2026-10-19 09:00:00','2026-10-19 09:00:00');




INSERT INTO `kpis` (`id`, `user_id`, `name`, `source`, `target`, `actual`, `weight`, `status`, `locked`, `evidence`, `created_at`, `updated_at`) VALUES (1,2,'Team Performance Achievement Rate','MANUAL_EVIDENCE',100.00,82.00,25,'On Track',0,'HR Grade B evidence required','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,4,'Completed Business Development Activities','SYSTEM',24.00,19.00,20,'On Track',0,'Drill-down to completed activities','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,4,'Tender / Opportunity Execution Quality','SYSTEM',90.00,78.00,20,'At Risk',0,'System-derived workflow evidence','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,5,'Customer Engagement Discipline','SYSTEM',20.00,17.00,20,'On Track',1,'Locked Q2 result','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,6,'Sales Coordination & Data Completeness','MANUAL_EVIDENCE',95.00,92.00,25,'On Track',0,'Monthly checklist and evidence','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,7,'Contact Centre SLA Compliance','SYSTEM',95.00,91.00,30,'On Track',0,'Case SLA drill-down','2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_10_05_000001_create_csa_tables',1);

INSERT INTO `opportunities` (`id`, `code`, `name`, `customer_id`, `owner_id`, `stage`, `value`, `probability`, `next_action`, `next_action_date`, `lost_reason`, `competitor_id`, `is_renewal`, `created_at`, `updated_at`) VALUES (1,'OPP-2026-000001','JUPEM DRC Storage Expansion 7PB',1,4,'Lead Opportunity',8500000.00,10,'Courtesy visit','2026-10-16',NULL,1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,'OPP-2026-000002','MyGeoServe State Expansion',2,5,'Solution Presentation',885000.00,20,'Technical workshop','2026-10-17',NULL,NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,'OPP-2026-000003','JPS Data Lake & Integration',3,3,'Proposal & Quotation Submission',1120000.00,35,'Follow-up budget','2026-10-18',NULL,NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,'OPP-2026-000004','KPKT Datasarana Platform',4,4,'Budget Approval & Evaluation',1355000.00,45,'Prepare proposal','2026-10-19',NULL,4,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,'OPP-2026-000005','JPS URS + LiDAR Survey',5,5,'Specification Tender',1590000.00,55,'Tender readiness review','2026-10-20',NULL,NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,'OPP-2026-000006','JPS GIS Retender',6,3,'Release Tender',1825000.00,65,'Courtesy visit','2026-10-21',NULL,NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(7,'OPP-2026-000007','National IFSAR Programme',7,4,'Document Tender',2060000.00,75,'Technical workshop','2026-10-22',NULL,1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(8,'OPP-2026-000008','Forestry LiDAR & Multispectral',8,5,'Tender Evaluation',2295000.00,80,'Follow-up budget','2026-10-23',NULL,NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(9,'OPP-2026-000009','METMalaysia NWP Modernisation',9,3,'Negotiable/Shortlisted',2530000.00,90,'Prepare proposal','2026-10-16',NULL,NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(10,'OPP-2026-000010','JAKOA DNS/DHCP/IPAM',10,4,'Lead Opportunity',2765000.00,10,'Tender readiness review','2026-10-17',NULL,4,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(11,'OPP-2026-000011','TNB Geospatial Platform',11,5,'Solution Presentation',3000000.00,20,'Courtesy visit','2026-10-18',NULL,NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(12,'OPP-2026-000012','Air Selangor Spatial Analytics',12,3,'Proposal & Quotation Submission',3235000.00,35,'Technical workshop','2026-10-19',NULL,NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(13,'OPP-2026-000013','MBSJ Smart City GIS',13,4,'Budget Approval & Evaluation',3470000.00,45,'Follow-up budget','2026-10-20',NULL,1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(14,'OPP-2026-000014','MBJB Property Intelligence',14,5,'Specification Tender',3705000.00,55,'Prepare proposal','2026-10-21',NULL,NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(15,'OPP-2026-000015','MBPP Planning GIS',15,3,'Release Tender',3940000.00,65,'Tender readiness review','2026-10-22',NULL,NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(16,'OPP-2026-000016','FGV Enterprise Data Platform',16,4,'Document Tender',4175000.00,75,'Courtesy visit','2026-10-23',NULL,4,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(17,'OPP-2026-000017','KUSEL Utility Corridor Intelligence',1,5,'Award',4410000.00,80,'Technical workshop','2026-10-16',NULL,NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(18,'OPP-2026-000018','NADMA Disaster Data Hub',2,3,'Lost',4645000.00,90,'Follow-up budget','2026-10-17','Price / strategic fit',NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(19,'OPP-2026-000019','NAHRIM Hydro Intelligence',3,4,'Award',4880000.00,10,'Prepare proposal','2026-10-18',NULL,1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(20,'OPP-2026-000020','PLANMalaysia Urban Analytics',4,5,'Lost',5115000.00,20,'Tender readiness review','2026-10-19','Price / strategic fit',NULL,0,'2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `opportunity_stage_history` (`id`, `opportunity_id`, `stage`, `changed_by`, `reason`, `created_at`) VALUES (1,1,'Lead Opportunity',4,'Opportunity identified','2026-10-19 09:00:00'),
(2,2,'Lead Opportunity',5,'Opportunity identified','2026-10-19 09:00:00'),
(3,3,'Lead Opportunity',4,'Opportunity identified','2026-10-19 09:00:00'),
(4,4,'Lead Opportunity',5,'Opportunity identified','2026-10-19 09:00:00'),
(5,5,'Lead Opportunity',4,'Opportunity identified','2026-10-19 09:00:00'),
(6,6,'Lead Opportunity',5,'Opportunity identified','2026-10-19 09:00:00'),
(7,7,'Lead Opportunity',4,'Opportunity identified','2026-10-19 09:00:00'),
(8,8,'Lead Opportunity',5,'Opportunity identified','2026-10-19 09:00:00'),
(9,9,'Lead Opportunity',4,'Opportunity identified','2026-10-19 09:00:00'),
(10,10,'Lead Opportunity',5,'Opportunity identified','2026-10-19 09:00:00'),
(11,11,'Lead Opportunity',4,'Opportunity identified','2026-10-19 09:00:00'),
(12,12,'Lead Opportunity',5,'Opportunity identified','2026-10-19 09:00:00'),
(13,13,'Lead Opportunity',4,'Opportunity identified','2026-10-19 09:00:00'),
(14,14,'Lead Opportunity',5,'Opportunity identified','2026-10-19 09:00:00'),
(15,15,'Lead Opportunity',4,'Opportunity identified','2026-10-19 09:00:00'),
(16,16,'Lead Opportunity',5,'Opportunity identified','2026-10-19 09:00:00'),
(17,17,'Lead Opportunity',4,'Opportunity identified','2026-10-19 09:00:00'),
(18,18,'Lead Opportunity',5,'Opportunity identified','2026-10-19 09:00:00'),
(19,19,'Lead Opportunity',4,'Opportunity identified','2026-10-19 09:00:00'),
(20,20,'Lead Opportunity',5,'Opportunity identified','2026-10-19 09:00:00');

INSERT INTO `partners` (`id`, `name`, `classification`, `preferred`, `rating`, `status`, `created_at`, `updated_at`) VALUES (1,'Hexagon Geospatial','Principal, OEM',1,3,'Active','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,'Esri Malaysia','Technology Partner',1,4,'Active','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,'HPE / VSTECS Astar','Distributor',1,5,'Active','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,'Intermap Technologies','Principal',1,3,'Active','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,'Maret Survey','Subcontractor',0,4,'Active','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,'Synspective','Technology Partner',0,5,'Active','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(7,'MetaSensing','Technology Partner',0,3,'Active','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(8,'DJI Enterprise','OEM',0,4,'Active','2026-10-19 09:00:00','2026-10-19 09:00:00');


INSERT INTO `products` (`id`, `name`, `category`, `partner_id`, `created_at`, `updated_at`) VALUES (1,'Enterprise GIS Platform','GIS',1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,'Geospatial Data Lake','Data',2,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,'Object Storage Platform','Infrastructure',3,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,'AI Analytics Platform','AI',4,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,'UAV LiDAR Survey','Survey',5,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,'Satellite InSAR','Remote Sensing',6,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(7,'IFSAR Mapping','GIS',7,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(8,'IPAM & Network Services','Data',8,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(9,'Smart City Platform','Infrastructure',1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(10,'Contact Centre Platform','AI',2,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(11,'Cybersecurity Services','Survey',3,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(12,'Data Integration & ETL','Remote Sensing',4,'2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `project_milestones` (`id`, `project_id`, `name`, `weight`, `progress`, `created_at`, `updated_at`) VALUES (1,1,'Kickoff',20,100,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,1,'Delivery',50,70,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,1,'UAT / Closure',30,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,2,'Kickoff',20,100,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,2,'Delivery',50,100,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,2,'UAT / Closure',30,80,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(7,3,'Kickoff',20,100,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(8,3,'Delivery',50,30,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(9,3,'UAT / Closure',30,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(10,4,'Kickoff',20,100,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(11,4,'Delivery',50,100,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(12,4,'UAT / Closure',30,100,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(13,5,'Kickoff',20,100,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(14,5,'Delivery',50,55,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(15,5,'UAT / Closure',30,0,'2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `projects` (`id`, `code`, `award_id`, `title`, `status`, `owner_id`, `created_at`, `updated_at`) VALUES (1,'PRJ-2026-000001',1,'Project — MyGeoServe State Expansion','In Progress',4,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,'PRJ-2026-000002',2,'Project — JPS Data Lake & Integration','UAT',5,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,'PRJ-2026-000003',3,'Project — KPKT Datasarana Platform','Kickoff',4,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,'PRJ-2026-000004',4,'Project — JPS URS + LiDAR Survey','Delivered',5,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,'PRJ-2026-000005',5,'Project — JPS GIS Retender','In Progress',4,'2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `renewals` (`id`, `customer_id`, `project_id`, `type`, `expiry_date`, `status`, `owner_id`, `opportunity_id`, `created_at`, `updated_at`) VALUES (1,3,1,'Maintenance','2026-11-13','DUE_SOON',4,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,4,2,'License','2026-12-13','DUE_SOON',5,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,5,3,'Warranty','2027-01-12','DUE_SOON',4,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,6,4,'Subscription','2027-03-18','DUE_SOON',5,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,7,5,'Maintenance','2027-05-17','ACTIVE',4,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,8,1,'License','2026-10-09','EXPIRED',5,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00');


INSERT INTO `tender_checklist_items` (`id`, `tender_id`, `label`, `mandatory`, `done`, `created_at`, `updated_at`) VALUES (1,1,'Tender document purchased/received',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,1,'Mandatory company documents',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,1,'Technical proposal finalised',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,1,'Commercial pricing finalised',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,1,'Submission packaging verified',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,2,'Tender document purchased/received',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(7,2,'Mandatory company documents',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(8,2,'Technical proposal finalised',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(9,2,'Commercial pricing finalised',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(10,2,'Submission packaging verified',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(11,3,'Tender document purchased/received',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(12,3,'Mandatory company documents',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(13,3,'Technical proposal finalised',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(14,3,'Commercial pricing finalised',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(15,3,'Submission packaging verified',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(16,4,'Tender document purchased/received',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(17,4,'Mandatory company documents',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(18,4,'Technical proposal finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(19,4,'Commercial pricing finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(20,4,'Submission packaging verified',1,0,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(21,5,'Tender document purchased/received',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(22,5,'Mandatory company documents',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(23,5,'Technical proposal finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(24,5,'Commercial pricing finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(25,5,'Submission packaging verified',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(26,6,'Tender document purchased/received',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(27,6,'Mandatory company documents',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(28,6,'Technical proposal finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(29,6,'Commercial pricing finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(30,6,'Submission packaging verified',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(31,7,'Tender document purchased/received',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(32,7,'Mandatory company documents',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(33,7,'Technical proposal finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(34,7,'Commercial pricing finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(35,7,'Submission packaging verified',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(36,8,'Tender document purchased/received',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(37,8,'Mandatory company documents',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(38,8,'Technical proposal finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(39,8,'Commercial pricing finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(40,8,'Submission packaging verified',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(41,9,'Tender document purchased/received',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(42,9,'Mandatory company documents',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(43,9,'Technical proposal finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(44,9,'Commercial pricing finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(45,9,'Submission packaging verified',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(46,10,'Tender document purchased/received',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(47,10,'Mandatory company documents',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(48,10,'Technical proposal finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(49,10,'Commercial pricing finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(50,10,'Submission packaging verified',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(51,11,'Tender document purchased/received',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(52,11,'Mandatory company documents',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(53,11,'Technical proposal finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(54,11,'Commercial pricing finalised',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(55,11,'Submission packaging verified',1,1,'2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `tender_status_history` (`id`, `tender_id`, `status`, `changed_by`, `created_at`) VALUES (1,1,'PREPARATION',4,'2026-10-19 09:00:00'),
(2,2,'READY_FOR_SUBMISSION',5,'2026-10-19 09:00:00'),
(3,3,'SUBMISSION_APPROVAL',3,'2026-10-19 09:00:00'),
(4,4,'SUBMITTED',4,'2026-10-19 09:00:00'),
(5,5,'TECHNICAL_EVALUATION',5,'2026-10-19 09:00:00'),
(6,6,'COMMERCIAL_EVALUATION',3,'2026-10-19 09:00:00'),
(7,7,'SHORTLISTED',4,'2026-10-19 09:00:00'),
(8,8,'AWARDED',5,'2026-10-19 09:00:00'),
(9,9,'LOST',3,'2026-10-19 09:00:00'),
(10,10,'CANCELLED',4,'2026-10-19 09:00:00'),
(11,11,'BID_ASSESSMENT',5,'2026-10-19 09:00:00');

INSERT INTO `tenders` (`id`, `code`, `title`, `customer_id`, `opportunity_id`, `owner_id`, `status`, `closing_at`, `risk`, `cost`, `price`, `pricing_approved`, `submitted_at`, `created_at`, `updated_at`) VALUES (1,'TND-2026-000001','JUPEM DRC Storage Expansion 7PB — Tender',1,1,4,'PREPARATION','2026-10-23 09:00:00','High',550000.00,720000.00,0,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(2,'TND-2026-000002','MyGeoServe State Expansion — Tender',2,2,5,'READY_FOR_SUBMISSION','2026-10-31 09:00:00','High',640000.00,840000.00,1,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(3,'TND-2026-000003','JPS Data Lake & Integration — Tender',3,3,3,'SUBMISSION_APPROVAL','2026-11-08 09:00:00','High',730000.00,960000.00,0,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(4,'TND-2026-000004','KPKT Datasarana Platform — Tender',4,4,4,'SUBMITTED','2026-11-16 09:00:00','Low',820000.00,1080000.00,1,'2026-10-02 09:00:00','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(5,'TND-2026-000005','JPS URS + LiDAR Survey — Tender',5,5,5,'TECHNICAL_EVALUATION','2026-11-24 09:00:00','Medium',910000.00,1200000.00,1,'2026-10-03 09:00:00','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(6,'TND-2026-000006','JPS GIS Retender — Tender',6,6,3,'COMMERCIAL_EVALUATION','2026-12-02 09:00:00','Low',1000000.00,1320000.00,1,'2026-10-04 09:00:00','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(7,'TND-2026-000007','National IFSAR Programme — Tender',7,7,4,'SHORTLISTED','2026-12-10 09:00:00','Low',1090000.00,1440000.00,1,'2026-10-05 09:00:00','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(8,'TND-2026-000008','Forestry LiDAR & Multispectral — Tender',8,8,5,'AWARDED','2026-12-18 09:00:00','Low',1180000.00,1560000.00,1,'2026-10-06 09:00:00','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(9,'TND-2026-000009','METMalaysia NWP Modernisation — Tender',9,9,3,'LOST','2026-12-26 09:00:00','Low',1270000.00,1680000.00,1,'2026-10-07 09:00:00','2026-10-19 09:00:00','2026-10-19 09:00:00'),
(10,'TND-2026-000010','JAKOA DNS/DHCP/IPAM — Tender',10,10,4,'CANCELLED','2027-01-03 09:00:00','Low',1360000.00,1800000.00,1,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00'),
(11,'TND-2026-000011','TNB Geospatial Platform — Tender',11,11,5,'BID_ASSESSMENT','2027-01-11 09:00:00','Low',1450000.00,1920000.00,1,NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00');

INSERT INTO `users` (`id`, `code`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `job_position`, `role`, `active`) VALUES (1,'USR-001','Stanley Chin Kok Hoon','stanley@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','SVP – CSA','Management',1),
(2,'USR-002','Muhamad Fauzi Bin Jamhori','fauzi@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','CSA HOD / AVP','HOD',1),
(3,'USR-003','Wan Nuzul Wan Mansor','nuzul@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','Senior Manager – BD','BD Manager',1),
(4,'USR-004','Amir Zafran Bin Baharudin','amir@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','Manager – BD','BD Manager',1),
(5,'USR-005','Muhammad Asyafiq Abdul Latiff','asyafiq@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','Manager – BD','BD Manager',1),
(6,'USR-006','Nurzam Syahirah Binti Mohd Zamri','nurzam@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','Coordinator','Sales Admin / Inside Sales',1),
(7,'USR-007','Muhammad Amin Bin Mansor','amin@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','Manager – Helpdesk','CC Manager',1),
(8,'USR-008','Nur Aisyah Binti Che Mohamad Rahimi','aisyah@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','Team Lead – Helpdesk','CC Team Lead',1),
(9,'USR-009','Nurfazlina Binti Zulkefeli','fazlina@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','Team Lead – Helpdesk','CC Team Lead',1),
(10,'USR-010','Nur Azlini Binti Zaini','azlini@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','Team Lead – Helpdesk','CC Team Lead',1),
(11,'USR-011','Nur Shahirah Binti Hamzah','shahirah@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','CC Executive','CC Executive',1),
(12,'USR-012','Nursyuhada Ardilla Binti Aman Ezurra','syuhada@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','CC Executive','CC Executive',1),
(13,'USR-013','Fifi Sofina Binti Ismail','fifi@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','CC Executive','CC Executive',1),
(14,'USR-014','Wan Muhammad Irfan Bin Wan Nazli','irfan@csa.test',NULL,'$2y$12$k4UCkhFaiEaf53OYQYOSA.tvDt/E/3iMgscSYRUaoE.tkaG02c53W',NULL,'2026-10-19 09:00:00','2026-10-19 09:00:00','CC Executive','CC Executive',1);
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

SET FOREIGN_KEY_CHECKS=1;
