<?php
/**
 * Admin functionality class with logs view and scheduler controls
 *
 * @package ApprenticeshipConnect
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin functionality class
 */
class Apprco_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        // Ensure admin menu is added only once
        if ( ! has_action( 'admin_menu', array( $this, 'add_admin_menu' ) ) ) {
            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        }

        // Ensure duplicate submenu cleanup is added only once
        if ( ! has_action( 'admin_menu', array( $this, 'cleanup_duplicate_submenu' ) ) ) {
            add_action( 'admin_menu', array( $this, 'cleanup_duplicate_submenu' ), 999 );
        }

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // AJAX handlers
        add_action( 'wp_ajax_apprco_manual_sync', array( $this, 'ajax_manual_sync' ) );
        add_action( 'wp_ajax_apprco_test_api', array( $this, 'ajax_test_api' ) );
        add_action( 'wp_ajax_apprco_test_and_sync', array( $this, 'ajax_test_and_sync' ) );
        add_action( 'wp_ajax_apprco_save_api_settings', array( $this, 'ajax_save_api_settings' ) );
        add_action( 'wp_ajax_apprco_get_logs', array( $this, 'ajax_get_logs' ) );
        add_action( 'wp_ajax_apprco_get_import_runs', array( $this, 'ajax_get_import_runs' ) );
        add_action( 'wp_ajax_apprco_clear_logs', array( $this, 'ajax_clear_logs' ) );
        add_action( 'wp_ajax_apprco_export_logs', array( $this, 'ajax_export_logs' ) );
        add_action( 'wp_ajax_apprco_clear_cache', array( $this, 'ajax_clear_cache' ) );
        add_action( 'wp_ajax_apprco_reschedule_sync', array( $this, 'ajax_reschedule_sync' ) );

        // Import Tasks AJAX handlers
        add_action( 'wp_ajax_apprco_get_tasks', array( $this, 'ajax_get_tasks' ) );
        add_action( 'wp_ajax_apprco_get_task', array( $this, 'ajax_get_task' ) );
        add_action( 'wp_ajax_apprco_save_task', array( $this, 'ajax_save_task' ) );
        add_action( 'wp_ajax_apprco_delete_task', array( $this, 'ajax_delete_task' ) );
        add_action( 'wp_ajax_apprco_bulk_task_action', array( $this, 'ajax_bulk_task_action' ) );
        add_action( 'wp_ajax_apprco_test_task_connection', array( $this, 'ajax_test_task_connection' ) );
        add_action( 'wp_ajax_apprco_run_task', array( $this, 'ajax_run_task' ) );

        // Plugin links
        if ( ! has_filter( 'plugin_action_links_' . APPRCO_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) ) ) {
            add_filter( 'plugin_action_links_' . APPRCO_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
        }

        if ( ! has_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ) ) ) {
            add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
        }
    }

    /**
     * Add action links on the Plugins page
     *
     * @param array $links Existing links.
     * @return array
     */
    public function plugin_action_links( array $links ): array {
        $setup_completed = (bool) get_option( 'apprco_setup_completed' );
        $url = $setup_completed
            ? admin_url( 'admin.php?page=apprco-settings' )
            : admin_url( 'admin.php?page=apprco-setup' );
        $label = $setup_completed
            ? __( 'Settings', 'apprenticeship-connect' )
            : __( 'Setup Wizard', 'apprenticeship-connect' );

        $custom_link = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        array_unshift( $links, $custom_link );
        return $links;
    }

    /**
     * Add Buy Me a Coffee link on the Plugins page row meta
     *
     * @param array  $links Existing links.
     * @param string $file  Plugin file.
     * @return array
     */
    public function plugin_row_meta( array $links, string $file ): array {
        if ( $file === APPRCO_PLUGIN_BASENAME ) {
            $links[] = '<a href="https://buymeacoffee.com/epark" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Buy me a Coffee', 'apprenticeship-connect' ) . '</a>';
        }
        return $links;
    }

    /**
     * Remove duplicate submenu that mirrors the top-level link
     */
    public function cleanup_duplicate_submenu(): void {
        remove_submenu_page( 'apprco-dashboard', 'apprco-dashboard' );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu(): void {
        // Main menu page
        add_menu_page(
            __( 'Apprenticeship Connect', 'apprenticeship-connect' ),
            __( 'Apprenticeship Connect', 'apprenticeship-connect' ),
            'manage_options',
            'apprco-dashboard',
            array( $this, 'dashboard_page' ),
            'dashicons-welcome-learn-more',
            30
        );

        // Submenus (Import Tasks is now the primary submenu)
        add_submenu_page(
            'apprco-dashboard',
            __( 'Import Tasks', 'apprenticeship-connect' ),
            __( 'Import Tasks', 'apprenticeship-connect' ),
            'manage_options',
            'apprco-import-tasks',
            array( $this, 'import_tasks_page' )
        );

        add_submenu_page(
            'apprco-dashboard',
            __( 'Dashboard', 'apprenticeship-connect' ),
            __( 'Dashboard', 'apprenticeship-connect' ),
            'manage_options',
            'apprco-dashboard',
            array( $this, 'dashboard_page' )
        );

        add_submenu_page(
            'apprco-dashboard',
            __( 'All Vacancies', 'apprenticeship-connect' ),
            __( 'All Vacancies', 'apprenticeship-connect' ),
            'manage_options',
            'edit.php?post_type=apprco_vacancy'
        );

        add_submenu_page(
            'apprco-dashboard',
            __( 'Add Vacancy', 'apprenticeship-connect' ),
            __( 'Add Vacancy', 'apprenticeship-connect' ),
            'manage_options',
            'post-new.php?post_type=apprco_vacancy'
        );

        add_submenu_page(
            'apprco-dashboard',
            __( 'Import Logs', 'apprenticeship-connect' ),
            __( 'Import Logs', 'apprenticeship-connect' ),
            'manage_options',
            'apprco-logs',
            array( $this, 'logs_page' )
        );

        add_submenu_page(
            'apprco-dashboard',
            __( 'Settings', 'apprenticeship-connect' ),
            __( 'Settings', 'apprenticeship-connect' ),
            'manage_options',
            'apprco-settings',
            array( $this, 'admin_page' )
        );
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( string $hook ): void {
        $apprco_pages = array( 'apprco-settings', 'apprco-setup', 'apprco-dashboard', 'apprco-logs', 'apprco-import-wizard', 'apprco-import-tasks' );
        $current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        if ( in_array( $current_page, $apprco_pages, true ) ) {
            // Load modern built admin assets
            $admin_asset_file = APPRCO_PLUGIN_DIR . 'assets/build/admin.asset.php';
            if ( file_exists( $admin_asset_file ) ) {
                $admin_asset = include $admin_asset_file;
                wp_enqueue_style( 'apprco-admin', APPRCO_PLUGIN_URL . 'assets/build/style-admin.css', array(), $admin_asset['version'] );
                wp_enqueue_script( 'apprco-admin', APPRCO_PLUGIN_URL . 'assets/build/admin.js', $admin_asset['dependencies'], $admin_asset['version'], true );
            } else {
                // Fallback to old assets if build doesn't exist
                wp_enqueue_style( 'apprco-admin', APPRCO_PLUGIN_URL . 'assets/css/admin.css', array(), APPRCO_PLUGIN_VERSION );
                wp_enqueue_script( 'apprco-admin', APPRCO_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), APPRCO_PLUGIN_VERSION, true );
            }
            wp_enqueue_media();

            wp_localize_script( 'apprco-admin', 'apprcoAjax', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'apprco_admin_nonce' ),
                'strings' => array(
                    'syncing'       => __( 'Syncing vacancies...', 'apprenticeship-connect' ),
                    'testing'       => __( 'Testing API connection...', 'apprenticeship-connect' ),
                    'success'       => __( 'Success!', 'apprenticeship-connect' ),
                    'error'         => __( 'Error occurred.', 'apprenticeship-connect' ),
                    'confirm_clear' => __( 'Are you sure you want to clear all logs?', 'apprenticeship-connect' ),
                    'loading'       => __( 'Loading...', 'apprenticeship-connect' ),
                ),
            ) );
        }

        // Dashboard React app
        if ( 'apprco-dashboard' === $current_page ) {
            $dashboard_asset_file = APPRCO_PLUGIN_DIR . 'assets/build/dashboard.asset.php';
            if ( file_exists( $dashboard_asset_file ) ) {
                $dashboard_asset = include $dashboard_asset_file;
                wp_enqueue_script( 'apprco-dashboard', APPRCO_PLUGIN_URL . 'assets/build/dashboard.js', $dashboard_asset['dependencies'], $dashboard_asset['version'], true );
            }
        }

        // Settings React app
        if ( 'apprco-settings' === $current_page ) {
            $settings_asset_file = APPRCO_PLUGIN_DIR . 'assets/build/settings.asset.php';
            if ( file_exists( $settings_asset_file ) ) {
                $settings_asset = include $settings_asset_file;
                wp_enqueue_script( 'apprco-settings', APPRCO_PLUGIN_URL . 'assets/build/settings.js', $settings_asset['dependencies'], $settings_asset['version'], true );
            } else {
                // Fallback: Show build instructions
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>Settings UI Missing:</strong> Run <code>npm install && npm run build</code> in plugin directory to compile React assets.';
                    echo '</p></div>';
                });
            }
        }

    }


    /**
     * Dashboard page
     */
    public function dashboard_page(): void {
        ?>
        <div class="wrap">
            <!-- React Dashboard will render here -->
            <div id="apprco-dashboard-root"></div>
        </div>
        <?php
    }

    /**
     * Logs page
     */
    public function logs_page(): void {
        $logger = new Apprco_Import_Logger();
        $runs   = $logger->get_import_runs( 20 );
        $stats  = $logger->get_stats();
        ?>
        <div class="wrap apprco-logs">
            <h1><?php esc_html_e( 'Import Logs', 'apprenticeship-connect' ); ?></h1>

            <div class="apprco-logs-actions">
                <button type="button" id="apprco-refresh-logs" class="button"><?php esc_html_e( 'Refresh', 'apprenticeship-connect' ); ?></button>
                <button type="button" id="apprco-export-logs" class="button"><?php esc_html_e( 'Export CSV', 'apprenticeship-connect' ); ?></button>
                <button type="button" id="apprco-clear-logs" class="button button-secondary"><?php esc_html_e( 'Clear All Logs', 'apprenticeship-connect' ); ?></button>
            </div>

            <div class="apprco-logs-stats">
                <span><strong><?php esc_html_e( 'Total Log Entries:', 'apprenticeship-connect' ); ?></strong> <?php echo esc_html( $stats['total_logs'] ); ?></span>
                <?php foreach ( $stats['by_level'] as $level => $count ) : ?>
                    <span class="apprco-log-level-<?php echo esc_attr( $level ); ?>"><?php echo esc_html( ucfirst( $level ) ); ?>: <?php echo esc_html( $count ); ?></span>
                <?php endforeach; ?>
            </div>

            <h2><?php esc_html_e( 'Recent Import Runs', 'apprenticeship-connect' ); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Import ID', 'apprenticeship-connect' ); ?></th>
                        <th><?php esc_html_e( 'Started', 'apprenticeship-connect' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'apprenticeship-connect' ); ?></th>
                        <th><?php esc_html_e( 'Trigger', 'apprenticeship-connect' ); ?></th>
                        <th><?php esc_html_e( 'Fetched', 'apprenticeship-connect' ); ?></th>
                        <th><?php esc_html_e( 'Created', 'apprenticeship-connect' ); ?></th>
                        <th><?php esc_html_e( 'Updated', 'apprenticeship-connect' ); ?></th>
                        <th><?php esc_html_e( 'Deleted', 'apprenticeship-connect' ); ?></th>
                        <th><?php esc_html_e( 'Errors', 'apprenticeship-connect' ); ?></th>
                    </tr>
                </thead>
                <tbody id="apprco-import-runs">
                    <?php if ( empty( $runs ) ) : ?>
                        <tr><td colspan="9"><?php esc_html_e( 'No import runs found.', 'apprenticeship-connect' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $runs as $run ) : ?>
                            <tr data-import-id="<?php echo esc_attr( $run['import_id'] ); ?>">
                                <td><a href="#" class="apprco-view-logs" data-import-id="<?php echo esc_attr( $run['import_id'] ); ?>"><?php echo esc_html( substr( $run['import_id'], 0, 8 ) ); ?>...</a></td>
                                <td><?php echo esc_html( $run['started_at'] ); ?></td>
                                <td><span class="apprco-badge apprco-badge-<?php echo esc_attr( $run['status'] ); ?>"><?php echo esc_html( $run['status'] ); ?></span></td>
                                <td><?php echo esc_html( $run['trigger_type'] ); ?></td>
                                <td><?php echo esc_html( $run['total_fetched'] ); ?></td>
                                <td><?php echo esc_html( $run['total_created'] ); ?></td>
                                <td><?php echo esc_html( $run['total_updated'] ); ?></td>
                                <td><?php echo esc_html( $run['total_deleted'] ); ?></td>
                                <td><?php echo esc_html( $run['error_count'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div id="apprco-log-details" class="apprco-log-details" style="display: none;">
                <h3><?php esc_html_e( 'Log Details', 'apprenticeship-connect' ); ?> <span id="apprco-log-import-id"></span></h3>
                <button type="button" id="apprco-close-logs" class="button"><?php esc_html_e( 'Close', 'apprenticeship-connect' ); ?></button>
                <div id="apprco-log-entries"></div>
            </div>
        </div>
        <?php
    }


    /**
     * Admin settings page
     */
    public function admin_page(): void {
        ?>
        <div class="wrap">
            <!-- React Settings will render here -->
            <div id="apprco-settings-root"></div>
        </div>
        <?php
    }

    /**
     * Get sync status
     *
     * @return array
     */
    private function get_sync_status(): array {
        $last_sync        = get_option( 'apprco_last_sync' );
        $total_vacancies  = wp_count_posts( 'apprco_vacancy' );
        $settings_manager = Apprco_Settings_Manager::get_instance();
        $api_key          = $settings_manager->get( 'api', 'subscription_key' );

        return array(
            'last_sync'       => $last_sync,
            'last_sync_human' => $last_sync ? human_time_diff( $last_sync ) . ' ago' : 'Never',
            'total_vacancies' => $total_vacancies->publish ?? 0,
            'is_configured'   => ! empty( $api_key ),
        );
    }

    // AJAX Handlers
    public function ajax_manual_sync(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $adapter = Apprco_Import_Adapter::get_instance();
        $result_data = $adapter->run_manual_sync();
        $result = $result_data['success'] ?? false;

        if ( $result ) {
            $stats = array(
                'created' => $result_data['created'] ?? 0,
                'updated' => $result_data['updated'] ?? 0,
                'deleted' => $result_data['deleted'] ?? 0,
            );
            wp_send_json_success( array(
                'message' => sprintf(
                    __( 'Sync completed! Created: %d, Updated: %d, Deleted: %d', 'apprenticeship-connect' ),
                    $stats['created'],
                    $stats['updated'],
                    $stats['deleted']
                ),
                'stats' => $stats,
            ) );
        } else {
            wp_send_json_error( __( 'Sync failed. Check the logs for details.', 'apprenticeship-connect' ) );
        }
    }

    public function ajax_test_api(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $registry = Apprco_Provider_Registry::get_instance();
        $provider = $registry->get( 'uk-gov-apprenticeships' );

        if ( ! $provider ) {
            wp_send_json_error( __( 'UK Gov Provider not found.', 'apprenticeship-connect' ) );
        }

        $result = $provider->test_connection();

        if ( $result['success'] ) {
            wp_send_json_success( $result['message'] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    public function ajax_test_and_sync(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $settings_manager = Apprco_Settings_Manager::get_instance();
        $saved = $settings_manager->get_options_array();

        $api_base_url = isset( $_POST['api_base_url'] ) && $_POST['api_base_url'] !== '' ? esc_url_raw( wp_unslash( $_POST['api_base_url'] ) ) : ( $saved['api_base_url'] ?? '' );
        $api_key      = isset( $_POST['api_subscription_key'] ) && $_POST['api_subscription_key'] !== '' ? sanitize_text_field( wp_unslash( $_POST['api_subscription_key'] ) ) : ( $saved['api_subscription_key'] ?? '' );
        $api_ukprn    = isset( $_POST['api_ukprn'] ) && $_POST['api_ukprn'] !== '' ? sanitize_text_field( wp_unslash( $_POST['api_ukprn'] ) ) : ( $saved['api_ukprn'] ?? '' );

        if ( empty( $api_key ) || empty( $api_base_url ) ) {
            wp_send_json_error( __( 'API credentials not configured.', 'apprenticeship-connect' ) );
        }

        // Run manual sync with overrides
        $adapter = Apprco_Import_Adapter::get_instance();
        $sync_result_data = $adapter->run_manual_sync( array(
            'api_base_url'         => $api_base_url,
            'api_subscription_key' => $api_key,
            'api_ukprn'            => $api_ukprn,
        ) );

        $sync_result = $sync_result_data['success'] ?? false;
        $sync_status = $this->get_sync_status();

        if ( $sync_result ) {
            wp_send_json_success( array(
                'message'         => sprintf(
                    __( 'Success! Total vacancies in database: %d', 'apprenticeship-connect' ),
                    $sync_status['total_vacancies']
                ),
                'last_sync'       => $sync_status['last_sync'] ? wp_date( 'Y-m-d H:i:s', $sync_status['last_sync'] ) : __( 'Never', 'apprenticeship-connect' ),
                'total_vacancies' => $sync_status['total_vacancies'],
            ) );
        } else {
            wp_send_json_error( __( 'Sync failed. Check the logs for details.', 'apprenticeship-connect' ) );
        }
    }

    public function ajax_save_api_settings(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $api_base_url = isset( $_POST['api_base_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_base_url'] ) ) : '';
        $api_key      = isset( $_POST['api_subscription_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_subscription_key'] ) ) : '';
        $api_ukprn    = isset( $_POST['api_ukprn'] ) ? sanitize_text_field( wp_unslash( $_POST['api_ukprn'] ) ) : '';

        if ( empty( $api_base_url ) || empty( $api_key ) ) {
            wp_send_json_error( __( 'Missing API settings.', 'apprenticeship-connect' ) );
        }

        $settings_manager = Apprco_Settings_Manager::get_instance();
        $settings_manager->update( 'api', 'base_url', $api_base_url );
        $settings_manager->update( 'api', 'subscription_key', $api_key );
        $settings_manager->update( 'api', 'ukprn', $api_ukprn );

        wp_send_json_success( __( 'API settings saved.', 'apprenticeship-connect' ) );
    }

    public function ajax_get_logs(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $import_id = isset( $_POST['import_id'] ) ? sanitize_text_field( wp_unslash( $_POST['import_id'] ) ) : '';

        $logger = new Apprco_Import_Logger();
        $logs   = $logger->get_logs_by_import( $import_id );

        wp_send_json_success( $logs );
    }

    public function ajax_get_import_runs(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $logger = new Apprco_Import_Logger();
        $runs   = $logger->get_import_runs( 20 );

        wp_send_json_success( $runs );
    }

    public function ajax_clear_logs(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $logger = new Apprco_Import_Logger();
        $logger->clear_all();

        wp_send_json_success( __( 'All logs cleared.', 'apprenticeship-connect' ) );
    }

    public function ajax_export_logs(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $import_id = isset( $_POST['import_id'] ) ? sanitize_text_field( wp_unslash( $_POST['import_id'] ) ) : null;

        $logger = new Apprco_Import_Logger();
        $csv    = $logger->export_csv( $import_id );

        wp_send_json_success( array( 'csv' => $csv ) );
    }

    public function ajax_clear_cache(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        // Clear API Client cache
        $api_client = new Apprco_API_Client( '' );
        $api_client->clear_cache();

        // Clear object cache
        wp_cache_delete( 'apprco_existing_vacancy_references' );

        wp_send_json_success( __( 'Cache cleared successfully.', 'apprenticeship-connect' ) );
    }

    public function ajax_reschedule_sync(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        // If frequency is provided, save it first
        if ( isset( $_POST['frequency'] ) ) {
            $settings_manager = Apprco_Settings_Manager::get_instance();
            $settings_manager->update( 'schedule', 'frequency', sanitize_text_field( wp_unslash( $_POST['frequency'] ) ) );
        }

        $task_scheduler = Apprco_Task_Scheduler::get_instance();
        $task_scheduler->init();

        wp_send_json_success( __( 'Sync rescheduled.', 'apprenticeship-connect' ) );
    }

    /**
     * Import Tasks list/edit page
     */
    public function import_tasks_page(): void {
        $action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
        $task_id = isset( $_GET['task_id'] ) ? absint( $_GET['task_id'] ) : 0;

        $tasks_manager = Apprco_Import_Tasks::get_instance();

        if ( 'new' === $action || 'edit' === $action ) {
            $task = $task_id ? $tasks_manager->get( $task_id ) : null;
            $is_new = ! $task;

            // Default values for new task
            if ( $is_new ) {
                $task = array(
                    'id'                 => 0,
                    'name'               => '',
                    'description'        => '',
                    'status'             => 'draft',
                    'api_base_url'       => 'https://api.apprenticeships.education.gov.uk/vacancies',
                    'api_endpoint'       => '/vacancy',
                    'api_method'         => 'GET',
                    'api_headers'        => array( 'X-Version' => '2' ),
                    'api_params'         => array( 'Sort' => 'AgeDesc' ),
                    'api_auth_type'      => 'header_key',
                    'api_auth_key'       => 'Ocp-Apim-Subscription-Key',
                    'api_auth_value'     => '',
                    'response_format'    => 'json',
                    'data_path'          => 'vacancies',
                    'total_path'         => 'total',
                    'pagination_type'    => 'page_number',
                    'page_param'         => 'PageNumber',
                    'page_size_param'    => 'PageSize',
                    'page_size'          => 100,
                    'field_mappings'     => Apprco_Import_Tasks::get_default_field_mappings(),
                    'unique_id_field'    => 'vacancyReference',
                    'transforms_enabled' => 0,
                    'transforms_code'    => '',
                    'target_post_type'   => 'apprco_vacancy',
                    'post_status'        => 'publish',
                    'schedule_enabled'   => 0,
                    'schedule_frequency' => 'daily',
                );
            }

            Apprco_Task_Views::render_editor( $task, $is_new );
        } else {
            $scheduler     = Apprco_Task_Scheduler::get_instance();
            $tasks         = $tasks_manager->get_all();
            $scheduled     = $scheduler->get_scheduled_tasks();

            // Map scheduled tasks by ID for easy lookup
            $scheduled_map = array();
            foreach ( $scheduled as $s ) {
                $scheduled_map[ $s['task_id'] ] = $s;
            }

            Apprco_Task_Views::render_list( $tasks, $scheduled_map );
        }
    }

    /**
     * AJAX: Get all tasks
     */
    public function ajax_get_tasks(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $tasks_manager = Apprco_Import_Tasks::get_instance();
        wp_send_json_success( $tasks_manager->get_all() );
    }

    /**
     * AJAX: Get single task
     */
    public function ajax_get_task(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;
        $tasks_manager = Apprco_Import_Tasks::get_instance();
        $task = $tasks_manager->get( $task_id );

        if ( $task ) {
            wp_send_json_success( $task );
        } else {
            wp_send_json_error( __( 'Task not found.', 'apprenticeship-connect' ) );
        }
    }

    /**
     * AJAX: Save task
     */
    public function ajax_save_task(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;

        // Parse JSON fields
        $api_headers    = json_decode( stripslashes( $_POST['api_headers'] ?? '{}' ), true ) ?: array();
        $api_params     = json_decode( stripslashes( $_POST['api_params'] ?? '{}' ), true ) ?: array();
        $field_mappings = json_decode( stripslashes( $_POST['field_mappings'] ?? '{}' ), true ) ?: array();

        $data = array(
            'name'               => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'description'        => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'status'             => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'draft' ) ),
            'api_base_url'       => esc_url_raw( wp_unslash( $_POST['api_base_url'] ?? '' ) ),
            'api_endpoint'       => sanitize_text_field( wp_unslash( $_POST['api_endpoint'] ?? '' ) ),
            'api_auth_key'       => sanitize_text_field( wp_unslash( $_POST['api_auth_key'] ?? '' ) ),
            'api_auth_value'     => sanitize_text_field( wp_unslash( $_POST['api_auth_value'] ?? '' ) ),
            'api_headers'        => $api_headers,
            'api_params'         => $api_params,
            'data_path'          => sanitize_text_field( wp_unslash( $_POST['data_path'] ?? '' ) ),
            'total_path'         => sanitize_text_field( wp_unslash( $_POST['total_path'] ?? '' ) ),
            'unique_id_field'    => sanitize_text_field( wp_unslash( $_POST['unique_id_field'] ?? '' ) ),
            'page_param'         => sanitize_text_field( wp_unslash( $_POST['page_param'] ?? '' ) ),
            'page_size'          => absint( $_POST['page_size'] ?? 100 ),
            'field_mappings'     => $field_mappings,
            'transforms_enabled' => isset( $_POST['transforms_enabled'] ) ? 1 : 0,
            'transforms_code'    => wp_unslash( $_POST['transforms_code'] ?? '' ),
            'schedule_enabled'   => isset( $_POST['schedule_enabled'] ) ? 1 : 0,
            'schedule_frequency' => sanitize_text_field( wp_unslash( $_POST['schedule_frequency'] ?? 'daily' ) ),
        );

        // Validate required fields
        $validation_errors = array();
        if ( empty( $data['name'] ) ) {
            $validation_errors[] = __( 'Task name is required.', 'apprenticeship-connect' );
        }
        if ( empty( $data['api_base_url'] ) || ! filter_var( $data['api_base_url'], FILTER_VALIDATE_URL ) ) {
            $validation_errors[] = __( 'Valid API Base URL is required.', 'apprenticeship-connect' );
        }
        if ( empty( $data['unique_id_field'] ) ) {
            $validation_errors[] = __( 'Unique ID field is required for duplicate detection.', 'apprenticeship-connect' );
        }
        if ( $data['page_size'] < 1 || $data['page_size'] > 1000 ) {
            $validation_errors[] = __( 'Page size must be between 1 and 1000.', 'apprenticeship-connect' );
        }

        if ( ! empty( $validation_errors ) ) {
            wp_send_json_error( array(
                'message' => __( 'Validation failed:', 'apprenticeship-connect' ),
                'errors'  => $validation_errors,
            ) );
        }

        $tasks_manager = Apprco_Import_Tasks::get_instance();

        if ( $task_id > 0 ) {
            $result = $tasks_manager->update( $task_id, $data );
            if ( $result ) {
                wp_send_json_success( array( 'task_id' => $task_id, 'message' => 'Task updated.' ) );
            } else {
                wp_send_json_error( __( 'Failed to update task.', 'apprenticeship-connect' ) );
            }
        } else {
            $new_id = $tasks_manager->create( $data );
            if ( $new_id ) {
                wp_send_json_success( array( 'task_id' => $new_id, 'message' => 'Task created.' ) );
            } else {
                wp_send_json_error( __( 'Failed to create task.', 'apprenticeship-connect' ) );
            }
        }
    }

    /**
     * AJAX: Delete task
     */
    public function ajax_delete_task(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;

        if ( ! $task_id ) {
            wp_send_json_error( __( 'Invalid task ID.', 'apprenticeship-connect' ) );
        }

        $tasks_manager = Apprco_Import_Tasks::get_instance();
        $result = $tasks_manager->delete( $task_id );

        if ( $result ) {
            wp_send_json_success( __( 'Task deleted.', 'apprenticeship-connect' ) );
        } else {
            wp_send_json_error( __( 'Failed to delete task.', 'apprenticeship-connect' ) );
        }
    }

    /**
     * AJAX handler for bulk task actions
     */
    public function ajax_bulk_task_action(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $action   = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
        $task_ids = isset( $_POST['task_ids'] ) ? array_map( 'absint', (array) $_POST['task_ids'] ) : array();

        if ( empty( $task_ids ) ) {
            wp_send_json_error( __( 'No tasks selected.', 'apprenticeship-connect' ) );
        }

        $tasks_manager = Apprco_Import_Tasks::get_instance();
        $success_count = 0;

        foreach ( $task_ids as $id ) {
            switch ( $action ) {
                case 'activate':
                    if ( $tasks_manager->update( $id, array( 'status' => 'active' ) ) ) {
                        $success_count++;
                    }
                    break;
                case 'deactivate':
                    if ( $tasks_manager->update( $id, array( 'status' => 'inactive' ) ) ) {
                        $success_count++;
                    }
                    break;
                case 'delete':
                    if ( $tasks_manager->delete( $id ) ) {
                        $success_count++;
                    }
                    break;
            }
        }

        wp_send_json_success( sprintf( __( 'Bulk action processed for %d tasks.', 'apprenticeship-connect' ), $success_count ) );
    }

    /**
     * AJAX: Test task connection
     */
    public function ajax_test_task_connection(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        // Build task config from form data
        $api_headers = json_decode( stripslashes( $_POST['api_headers'] ?? '{}' ), true ) ?: array();
        $api_params  = json_decode( stripslashes( $_POST['api_params'] ?? '{}' ), true ) ?: array();

        $task = array(
            'api_base_url'    => esc_url_raw( wp_unslash( $_POST['api_base_url'] ?? '' ) ),
            'api_endpoint'    => sanitize_text_field( wp_unslash( $_POST['api_endpoint'] ?? '' ) ),
            'api_method'      => 'GET',
            'api_headers'     => $api_headers,
            'api_params'      => $api_params,
            'api_auth_type'   => 'header_key',
            'api_auth_key'    => sanitize_text_field( wp_unslash( $_POST['api_auth_key'] ?? '' ) ),
            'api_auth_value'  => sanitize_text_field( wp_unslash( $_POST['api_auth_value'] ?? '' ) ),
            'data_path'       => sanitize_text_field( wp_unslash( $_POST['data_path'] ?? 'vacancies' ) ),
            'total_path'      => sanitize_text_field( wp_unslash( $_POST['total_path'] ?? 'total' ) ),
            'pagination_type' => 'page_number',
            'page_param'      => 'PageNumber',
            'page_size_param' => 'PageSize',
            'page_size'       => 10,
        );

        $tasks_manager = Apprco_Import_Tasks::get_instance();
        $result = $tasks_manager->execute_api_request( $task, 10, true );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Run task
     */
    public function ajax_run_task(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;

        if ( ! $task_id ) {
            wp_send_json_error( __( 'Invalid task ID.', 'apprenticeship-connect' ) );
        }

        $tasks_manager = Apprco_Import_Tasks::get_instance();
        $result = $tasks_manager->run_import( $task_id );

        if ( $result['success'] ) {
            $message = sprintf(
                __( 'Fetched: %d, Created: %d, Updated: %d, Errors: %d', 'apprenticeship-connect' ),
                $result['fetched'],
                $result['created'],
                $result['updated'],
                $result['errors']
            );
            wp_send_json_success( array( 'message' => $message, 'result' => $result ) );
        } else {
            wp_send_json_error( $result['error'] ?? __( 'Import failed.', 'apprenticeship-connect' ) );
        }
    }
}

// Prevent duplicate menu entries
add_action( 'admin_menu', function() {
    global $submenu;
    if ( isset( $submenu['apprco-dashboard'] ) ) {
        $submenu['apprco-dashboard'] = array_unique( $submenu['apprco-dashboard'], SORT_REGULAR );
    }
}, 999 );
