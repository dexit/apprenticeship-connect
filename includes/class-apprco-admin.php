<?php
/**
 * Admin Manager Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Admin
 *
 * Handles the admin menu and asset enqueuing for the plugin.
 */
class Apprco_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers the admin menu and submenu pages.
	 *
	 * @return void
	 */
	public function add_menu_pages(): void {
		add_menu_page(
			__( 'Apprenticeship Connect', 'apprenticeship-connect' ),
			__( 'Appr Connect', 'apprenticeship-connect' ),
			'manage_options',
			'apprco-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-welcome-learn-more'
		);

		add_submenu_page(
			'apprco-dashboard',
			__( 'Settings', 'apprenticeship-connect' ),
			__( 'Settings', 'apprenticeship-connect' ),
			'manage_options',
			'apprco-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'apprco-dashboard',
			__( 'Field Mappings', 'apprenticeship-connect' ),
			__( 'Field Mappings', 'apprenticeship-connect' ),
			'manage_options',
			'apprco-field-mappings',
			array( $this, 'render_field_mappings' )
		);
	}

	/**
	 * Renders the dashboard React mount point.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		echo '<div id="apprco-dashboard-root"></div>';
	}

	/**
	 * Renders the settings React mount point.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		echo '<div id="apprco-settings-root"></div>';
	}

	/**
	 * Renders the field mappings admin page.
	 *
	 * @return void
	 */
	public function render_field_mappings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'apprenticeship-connect' ) );
		}

		$tasks_repo = Apprco_Import_Tasks::get_instance();
		$tasks      = $tasks_repo->get_all();
		// Apprco_DTO_Mapper uses static methods.

		// Handle reset-to-defaults POST action.
		if ( isset( $_POST['apprco_reset_mappings'], $_POST['apprco_task_id'], $_POST['_wpnonce'] ) ) {
			$task_id = intval( $_POST['apprco_task_id'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'apprco_reset_mappings_' . $task_id ) ) {
				$tasks_repo->update(
					$task_id,
					array( 'field_mappings' => Apprco_DTO_Mapper::default_mappings() )
				);
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Mappings reset to defaults.', 'apprenticeship-connect' ) . '</p></div>';
				$tasks = $tasks_repo->all();
			}
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Field Mappings', 'apprenticeship-connect' ) . '</h1>';
		echo '<p>' . esc_html__( 'Each import task can have its own DTO field mapping rules. Mappings define how API response fields are mapped to CPT post data, post meta, taxonomies, and the vacancy store.', 'apprenticeship-connect' ) . '</p>';

		if ( empty( $tasks ) ) {
			echo '<p>' . esc_html__( 'No import tasks found. Create an import task first.', 'apprenticeship-connect' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Task', 'apprenticeship-connect' ) . '</th>';
		echo '<th>' . esc_html__( 'Mapping Rules', 'apprenticeship-connect' ) . '</th>';
		echo '<th>' . esc_html__( 'Custom?', 'apprenticeship-connect' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'apprenticeship-connect' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $tasks as $task ) {
			$task_id       = intval( $task['id'] );
			$task_name     = esc_html( $task['name'] ?? 'Task #' . $task_id );
			$mappings      = ! empty( $task['field_mappings'] ) ? $task['field_mappings'] : array();
			$is_custom     = ! empty( $mappings ) && $mappings !== Apprco_DTO_Mapper::default_mappings();
			$mapping_count = count( $mappings );

			$edit_url = add_query_arg(
				array(
					'post_type' => 'apprco_vacancy',
					'action'    => 'apprco_edit_task',
					'task_id'   => $task_id,
				),
				admin_url( 'edit.php' )
			);

			echo '<tr>';
			echo '<td><strong>' . esc_html( $task_name ) . '</strong><br><span class="description">#' . esc_html( (string) $task_id ) . '</span></td>';
			echo '<td>' . esc_html( (string) $mapping_count ) . ' ' . esc_html__( 'rules', 'apprenticeship-connect' ) . '</td>';
			echo '<td>' . ( $is_custom ? '<span style="color:#d63638">&#10003; ' . esc_html__( 'Custom', 'apprenticeship-connect' ) . '</span>' : '<span style="color:#00a32a">&#10003; ' . esc_html__( 'Default', 'apprenticeship-connect' ) . '</span>' ) . '</td>';
			echo '<td>';

			if ( $is_custom ) {
				$nonce = wp_create_nonce( 'apprco_reset_mappings_' . $task_id );
				echo '<form method="post" style="display:inline">';
				echo '<input type="hidden" name="apprco_task_id" value="' . esc_attr( (string) $task_id ) . '">';
				echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">';
				echo '<button type="submit" name="apprco_reset_mappings" class="button button-small" onclick="return confirm(\'' . esc_js( __( 'Reset mappings to defaults?', 'apprenticeship-connect' ) ) . '\')">' . esc_html__( 'Reset to Defaults', 'apprenticeship-connect' ) . '</button>';
				echo '</form> ';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';

		// Show default mappings reference table.
		$defaults = Apprco_DTO_Mapper::default_mappings();
		echo '<h2 style="margin-top:2em">' . esc_html__( 'Default Mapping Rules Reference', 'apprenticeship-connect' ) . '</h2>';
		echo '<p>' . esc_html__( 'These are the built-in mappings applied when no custom mappings are configured for a task.', 'apprenticeship-connect' ) . '</p>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>' . esc_html__( 'Source Path', 'apprenticeship-connect' ) . '</th>';
		echo '<th>' . esc_html__( 'Target Type', 'apprenticeship-connect' ) . '</th>';
		echo '<th>' . esc_html__( 'Target Key', 'apprenticeship-connect' ) . '</th>';
		echo '<th>' . esc_html__( 'Transform', 'apprenticeship-connect' ) . '</th></tr></thead><tbody>';

		foreach ( $defaults as $rule ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $rule['source'] ?? '' ) . '</code></td>';
			echo '<td>' . esc_html( $rule['type'] ?? '' ) . '</td>';
			echo '<td><code>' . esc_html( $rule['key'] ?? '' ) . '</code></td>';
			echo '<td>' . ( ! empty( $rule['transform'] ) ? '<code>' . esc_html( $rule['transform'] ) . '</code>' : '&mdash;' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Enqueues admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ): void {
		if ( strpos( $hook, 'apprco' ) === false && strpos( $hook, 'apprco_vacancy' ) === false ) {
			return;
		}

		$asset_file = APPRCO_PLUGIN_DIR . 'build/admin/index.asset.php';
		$deps       = array( 'wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-components' );
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
			$deps  = $asset['dependencies'];
		}

		wp_enqueue_script( 'apprco-admin', APPRCO_PLUGIN_URL . 'build/admin/index.js', $deps, APPRCO_VERSION, true );
		wp_enqueue_style( 'apprco-admin-style', APPRCO_PLUGIN_URL . 'build/admin/index.css', array( 'wp-components' ), APPRCO_VERSION );
	}
}
