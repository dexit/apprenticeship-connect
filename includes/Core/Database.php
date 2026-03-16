<?php
/**
 * Custom database tables manager.
 *
 * @package ApprenticeshipConnector\Core
 */

namespace ApprenticeshipConnector\Core;

class Database {

	/**
	 * Run dbDelta to create/update all plugin tables.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		// ── Import Jobs ────────────────────────────────────────────────────
		$sql_jobs = "CREATE TABLE {$wpdb->prefix}appcon_import_jobs (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                   VARCHAR(255)    NOT NULL,
  description            TEXT,
  status                 ENUM('active','inactive','draft') NOT NULL DEFAULT 'draft',
  api_base_url           VARCHAR(500)    NOT NULL DEFAULT 'https://api.apprenticeships.education.gov.uk/vacancies',
  api_subscription_key   VARCHAR(255)    NOT NULL DEFAULT '',
  stage1_enabled         TINYINT(1)      NOT NULL DEFAULT 1,
  stage1_page_size       INT             NOT NULL DEFAULT 100,
  stage1_max_pages       INT             NOT NULL DEFAULT 100,
  stage1_sort            VARCHAR(50)     NOT NULL DEFAULT 'AgeDesc',
  stage1_filters         JSON,
  stage2_enabled         TINYINT(1)      NOT NULL DEFAULT 1,
  stage2_delay_ms        INT             NOT NULL DEFAULT 250,
  stage2_batch_size      INT             NOT NULL DEFAULT 10,
  field_mappings         JSON            NOT NULL,
  schedule_enabled       TINYINT(1)      NOT NULL DEFAULT 0,
  schedule_frequency     VARCHAR(50),
  schedule_time          TIME,
  schedule_next_run      DATETIME,
  last_run_at            DATETIME,
  last_run_status        VARCHAR(50),
  last_run_stage1_fetched INT            NOT NULL DEFAULT 0,
  last_run_stage2_fetched INT            NOT NULL DEFAULT 0,
  last_run_created       INT             NOT NULL DEFAULT 0,
  last_run_updated       INT             NOT NULL DEFAULT 0,
  last_run_errors        INT             NOT NULL DEFAULT 0,
  last_run_duration      INT,
  created_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_schedule (schedule_enabled, schedule_frequency),
  KEY idx_next_run (schedule_next_run)
) ENGINE=InnoDB $charset;";

		// ── Import Runs ────────────────────────────────────────────────────
		$sql_runs = "CREATE TABLE {$wpdb->prefix}appcon_import_runs (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id            BIGINT UNSIGNED NOT NULL,
  run_id            VARCHAR(64)     NOT NULL,
  status            ENUM('queued','running','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
  started_at        DATETIME,
  completed_at      DATETIME,
  duration          INT,
  stage1_pages      INT             NOT NULL DEFAULT 0,
  stage1_fetched    INT             NOT NULL DEFAULT 0,
  stage1_errors     INT             NOT NULL DEFAULT 0,
  stage2_total      INT             NOT NULL DEFAULT 0,
  stage2_fetched    INT             NOT NULL DEFAULT 0,
  stage2_created    INT             NOT NULL DEFAULT 0,
  stage2_updated    INT             NOT NULL DEFAULT 0,
  stage2_errors     INT             NOT NULL DEFAULT 0,
  stage2_skipped    INT             NOT NULL DEFAULT 0,
  current_stage     TINYINT         NOT NULL DEFAULT 1,
  current_item      INT             NOT NULL DEFAULT 0,
  total_items       INT             NOT NULL DEFAULT 0,
  progress_pct      DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
  error_message     TEXT,
  failed_references JSON,
  retry_count       INT             NOT NULL DEFAULT 0,
  retry_of_run_id   VARCHAR(64),
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY idx_run_id (run_id),
  KEY idx_job_id (job_id),
  KEY idx_status (status),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB $charset;";

		// ── Import Logs ────────────────────────────────────────────────────
		$sql_logs = "CREATE TABLE {$wpdb->prefix}appcon_import_logs (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id     VARCHAR(64)     NOT NULL,
  log_level  ENUM('trace','debug','info','warning','error','critical') NOT NULL DEFAULT 'info',
  message    TEXT            NOT NULL,
  context    VARCHAR(100),
  meta_data  JSON,
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_run_id (run_id),
  KEY idx_log_level (log_level),
  KEY idx_context (context),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB $charset;";

		// ── Employers (cache) ──────────────────────────────────────────────
		$sql_employers = "CREATE TABLE {$wpdb->prefix}appcon_employers (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(255)    NOT NULL,
  slug          VARCHAR(255)    NOT NULL,
  description   TEXT,
  website_url   VARCHAR(500),
  contact_name  VARCHAR(255),
  contact_phone VARCHAR(50),
  contact_email VARCHAR(255),
  term_id       BIGINT UNSIGNED,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY idx_name (name),
  UNIQUE KEY idx_slug (slug),
  KEY idx_term_id (term_id)
) ENGINE=InnoDB $charset;";

		dbDelta( $sql_jobs );
		dbDelta( $sql_runs );
		dbDelta( $sql_logs );
		dbDelta( $sql_employers );
	}

	// ── Helper methods ─────────────────────────────────────────────────────

	public static function get_jobs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'appcon_import_jobs';
	}

	public static function get_runs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'appcon_import_runs';
	}

	public static function get_logs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'appcon_import_logs';
	}

	public static function get_employers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'appcon_employers';
	}
}
