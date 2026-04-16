<?php
/**
 * Dependency checker – validates required and optional plugin dependencies
 * and surfaces admin notices when something is missing.
 *
 * Required:
 *  - PHP 8.2+          (fatal – enforced in Activator)
 *  - WordPress 6.4+    (error notice)
 *  - Composer vendor/  (error notice – Action Scheduler lives here)
 *
 * Optional (warnings):
 *  - Action Scheduler function availability (fallback to sync mode)
 *  - ACF / Secure Custom Fields             (fallback to plain meta boxes)
 *
 * @package ApprenticeshipConnector\Core
 */

namespace ApprenticeshipConnector\Core;

class DependencyChecker {

	/** User-meta key for per-user notice dismissals. */
	private const DISMISSED_META_KEY = 'appcon_dismissed_notices';

	// ── Public API ─────────────────────────────────────────────────────────

	/**
	 * Run all dependency checks and return an array of issue descriptors.
	 *
	 * Each descriptor:
	 *   key     string  machine-readable identifier (used for dismissal)
	 *   type    string  'error'|'warning'
	 *   message string  HTML-safe message (may contain <a> and <code>)
	 *
	 * @return array<int, array{key:string, type:string, message:string}>
	 */
	public static function check(): array {
		$issues = [];

		// ── WordPress version ──────────────────────────────────────────────
		global $wp_version;
		if ( version_compare( $wp_version, '6.4', '<' ) ) {
			$issues[] = [
				'key'     => 'wp_version',
				'type'    => 'error',
				'message' => sprintf(
					/* translators: %s: minimum required WordPress version */
					esc_html__( 'Apprenticeship Connector requires WordPress %s or higher. Please update WordPress.', 'apprenticeship-connector' ),
					'6.4'
				),
			];
		}

		// ── Composer vendor directory ──────────────────────────────────────
		if ( ! file_exists( APPCON_DIR . 'vendor/autoload.php' ) ) {
			$issues[] = [
				'key'     => 'composer',
				'type'    => 'error',
				'message' => wp_kses(
					__( 'Apprenticeship Connector: Composer dependencies are missing. Run <code>composer install --no-dev</code> inside the plugin directory, or install from a pre-built release ZIP.', 'apprenticeship-connector' ),
					[ 'code' => [] ]
				),
			];
		}

		// ── Action Scheduler ──────────────────────────────────────────────
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$issues[] = [
				'key'     => 'action_scheduler',
				'type'    => 'warning',
				'message' => wp_kses(
					__( 'Apprenticeship Connector: Action Scheduler is not available – imports will run synchronously and may time out for large datasets. Install via <code>composer install</code> or ensure the WooCommerce plugin is active.', 'apprenticeship-connector' ),
					[ 'code' => [] ]
				),
			];
		}

		// ── ACF / Secure Custom Fields ─────────────────────────────────────
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			$install_url = admin_url( 'plugin-install.php?s=secure+custom+fields&tab=search&type=term' );
			$issues[]    = [
				'key'     => 'acf',
				'type'    => 'warning',
				'message' => sprintf(
					wp_kses(
						/* translators: %s: admin plugin-install URL */
						__( 'Apprenticeship Connector: <strong>Secure Custom Fields</strong> (or Advanced Custom Fields) is recommended for rich vacancy editing. <a href="%s">Install Secure Custom Fields &rarr;</a>', 'apprenticeship-connector' ),
						[ 'strong' => [], 'a' => [ 'href' => [] ] ]
					),
					esc_url( $install_url )
				),
			];
		}

		return $issues;
	}

	/**
	 * Output WordPress admin notices for any unresolved / un-dismissed issues.
	 * Hooked to admin_notices.
	 */
	public static function render_admin_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$dismissed = (array) get_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, true );

		foreach ( self::check() as $issue ) {
			if ( in_array( $issue['key'], $dismissed, true ) ) {
				continue;
			}

			$dismissible = ( $issue['type'] === 'warning' );
			$class       = 'notice notice-' . esc_attr( $issue['type'] );
			if ( $dismissible ) {
				$class .= ' is-dismissible';
			}

			printf(
				'<div class="%s" data-appcon-notice="%s"><p>%s</p></div>',
				$class,
				esc_attr( $issue['key'] ),
				$issue['message'] // already kses-escaped above
			);
		}
	}

	/**
	 * AJAX handler: dismiss a notice for the current user.
	 * Expects POST field `notice_key`.
	 */
	public static function ajax_dismiss_notice(): void {
		check_ajax_referer( 'appcon_dismiss_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$key = sanitize_key( $_POST['notice_key'] ?? '' );
		if ( ! $key ) {
			wp_send_json_error( 'Missing notice_key', 400 );
		}

		$dismissed   = (array) get_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, true );
		$dismissed[] = $key;
		update_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, array_unique( $dismissed ) );

		wp_send_json_success();
	}
}
