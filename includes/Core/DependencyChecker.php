<?php
/**
 * Dependency checker – validates required and optional plugin dependencies
 * and surfaces admin notices when something is missing.
 *
 * Required (errors – plugin will not function correctly):
 *  - PHP 8.2+          (fatal – enforced in Activator before this class loads)
 *  - WordPress 6.4+    (error notice)
 *  - Composer vendor/  (error notice – Action Scheduler lives here)
 *
 * Optional (warnings – plugin degrades gracefully):
 *  - Action Scheduler  (fallback to synchronous WP-Cron runs)
 *  - ACF / Secure Custom Fields (fallback to plain post meta boxes)
 *  - Elementor         (Elementor widgets / dynamic tags unavailable)
 *
 * @package ApprenticeshipConnector\Core
 */

namespace ApprenticeshipConnector\Core;

class DependencyChecker {

	/** User-meta key for per-user notice dismissals. */
	private const DISMISSED_META_KEY = 'appcon_dismissed_notices';

	/**
	 * Minimum plugin versions that satisfy each optional dependency.
	 * Used to warn when an outdated version is active.
	 */
	private const MIN_ELEMENTOR_VERSION = '3.0.0';

	// ── Public API ─────────────────────────────────────────────────────────

	/**
	 * Run all dependency checks and return an array of issue descriptors.
	 *
	 * Each descriptor:
	 *   key     string   machine-readable identifier (used for dismissal)
	 *   type    string   'error' | 'warning' | 'info'
	 *   message string   HTML-safe message (may contain <a>, <code>, <strong>)
	 *   dismiss bool     whether the notice can be dismissed (default true for warnings)
	 *
	 * @return array<int, array{key:string, type:string, message:string, dismiss:bool}>
	 */
	public static function check(): array {
		$issues = [];

		// ── WordPress version ──────────────────────────────────────────────
		global $wp_version;
		if ( version_compare( $wp_version, '6.4', '<' ) ) {
			$issues[] = [
				'key'     => 'wp_version',
				'type'    => 'error',
				'dismiss' => false,
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
				'dismiss' => false,
				'message' => wp_kses(
					__( '<strong>Apprenticeship Connector:</strong> Composer dependencies are missing. Run <code>composer install --no-dev</code> inside the plugin directory, or install from a pre-built release ZIP.', 'apprenticeship-connector' ),
					[ 'strong' => [], 'code' => [] ]
				),
			];
		}

		// ── Action Scheduler ──────────────────────────────────────────────
		// Bundled via Composer but also available through WooCommerce.
		// Without it, imports run synchronously and may time out on large datasets.
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$issues[] = [
				'key'     => 'action_scheduler',
				'type'    => 'warning',
				'dismiss' => true,
				'message' => wp_kses(
					sprintf(
						/* translators: 1: plugin name, 2: composer command, 3: WooCommerce link */
						__( '<strong>Apprenticeship Connector:</strong> <strong>Action Scheduler</strong> is not available – imports will run synchronously and may time out for large datasets. Either run <code>composer install</code> in the plugin directory, or activate %s to supply Action Scheduler.', 'apprenticeship-connector' ),
						'<a href="' . esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ) . '">WooCommerce</a>'
					),
					[ 'strong' => [], 'code' => [], 'a' => [ 'href' => [] ] ]
				),
			];
		}

		// ── ACF / Secure Custom Fields ─────────────────────────────────────
		// Provides rich editing fields for vacancies. Falls back to plain meta boxes.
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			$install_url = admin_url( 'plugin-install.php?s=secure+custom+fields&tab=search&type=term' );
			$issues[]    = [
				'key'     => 'acf',
				'type'    => 'warning',
				'dismiss' => true,
				'message' => sprintf(
					wp_kses(
						/* translators: %s: admin plugin-install URL */
						__( '<strong>Apprenticeship Connector:</strong> <strong>Secure Custom Fields</strong> (or Advanced Custom Fields) is recommended for rich vacancy editing in the WordPress admin. <a href="%s">Install Secure Custom Fields &rarr;</a>', 'apprenticeship-connector' ),
						[ 'strong' => [], 'a' => [ 'href' => [] ] ]
					),
					esc_url( $install_url )
				),
			];
		}

		// ── Elementor ─────────────────────────────────────────────────────
		// Optional – enables vacancy widgets in the Elementor page builder.
		// Not required; the Gutenberg blocks and shortcodes work without it.
		$elementor_active  = defined( 'ELEMENTOR_VERSION' );
		$elementor_version = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '0';

		if ( ! $elementor_active ) {
			$install_url = admin_url( 'plugin-install.php?s=elementor&tab=search&type=term' );
			$issues[]    = [
				'key'     => 'elementor',
				'type'    => 'info',
				'dismiss' => true,
				'message' => sprintf(
					wp_kses(
						/* translators: %s: admin plugin-install URL */
						__( '<strong>Apprenticeship Connector:</strong> <strong>Elementor</strong> is not active – Elementor vacancy widgets and dynamic tags are unavailable. Gutenberg blocks and <code>[appcon_vacancies]</code> shortcodes work without it. <a href="%s">Install Elementor &rarr;</a>', 'apprenticeship-connector' ),
						[ 'strong' => [], 'code' => [], 'a' => [ 'href' => [] ] ]
					),
					esc_url( $install_url )
				),
			];
		} elseif ( version_compare( $elementor_version, self::MIN_ELEMENTOR_VERSION, '<' ) ) {
			$issues[] = [
				'key'     => 'elementor_version',
				'type'    => 'warning',
				'dismiss' => true,
				'message' => sprintf(
					wp_kses(
						/* translators: 1: installed version, 2: minimum required version */
						__( '<strong>Apprenticeship Connector:</strong> Elementor %1$s is active, but version %2$s or higher is required for full widget compatibility. Please update Elementor.', 'apprenticeship-connector' ),
						[ 'strong' => [] ]
					),
					esc_html( $elementor_version ),
					esc_html( self::MIN_ELEMENTOR_VERSION )
				),
			];
		}

		return $issues;
	}

	/**
	 * Return only issues of a given type.
	 *
	 * @param string $type 'error'|'warning'|'info'
	 * @return array
	 */
	public static function get_issues_by_type( string $type ): array {
		return array_values(
			array_filter( self::check(), fn( $i ) => $i['type'] === $type )
		);
	}

	/**
	 * Return true if there are any blocking errors (type = 'error').
	 */
	public static function has_errors(): bool {
		return ! empty( self::get_issues_by_type( 'error' ) );
	}

	// ── Admin notices ──────────────────────────────────────────────────────

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

			$can_dismiss = $issue['dismiss'] ?? false;

			$class = 'notice notice-' . esc_attr( $issue['type'] );
			if ( $can_dismiss ) {
				$class .= ' is-dismissible';
			}

			printf(
				'<div class="%s" data-appcon-notice="%s"><p>%s</p></div>',
				esc_attr( $class ),
				esc_attr( $issue['key'] ),
				$issue['message'] // already kses-escaped above
			);
		}

		// Output the dismiss handler script once per page load.
		self::maybe_print_dismiss_script();
	}

	/**
	 * Print the inline JS that wires AJAX dismiss to the WP core "dismiss" button.
	 * Only printed when there are dismissible notices on screen.
	 */
	private static function maybe_print_dismiss_script(): void {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		?>
		<script>
		( function () {
			document.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '.notice[data-appcon-notice] .notice-dismiss' );
				if ( ! btn ) return;
				var notice = btn.closest( '[data-appcon-notice]' );
				if ( ! notice ) return;
				var key = notice.dataset.appconNotice;
				if ( ! key ) return;
				fetch( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
					method : 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body   : new URLSearchParams( {
						action    : 'appcon_dismiss_notice',
						nonce     : <?php echo wp_json_encode( wp_create_nonce( 'appcon_dismiss_notice' ) ); ?>,
						notice_key: key,
					} ),
				} );
			} );
		} )();
		</script>
		<?php
	}

	// ── AJAX dismiss handler ───────────────────────────────────────────────

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
