<?php
/**
 * Core plugin bootstrap – WordPress Plugin Boilerplate pattern.
 *
 * @package ApprenticeshipConnector\Core
 */

namespace ApprenticeshipConnector\Core;

use ApprenticeshipConnector\Admin\AdminLoader;
use ApprenticeshipConnector\PostTypes\VacancyPostType;
use ApprenticeshipConnector\PostTypes\EmployerPostType;
use ApprenticeshipConnector\Taxonomies\LevelTaxonomy;
use ApprenticeshipConnector\Taxonomies\RouteTaxonomy;
use ApprenticeshipConnector\Taxonomies\LARSTaxonomy;
use ApprenticeshipConnector\Taxonomies\SkillTaxonomy;
use ApprenticeshipConnector\Taxonomies\EmployerTaxonomy;
use ApprenticeshipConnector\REST\ImportJobsController;
use ApprenticeshipConnector\REST\TestController;
use ApprenticeshipConnector\Import\ImportRunner;
use ApprenticeshipConnector\Import\ActionSchedulerRunner;
use ApprenticeshipConnector\Import\ExpiryManager;
use ApprenticeshipConnector\Admin\SCFFields;

/**
 * Main plugin class – singleton.
 */
class Plugin {

	private static ?Plugin $instance = null;

	private Loader $loader;

	private function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_rest_hooks();
		$this->define_cron_hooks();
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// ── Dependency loading ─────────────────────────────────────────────────

	private function load_dependencies(): void {
		$dir = APPCON_DIR . 'includes/';

		// Boilerplate loader
		require_once $dir . 'Core/Loader.php';
		require_once $dir . 'Core/I18n.php';
		require_once $dir . 'Core/Database.php';
		require_once $dir . 'Core/Settings.php';

		// API layer
		require_once $dir . 'API/RateLimiter.php';
		require_once $dir . 'API/Client.php';
		require_once $dir . 'API/DisplayAdvertAPI.php';

		// Import layer
		require_once $dir . 'Import/Logger.php';
		require_once $dir . 'Import/FieldMapper.php';
		require_once $dir . 'Import/ImportJob.php';
		require_once $dir . 'Import/TwoStageImporter.php';
		require_once $dir . 'Import/ImportRunner.php';
		require_once $dir . 'Import/ActionSchedulerRunner.php';
		require_once $dir . 'Import/ExpiryManager.php';

		// Post types & taxonomies
		require_once $dir . 'PostTypes/VacancyPostType.php';
		require_once $dir . 'PostTypes/EmployerPostType.php';
		require_once $dir . 'Taxonomies/LevelTaxonomy.php';
		require_once $dir . 'Taxonomies/RouteTaxonomy.php';
		require_once $dir . 'Taxonomies/LARSTaxonomy.php';
		require_once $dir . 'Taxonomies/SkillTaxonomy.php';
		require_once $dir . 'Taxonomies/EmployerTaxonomy.php';

		// Admin layer
		require_once $dir . 'Admin/AdminLoader.php';
		require_once $dir . 'Admin/Dashboard.php';
		require_once $dir . 'Admin/SettingsPage.php';
		require_once $dir . 'Admin/ImportJobsPage.php';
		require_once $dir . 'Admin/SCFFields.php';

		// REST controllers
		require_once $dir . 'REST/ImportJobsController.php';
		require_once $dir . 'REST/TestController.php';

		$this->loader = new Loader();
	}

	// ── Locale ────────────────────────────────────────────────────────────

	private function set_locale(): void {
		$i18n = new I18n();
		$this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
	}

	// ── Admin hooks ───────────────────────────────────────────────────────

	private function define_admin_hooks(): void {
		$admin = new AdminLoader();

		$this->loader->add_action( 'admin_menu',           $admin, 'register_menus' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );

		// Register CPTs + taxonomies
		$this->loader->add_action( 'init', VacancyPostType::class . '::register' );
		$this->loader->add_action( 'init', VacancyPostType::class . '::register_meta_fields' );
		$this->loader->add_action( 'init', EmployerPostType::class . '::register' );
		$this->loader->add_action( 'init', LevelTaxonomy::class . '::register' );
		$this->loader->add_action( 'init', RouteTaxonomy::class . '::register' );
		$this->loader->add_action( 'init', LARSTaxonomy::class . '::register' );
		$this->loader->add_action( 'init', SkillTaxonomy::class . '::register' );
		$this->loader->add_action( 'init', EmployerTaxonomy::class . '::register' );

		// SCF / ACF field groups
		$this->loader->add_action( 'acf/init', SCFFields::class . '::register_field_groups' );
	}

	// ── Public hooks ──────────────────────────────────────────────────────

	private function define_public_hooks(): void {
		// Frontend assets added here if needed.
	}

	// ── REST hooks ────────────────────────────────────────────────────────

	private function define_rest_hooks(): void {
		$import_ctrl = new ImportJobsController();
		$test_ctrl   = new TestController();

		$this->loader->add_action( 'rest_api_init', $import_ctrl, 'register_routes' );
		$this->loader->add_action( 'rest_api_init', $test_ctrl,   'register_routes' );
	}

	// ── Action Scheduler hooks ────────────────────────────────────────────
	//
	// Each AS action hook receives named parameters from as_enqueue_async_action().
	// We define thin wrappers that unpack the array and delegate to the runner.

	private function define_cron_hooks(): void {
		// Legacy WP-Cron fallback (kept for backward compat).
		$legacy_runner = new ImportRunner();
		$this->loader->add_action( 'appcon_run_scheduled_import', $legacy_runner, 'run_scheduled' );

		// Action Scheduler – import flow.
		$as_runner = new ActionSchedulerRunner();

		$this->loader->add_action(
			ActionSchedulerRunner::HOOK_START,
			$as_runner,
			'handle_start_action',
			10, 1
		);
		$this->loader->add_action(
			ActionSchedulerRunner::HOOK_STAGE1_PAGE,
			$as_runner,
			'handle_stage1_page_action',
			10, 1
		);
		$this->loader->add_action(
			ActionSchedulerRunner::HOOK_STAGE2_BATCH,
			$as_runner,
			'handle_stage2_batch_action',
			10, 1
		);

		// Action Scheduler – daily expiry.
		$expiry = new ExpiryManager();
		$this->loader->add_action(
			ActionSchedulerRunner::HOOK_EXPIRE,
			$expiry,
			'run'
		);
	}

	// ── Run ───────────────────────────────────────────────────────────────

	public function run(): void {
		$this->loader->run();
	}

	public function get_plugin_name(): string    { return 'apprenticeship-connector'; }
	public function get_version(): string        { return APPCON_VERSION; }
	public function get_loader(): Loader         { return $this->loader; }
}
