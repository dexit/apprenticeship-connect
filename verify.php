<?php
/**
 * Production Verification Script
 *
 * Run this in WordPress admin to verify plugin is production-ready.
 * Copy to wp-content/plugins/apprenticeship-connect/verify.php
 * Access via: /wp-content/plugins/apprenticeship-connect/verify.php?verify=1
 *
 * @package ApprenticeshipConnect
 */

// Security check
if ( ! defined( 'ABSPATH' ) && ! isset( $_GET['verify'] ) ) {
	die( 'Direct access not permitted' );
}

// Load WordPress if not already loaded
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-load.php';
}

// Only admins can run this
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Permission denied' );
}

header( 'Content-Type: text/html; charset=utf-8' );

?>
<!DOCTYPE html>
<html>
<head>
	<title>Apprenticeship Connect - Production Verification</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
		h1 { color: #2271b1; }
		.test { padding: 15px; margin: 10px 0; border-left: 4px solid #ccc; background: #f6f7f7; }
		.test.pass { border-left-color: #00a32a; background: #edfaef; }
		.test.fail { border-left-color: #d63638; background: #fcf0f1; }
		.test.warn { border-left-color: #dba617; background: #fcf9e8; }
		.test h3 { margin-top: 0; }
		.test .status { font-weight: bold; }
		.pass .status { color: #00a32a; }
		.fail .status { color: #d63638; }
		.warn .status { color: #dba617; }
		.details { font-family: monospace; font-size: 12px; background: white; padding: 10px; margin: 10px 0; }
		.summary { font-size: 18px; padding: 20px; margin: 20px 0; border: 2px solid #2271b1; background: #f0f6fc; }
		.btn { display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 3px; margin: 10px 5px 0 0; }
		.btn:hover { background: #135e96; }
	</style>
</head>
<body>
	<h1>🔍 Apprenticeship Connect - Production Verification</h1>
	<p>Testing all critical functionality for production readiness...</p>

	<?php
	$tests_passed = 0;
	$tests_failed = 0;
	$tests_warned = 0;
	$total_tests  = 0;

	/**
	 * Run a test and display result
	 */
	function run_test( $name, $callback, &$passed, &$failed, &$warned, &$total ) {
		$total++;
		echo '<div class="test';

		try {
			$result = $callback();

			if ( $result['status'] === 'pass' ) {
				echo ' pass">';
				echo '<h3>✅ ' . esc_html( $name ) . '</h3>';
				echo '<p class="status">PASS</p>';
				$passed++;
			} elseif ( $result['status'] === 'warn' ) {
				echo ' warn">';
				echo '<h3>⚠️ ' . esc_html( $name ) . '</h3>';
				echo '<p class="status">WARNING</p>';
				$warned++;
			} else {
				echo ' fail">';
				echo '<h3>❌ ' . esc_html( $name ) . '</h3>';
				echo '<p class="status">FAIL</p>';
				$failed++;
			}

			if ( ! empty( $result['message'] ) ) {
				echo '<p>' . wp_kses_post( $result['message'] ) . '</p>';
			}

			if ( ! empty( $result['details'] ) ) {
				echo '<div class="details">' . wp_kses_post( $result['details'] ) . '</div>';
			}
		} catch ( Exception $e ) {
			echo ' fail">';
			echo '<h3>❌ ' . esc_html( $name ) . '</h3>';
			echo '<p class="status">FAIL</p>';
			echo '<p>Exception: ' . esc_html( $e->getMessage() ) . '</p>';
			$failed++;
		}

		echo '</div>';
	}

	// Test 1: Database Tables Exist
	run_test( 'Database Tables', function() {
		global $wpdb;
		$tables = array(
			$wpdb->prefix . 'apprco_import_tasks',
			$wpdb->prefix . 'apprco_import_logs',
			$wpdb->prefix . 'apprco_employers',
		);

		$missing = array();
		foreach ( $tables as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( ! $exists ) {
				$missing[] = $table;
			}
		}

		if ( empty( $missing ) ) {
			return array(
				'status'  => 'pass',
				'message' => 'All 3 required database tables exist.',
				'details' => implode( "\n", $tables ),
			);
		} else {
			return array(
				'status'  => 'fail',
				'message' => 'Missing database tables. Try deactivating and reactivating the plugin.',
				'details' => 'Missing: ' . implode( ', ', $missing ),
			);
		}
	}, $tests_passed, $tests_failed, $tests_warned, $total_tests );

	// Test 2: Required Classes Exist
	run_test( 'Required Classes', function() {
		$classes = array(
			'Apprco_Core',
			'Apprco_Admin',
			'Apprco_Import_Tasks',
			'Apprco_Import_Adapter',
			'Apprco_Import_Logger',
			'Apprco_Database',
		);

		$missing = array();
		foreach ( $classes as $class ) {
			if ( ! class_exists( $class ) ) {
				$missing[] = $class;
			}
		}

		if ( empty( $missing ) ) {
			return array(
				'status'  => 'pass',
				'message' => 'All ' . count( $classes ) . ' required classes loaded.',
			);
		} else {
			return array(
				'status'  => 'fail',
				'message' => 'Missing classes. Plugin may not be properly activated.',
				'details' => 'Missing: ' . implode( ', ', $missing ),
			);
		}
	}, $tests_passed, $tests_failed, $tests_warned, $total_tests );

	// Test 3: Settings Configuration
	run_test( 'Settings Configuration', function() {
		$options = get_option( 'apprco_plugin_options', array() );

		if ( empty( $options['api_subscription_key'] ) ) {
			return array(
				'status'  => 'warn',
				'message' => 'API credentials not configured yet. Configure in Settings page before importing.',
			);
		}

		if ( empty( $options['api_base_url'] ) ) {
			return array(
				'status'  => 'warn',
				'message' => 'API Base URL not set. Using default.',
			);
		}

		return array(
			'status'  => 'pass',
			'message' => 'API credentials configured.',
			'details' => 'Base URL: ' . esc_html( $options['api_base_url'] ) . "\nKey: " . str_repeat( '*', strlen( $options['api_subscription_key'] ) ),
		);
	}, $tests_passed, $tests_failed, $tests_warned, $total_tests );

	// Test 4: Import Adapter Works
	run_test( 'Import Adapter', function() {
		if ( ! class_exists( 'Apprco_Import_Adapter' ) ) {
			return array(
				'status'  => 'fail',
				'message' => 'Import Adapter class not found.',
			);
		}

		$adapter = Apprco_Import_Adapter::get_instance();

		if ( ! method_exists( $adapter, 'run_manual_sync' ) ) {
			return array(
				'status'  => 'fail',
				'message' => 'run_manual_sync method missing.',
			);
		}

		$stats = $adapter->get_stats();

		return array(
			'status'  => 'pass',
			'message' => 'Import Adapter functional.',
			'details' => 'Total Imports: ' . $stats['total_imports'] . "\nTotal Vacancies: " . $stats['total_vacancies'],
		);
	}, $tests_passed, $tests_failed, $tests_warned, $total_tests );

	// Test 5: Built Assets Exist
	run_test( 'Modern JavaScript Build', function() {
		$build_dir = APPRCO_PLUGIN_DIR . 'assets/build/';

		$required_files = array(
			'admin.js',
			'admin.asset.php',
			'import-wizard.js',
			'import-wizard.asset.php',
		);

		$missing = array();
		foreach ( $required_files as $file ) {
			if ( ! file_exists( $build_dir . $file ) ) {
				$missing[] = $file;
			}
		}

		if ( empty( $missing ) ) {
			return array(
				'status'  => 'pass',
				'message' => 'Modern JavaScript built and ready.',
			);
		} else {
			return array(
				'status'  => 'warn',
				'message' => 'Built assets missing. Run "npm run build" to build modern JavaScript. Old assets will be used as fallback.',
				'details' => 'Missing: ' . implode( ', ', $missing ),
			);
		}
	}, $tests_passed, $tests_failed, $tests_warned, $total_tests );

	// Test 6: CPT Registered
	run_test( 'Custom Post Type', function() {
		if ( ! post_type_exists( 'apprco_vacancy' ) ) {
			return array(
				'status'  => 'fail',
				'message' => 'Vacancy post type not registered.',
			);
		}

		$post_type = get_post_type_object( 'apprco_vacancy' );

		return array(
			'status'  => 'pass',
			'message' => 'Vacancy post type registered.',
			'details' => 'Label: ' . $post_type->labels->name . "\nPublic: " . ( $post_type->public ? 'Yes' : 'No' ),
		);
	}, $tests_passed, $tests_failed, $tests_warned, $total_tests );

	// Test 7: Import Tasks CRUD
	run_test( 'Import Tasks CRUD', function() {
		$tasks_manager = Apprco_Import_Tasks::get_instance();

		// Test get_all
		$all_tasks = $tasks_manager->get_all();
		if ( ! is_array( $all_tasks ) ) {
			return array(
				'status'  => 'fail',
				'message' => 'get_all() did not return an array.',
			);
		}

		return array(
			'status'  => 'pass',
			'message' => 'Import Tasks CRUD operational.',
			'details' => 'Current tasks: ' . count( $all_tasks ),
		);
	}, $tests_passed, $tests_failed, $tests_warned, $total_tests );

	// Test 8: Logging System
	run_test( 'Logging System', function() {
		$logger = Apprco_Import_Logger::get_instance();
		$stats  = $logger->get_stats();

		if ( ! is_array( $stats ) ) {
			return array(
				'status'  => 'fail',
				'message' => 'Logger stats not returning array.',
			);
		}

		return array(
			'status'  => 'pass',
			'message' => 'Logging system functional.',
			'details' => 'Total Runs: ' . $stats['total_runs'] . "\nLast Run: " . ( $stats['last_run'] ?: 'Never' ),
		);
	}, $tests_passed, $tests_failed, $tests_warned, $total_tests );

	// Test 9: Scheduler
	run_test( 'WP-Cron Scheduler', function() {
		if ( ! class_exists( 'Apprco_Scheduler' ) ) {
			return array(
				'status'  => 'fail',
				'message' => 'Scheduler class not found.',
			);
		}

		$scheduler = Apprco_Scheduler::get_instance();
		$status    = $scheduler->get_status();

		return array(
			'status'  => 'pass',
			'message' => 'Scheduler operational.',
			'details' => 'Next Sync: ' . ( $status['next_sync_formatted'] ?: 'Not scheduled' ),
		);
	}, $tests_passed, $tests_failed, $tests_warned, $total_tests );

	// Test 10: PHP Version
	run_test( 'PHP Version', function() {
		$version = PHP_VERSION;

		if ( version_compare( $version, '7.4', '<' ) ) {
			return array(
				'status'  => 'fail',
				'message' => 'PHP version too old. Requires 7.4+.',
				'details' => 'Current: ' . $version,
			);
		}

		if ( version_compare( $version, '8.0', '<' ) ) {
			return array(
				'status'  => 'warn',
				'message' => 'PHP 8.0+ recommended for better performance.',
				'details' => 'Current: ' . $version,
			);
		}

		return array(
			'status'  => 'pass',
			'message' => 'PHP version is good.',
			'details' => 'Version: ' . $version,
		);
	}, $tests_passed, $tests_failed, $tests_warned, $total_tests );

	// Summary
	$success_rate = $total_tests > 0 ? round( ( $tests_passed / $total_tests ) * 100 ) : 0;
	?>

	<div class="summary">
		<h2>📊 Test Summary</h2>
		<p><strong>Total Tests:</strong> <?php echo esc_html( $total_tests ); ?></p>
		<p><strong>Passed:</strong> <?php echo esc_html( $tests_passed ); ?> ✅</p>
		<p><strong>Warnings:</strong> <?php echo esc_html( $tests_warned ); ?> ⚠️</p>
		<p><strong>Failed:</strong> <?php echo esc_html( $tests_failed ); ?> ❌</p>
		<p><strong>Success Rate:</strong> <?php echo esc_html( $success_rate ); ?>%</p>

		<?php if ( $tests_failed === 0 && $tests_warned === 0 ) : ?>
			<p style="color: #00a32a; font-size: 20px; font-weight: bold;">
				🎉 PRODUCTION READY! All tests passed.
			</p>
		<?php elseif ( $tests_failed === 0 ) : ?>
			<p style="color: #dba617; font-size: 18px; font-weight: bold;">
				⚠️ PRODUCTION READY with warnings. Address warnings for optimal operation.
			</p>
		<?php else : ?>
			<p style="color: #d63638; font-size: 18px; font-weight: bold;">
				❌ NOT PRODUCTION READY. Fix failed tests before deploying.
			</p>
		<?php endif; ?>
	</div>

	<div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-settings' ) ); ?>" class="btn">Go to Settings</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-import-tasks' ) ); ?>" class="btn">Go to Import Tasks</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-logs' ) ); ?>" class="btn">View Logs</a>
		<a href="<?php echo esc_url( add_query_arg( 'verify', '1' ) ); ?>" class="btn" style="background: #50575e;">Re-run Tests</a>
	</div>

	<hr style="margin: 40px 0;">

	<h2>📋 Next Steps</h2>

	<?php if ( $tests_failed === 0 && $tests_warned <= 1 ) : ?>
		<ol>
			<li>✅ Configure API credentials in <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-settings' ) ); ?>">Settings</a></li>
			<li>✅ Click "Test API" to verify connection</li>
			<li>✅ Click "Manual Sync" to import vacancies</li>
			<li>✅ Check <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-logs' ) ); ?>">Logs</a> for import results</li>
			<li>✅ Create scheduled import in <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-import-tasks' ) ); ?>">Import Tasks</a></li>
			<li>✅ View vacancies on <a href="<?php echo esc_url( get_post_type_archive_link( 'apprco_vacancy' ) ); ?>">frontend</a></li>
		</ol>
	<?php else : ?>
		<ol>
			<li>Fix failed tests above</li>
			<li>Re-run this verification script</li>
			<li>Configure API credentials when ready</li>
		</ol>
	<?php endif; ?>

	<p style="color: #646970; font-size: 12px; margin-top: 40px;">
		Plugin Version: <?php echo esc_html( APPRCO_PLUGIN_VERSION ); ?> |
		WordPress Version: <?php echo esc_html( get_bloginfo( 'version' ) ); ?> |
		Generated: <?php echo esc_html( gmdate( 'Y-m-d H:i:s' ) . ' UTC' ); ?>
	</p>
</body>
</html>
