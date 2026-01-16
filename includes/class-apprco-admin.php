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

        add_action( 'admin_init', array( $this, 'init_settings' ) );
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

        // Submenus
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
            __( 'Import Wizard', 'apprenticeship-connect' ),
            __( 'Import Wizard', 'apprenticeship-connect' ),
            'manage_options',
            'apprco-import-wizard',
            array( $this, 'import_wizard_page' )
        );

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
            wp_enqueue_style( 'apprco-admin', APPRCO_PLUGIN_URL . 'assets/css/admin.css', array(), APPRCO_PLUGIN_VERSION );
            wp_enqueue_script( 'apprco-admin', APPRCO_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), APPRCO_PLUGIN_VERSION, true );
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

        // Import wizard specific assets
        if ( 'apprco-import-wizard' === $current_page ) {
            wp_enqueue_style( 'apprco-import-wizard', APPRCO_PLUGIN_URL . 'assets/css/import-wizard.css', array(), APPRCO_PLUGIN_VERSION );
            wp_enqueue_script( 'apprco-import-wizard', APPRCO_PLUGIN_URL . 'assets/js/import-wizard.js', array( 'jquery' ), APPRCO_PLUGIN_VERSION, true );

            $wizard = Apprco_Import_Wizard::get_instance();
            wp_localize_script( 'apprco-import-wizard', 'apprcoWizard', $wizard->get_js_data() );
        }
    }

    /**
     * Initialize settings
     */
    public function init_settings(): void {
        register_setting( 'apprco_plugin_options', 'apprco_plugin_options', array( $this, 'sanitize_options' ) );

        // API Settings Section
        add_settings_section(
            'apprco_api_settings',
            __( 'API Configuration', 'apprenticeship-connect' ),
            array( $this, 'api_settings_section_callback' ),
            'apprco-settings'
        );

        add_settings_field( 'api_base_url', __( 'API Base URL', 'apprenticeship-connect' ), array( $this, 'api_base_url_callback' ), 'apprco-settings', 'apprco_api_settings' );
        add_settings_field( 'api_subscription_key', __( 'API Subscription Key', 'apprenticeship-connect' ), array( $this, 'api_subscription_key_callback' ), 'apprco-settings', 'apprco_api_settings' );
        add_settings_field( 'api_ukprn', __( 'UKPRN (Optional)', 'apprenticeship-connect' ), array( $this, 'api_ukprn_callback' ), 'apprco-settings', 'apprco_api_settings' );
        add_settings_field( 'test_and_sync', __( 'Test & Sync', 'apprenticeship-connect' ), array( $this, 'test_and_sync_callback' ), 'apprco-settings', 'apprco_api_settings' );

        // Scheduler Settings Section
        add_settings_section(
            'apprco_scheduler_settings',
            __( 'Scheduler Settings', 'apprenticeship-connect' ),
            array( $this, 'scheduler_settings_section_callback' ),
            'apprco-settings'
        );

        add_settings_field( 'sync_frequency', __( 'Sync Frequency', 'apprenticeship-connect' ), array( $this, 'sync_frequency_callback' ), 'apprco-settings', 'apprco_scheduler_settings' );
        add_settings_field( 'delete_expired', __( 'Delete Expired', 'apprenticeship-connect' ), array( $this, 'delete_expired_callback' ), 'apprco-settings', 'apprco_scheduler_settings' );
        add_settings_field( 'expire_after_days', __( 'Expire After Days', 'apprenticeship-connect' ), array( $this, 'expire_after_days_callback' ), 'apprco-settings', 'apprco_scheduler_settings' );

        // Shortcode section
        add_settings_section(
            'apprco_shortcode_info',
            __( 'Shortcode', 'apprenticeship-connect' ),
            array( $this, 'shortcode_section_callback' ),
            'apprco-settings'
        );

        // Display Settings Section
        add_settings_section(
            'apprco_display_settings',
            __( 'Display Settings', 'apprenticeship-connect' ),
            array( $this, 'display_settings_section_callback' ),
            'apprco-settings'
        );

        add_settings_field( 'display_count', __( 'Default Display Count', 'apprenticeship-connect' ), array( $this, 'display_count_callback' ), 'apprco-settings', 'apprco_display_settings' );
        add_settings_field( 'show_employer', __( 'Show Employer', 'apprenticeship-connect' ), array( $this, 'show_employer_callback' ), 'apprco-settings', 'apprco_display_settings' );
        add_settings_field( 'show_location', __( 'Show Location', 'apprenticeship-connect' ), array( $this, 'show_location_callback' ), 'apprco-settings', 'apprco_display_settings' );
        add_settings_field( 'show_closing_date', __( 'Show Closing Date', 'apprenticeship-connect' ), array( $this, 'show_closing_date_callback' ), 'apprco-settings', 'apprco_display_settings' );
        add_settings_field( 'show_apply_button', __( 'Show Apply Button', 'apprenticeship-connect' ), array( $this, 'show_apply_button_callback' ), 'apprco-settings', 'apprco_display_settings' );
        add_settings_field( 'show_no_vacancy_image', __( 'Show "No Vacancy" Image', 'apprenticeship-connect' ), array( $this, 'show_no_vacancy_image_callback' ), 'apprco-settings', 'apprco_display_settings' );
        add_settings_field( 'no_vacancy_image', __( 'No Vacancy Image', 'apprenticeship-connect' ), array( $this, 'no_vacancy_image_callback' ), 'apprco-settings', 'apprco_display_settings' );
    }

    /**
     * Sanitize options
     *
     * @param array $input Input options.
     * @return array
     */
    public function sanitize_options( array $input ): array {
        $sanitized = array();

        $sanitized['api_base_url']         = isset( $input['api_base_url'] ) ? esc_url_raw( $input['api_base_url'] ) : '';
        $sanitized['api_subscription_key'] = isset( $input['api_subscription_key'] ) ? sanitize_text_field( $input['api_subscription_key'] ) : '';
        $sanitized['api_ukprn']            = isset( $input['api_ukprn'] ) ? sanitize_text_field( $input['api_ukprn'] ) : '';
        $sanitized['sync_frequency']       = isset( $input['sync_frequency'] ) ? sanitize_text_field( $input['sync_frequency'] ) : 'daily';
        $sanitized['delete_expired']       = isset( $input['delete_expired'] ) ? true : false;
        $sanitized['expire_after_days']    = isset( $input['expire_after_days'] ) ? absint( $input['expire_after_days'] ) : 7;
        $sanitized['display_count']        = isset( $input['display_count'] ) ? absint( $input['display_count'] ) : 10;
        $sanitized['show_employer']        = isset( $input['show_employer'] ) ? true : false;
        $sanitized['show_location']        = isset( $input['show_location'] ) ? true : false;
        $sanitized['show_closing_date']    = isset( $input['show_closing_date'] ) ? true : false;
        $sanitized['show_apply_button']    = isset( $input['show_apply_button'] ) ? true : false;
        $sanitized['no_vacancy_image']     = isset( $input['no_vacancy_image'] ) ? esc_url_raw( $input['no_vacancy_image'] ) : '';
        $sanitized['show_no_vacancy_image'] = isset( $input['show_no_vacancy_image'] ) ? true : false;

        // Reschedule sync if frequency changed
        $old_options = get_option( 'apprco_plugin_options', array() );
        if ( isset( $old_options['sync_frequency'] ) && $old_options['sync_frequency'] !== $sanitized['sync_frequency'] ) {
            $scheduler = Apprco_Scheduler::get_instance();
            $scheduler->reschedule();
        }

        return $sanitized;
    }

    // Section callbacks
    public function api_settings_section_callback(): void {
        echo '<p>' . esc_html__( 'Configure your API credentials to connect to the UK Government Apprenticeship service.', 'apprenticeship-connect' ) . '</p>';
    }

    public function scheduler_settings_section_callback(): void {
        $scheduler = Apprco_Scheduler::get_instance();
        $status    = $scheduler->get_status();

        echo '<p>' . esc_html__( 'Configure automated sync schedule.', 'apprenticeship-connect' ) . '</p>';

        echo '<div class="apprco-scheduler-status">';
        echo '<strong>' . esc_html__( 'Scheduler Status:', 'apprenticeship-connect' ) . '</strong> ';

        if ( $status['action_scheduler_available'] ) {
            echo '<span class="apprco-badge apprco-badge-success">' . esc_html__( 'Action Scheduler Active', 'apprenticeship-connect' ) . '</span>';
        } else {
            echo '<span class="apprco-badge apprco-badge-warning">' . esc_html__( 'Using WP-Cron', 'apprenticeship-connect' ) . '</span>';
        }

        if ( $status['next_sync_formatted'] ) {
            echo '<br><strong>' . esc_html__( 'Next Sync:', 'apprenticeship-connect' ) . '</strong> ' . esc_html( $status['next_sync_formatted'] );
        }

        if ( $status['is_running'] ) {
            echo '<br><span class="apprco-badge apprco-badge-info">' . esc_html__( 'Import Running...', 'apprenticeship-connect' ) . '</span>';
        }

        echo '</div>';
    }

    public function shortcode_section_callback(): void {
        echo '<div class="apprco-shortcode-inline">';
        echo '<p>' . esc_html__( 'Use this shortcode to display vacancies on any page:', 'apprenticeship-connect' ) . '</p>';
        echo '<code>[apprco_vacancies]</code>';
        echo '<p class="description">' . esc_html__( 'Or use the Vacancies archive page, Elementor Loop Grid, or REST API.', 'apprenticeship-connect' ) . '</p>';
        echo '</div>';
    }

    public function display_settings_section_callback(): void {
        echo '<p>' . esc_html__( 'Configure how vacancies are displayed on your website.', 'apprenticeship-connect' ) . '</p>';
    }

    // Field callbacks
    public function api_base_url_callback(): void {
        $options = get_option( 'apprco_plugin_options', array() );
        $value   = $options['api_base_url'] ?? 'https://api.apprenticeships.education.gov.uk/vacancies';
        echo '<input type="url" id="api_base_url" name="apprco_plugin_options[api_base_url]" value="' . esc_attr( $value ) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'The base URL for the API endpoint.', 'apprenticeship-connect' ) . '</p>';
    }

    public function api_subscription_key_callback(): void {
        $options = get_option( 'apprco_plugin_options', array() );
        $value   = $options['api_subscription_key'] ?? '';
        echo '<input type="password" id="api_subscription_key" name="apprco_plugin_options[api_subscription_key]" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off" />';
        echo '<p class="description">' . esc_html__( 'Your API subscription key from the UK Government Apprenticeship service.', 'apprenticeship-connect' ) . '</p>';
    }

    public function api_ukprn_callback(): void {
        $options = get_option( 'apprco_plugin_options', array() );
        $value   = $options['api_ukprn'] ?? '';
        echo '<input type="text" id="api_ukprn" name="apprco_plugin_options[api_ukprn]" value="' . esc_attr( $value ) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'UKPRN for your provider (optional). Leave blank to show all vacancies.', 'apprenticeship-connect' ) . '</p>';
    }

    public function test_and_sync_callback(): void {
        $sync_status     = $this->get_sync_status();
        $is_configured   = $sync_status['is_configured'];
        $last_sync       = $sync_status['last_sync'];
        $total_vacancies = $sync_status['total_vacancies'];

        echo '<button type="button" id="apprco-test-and-sync" class="button button-primary">';
        echo $is_configured ? esc_html__( 'Test & Sync Vacancies', 'apprenticeship-connect' ) : esc_html__( 'Configure API to Test & Sync', 'apprenticeship-connect' );
        echo '</button>';

        echo ' <button type="button" id="apprco-clear-cache" class="button">' . esc_html__( 'Clear Cache', 'apprenticeship-connect' ) . '</button>';

        echo '<div id="apprco-test-sync-result" style="margin-top: 10px;"></div>';

        if ( $is_configured ) {
            echo '<p class="description">' . esc_html__( 'Last synced: ', 'apprenticeship-connect' ) . '<span id="apprco-last-sync">' . ( $last_sync ? esc_html( wp_date( 'Y-m-d H:i:s', $last_sync ) ) : esc_html__( 'Never', 'apprenticeship-connect' ) ) . '</span></p>';
            echo '<p class="description">' . esc_html__( 'Total vacancies: ', 'apprenticeship-connect' ) . '<span id="apprco-total-vacancies">' . esc_html( $total_vacancies ) . '</span></p>';
        }
    }

    public function sync_frequency_callback(): void {
        $options = get_option( 'apprco_plugin_options', array() );
        $value   = $options['sync_frequency'] ?? 'daily';

        $frequencies = array(
            'hourly'     => __( 'Hourly', 'apprenticeship-connect' ),
            'twicedaily' => __( 'Twice Daily', 'apprenticeship-connect' ),
            'daily'      => __( 'Daily', 'apprenticeship-connect' ),
            'weekly'     => __( 'Weekly', 'apprenticeship-connect' ),
        );

        echo '<select id="sync_frequency" name="apprco_plugin_options[sync_frequency]">';
        foreach ( $frequencies as $freq => $label ) {
            echo '<option value="' . esc_attr( $freq ) . '"' . selected( $value, $freq, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'How often to automatically sync vacancies from the API.', 'apprenticeship-connect' ) . '</p>';
    }

    public function delete_expired_callback(): void {
        $options = get_option( 'apprco_plugin_options', array() );
        $checked = $options['delete_expired'] ?? true;
        echo '<input type="checkbox" id="delete_expired" name="apprco_plugin_options[delete_expired]" ' . checked( $checked, true, false ) . ' />';
        echo '<label for="delete_expired">' . esc_html__( 'Automatically delete expired vacancies', 'apprenticeship-connect' ) . '</label>';
    }

    public function expire_after_days_callback(): void {
        $options = get_option( 'apprco_plugin_options', array() );
        $value   = $options['expire_after_days'] ?? 7;
        echo '<input type="number" id="expire_after_days" name="apprco_plugin_options[expire_after_days]" value="' . esc_attr( $value ) . '" min="1" max="90" />';
        echo '<p class="description">' . esc_html__( 'Days after closing date to keep vacancies before deleting.', 'apprenticeship-connect' ) . '</p>';
    }

    public function display_count_callback(): void {
        $options = get_option( 'apprco_plugin_options', array() );
        $value   = $options['display_count'] ?? 10;
        echo '<input type="number" id="display_count" name="apprco_plugin_options[display_count]" value="' . esc_attr( $value ) . '" min="1" max="100" />';
    }

    public function show_employer_callback(): void {
        $options = get_option( 'apprco_plugin_options', array() );
        $checked = $options['show_employer'] ?? true;
        echo '<input type="checkbox" id="show_employer" name="apprco_plugin_options[show_employer]" ' . checked( $checked, true, false ) . ' />';
        echo '<label for="show_employer">' . esc_html__( 'Show employer name in vacancy listings', 'apprenticeship-connect' ) . '</label>';
    }

    public function show_location_callback(): void {
        $options = get_option( 'apprco_plugin_options', array() );
        $checked = $options['show_location'] ?? true;
        echo '<input type="checkbox" id="show_location" name="apprco_plugin_options[show_location]" ' . checked( $checked, true, false ) . ' />';
        echo '<label for="show_location">' . esc_html__( 'Show location in vacancy listings', 'apprenticeship-connect' ) . '</label>';
    }

    public function show_closing_date_callback(): void {
        $options = get_option( 'apprco_plugin_options', array() );
        $checked = $options['show_closing_date'] ?? true;
        echo '<input type="checkbox" id="show_closing_date" name="apprco_plugin_options[show_closing_date]" ' . checked( $checked, true, false ) . ' />';
        echo '<label for="show_closing_date">' . esc_html__( 'Show closing date in vacancy listings', 'apprenticeship-connect' ) . '</label>';
    }

    public function show_apply_button_callback(): void {
        $options = get_option( 'apprco_plugin_options', array() );
        $checked = $options['show_apply_button'] ?? true;
        echo '<input type="checkbox" id="show_apply_button" name="apprco_plugin_options[show_apply_button]" ' . checked( $checked, true, false ) . ' />';
        echo '<label for="show_apply_button">' . esc_html__( 'Show apply button in vacancy listings', 'apprenticeship-connect' ) . '</label>';
    }

    public function show_no_vacancy_image_callback(): void {
        $options = get_option( 'apprco_plugin_options', array() );
        $checked = $options['show_no_vacancy_image'] ?? true;
        echo '<input type="checkbox" id="show_no_vacancy_image" name="apprco_plugin_options[show_no_vacancy_image]" ' . checked( $checked, true, false ) . ' />';
        echo '<label for="show_no_vacancy_image">' . esc_html__( 'Show "No Vacancy" image when no vacancies are available', 'apprenticeship-connect' ) . '</label>';
    }

    public function no_vacancy_image_callback(): void {
        $options       = get_option( 'apprco_plugin_options', array() );
        $default_image = APPRCO_PLUGIN_URL . 'assets/images/bg-no-vacancy.png';
        $value         = $options['no_vacancy_image'] ?? $default_image;
        echo '<input type="url" id="no_vacancy_image" name="apprco_plugin_options[no_vacancy_image]" value="' . esc_attr( $value ) . '" class="regular-text" />';
        echo '<button type="button" id="no_vacancy_image_button" class="button">' . esc_html__( 'Choose Image', 'apprenticeship-connect' ) . '</button>';
    }

    /**
     * Dashboard page
     */
    public function dashboard_page(): void {
        $sync_status = $this->get_sync_status();
        $scheduler   = Apprco_Scheduler::get_instance();
        $sched_status = $scheduler->get_status();
        $logger      = new Apprco_Import_Logger();
        $log_stats   = $logger->get_stats();
        ?>
        <div class="wrap apprco-dashboard">
            <h1><?php esc_html_e( 'Apprenticeship Connect Dashboard', 'apprenticeship-connect' ); ?></h1>

            <div class="apprco-dashboard-grid">
                <!-- Status Cards -->
                <div class="apprco-card">
                    <h2><?php esc_html_e( 'Sync Status', 'apprenticeship-connect' ); ?></h2>
                    <div class="apprco-stat">
                        <span class="apprco-stat-value"><?php echo esc_html( $sync_status['total_vacancies'] ); ?></span>
                        <span class="apprco-stat-label"><?php esc_html_e( 'Published Vacancies', 'apprenticeship-connect' ); ?></span>
                    </div>
                    <p><strong><?php esc_html_e( 'Last Sync:', 'apprenticeship-connect' ); ?></strong> <?php echo esc_html( $sync_status['last_sync_human'] ); ?></p>
                    <p><strong><?php esc_html_e( 'Next Sync:', 'apprenticeship-connect' ); ?></strong> <?php echo esc_html( $sched_status['next_sync_formatted'] ?? __( 'Not scheduled', 'apprenticeship-connect' ) ); ?></p>
                </div>

                <div class="apprco-card">
                    <h2><?php esc_html_e( 'Quick Actions', 'apprenticeship-connect' ); ?></h2>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-import-wizard' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Import Wizard', 'apprenticeship-connect' ); ?></a>
                        <button type="button" id="apprco-dashboard-sync" class="button"><?php esc_html_e( 'Quick Sync', 'apprenticeship-connect' ); ?></button>
                    </p>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=apprco_vacancy' ) ); ?>" class="button"><?php esc_html_e( 'View Vacancies', 'apprenticeship-connect' ); ?></a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-logs' ) ); ?>" class="button"><?php esc_html_e( 'View Logs', 'apprenticeship-connect' ); ?></a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-settings' ) ); ?>" class="button"><?php esc_html_e( 'Settings', 'apprenticeship-connect' ); ?></a>
                    </p>
                    <div id="apprco-dashboard-result"></div>
                </div>

                <div class="apprco-card">
                    <h2><?php esc_html_e( 'Import Statistics', 'apprenticeship-connect' ); ?></h2>
                    <p><strong><?php esc_html_e( 'Total Imports:', 'apprenticeship-connect' ); ?></strong> <?php echo esc_html( $log_stats['total_runs'] ); ?></p>
                    <?php if ( $log_stats['last_run'] ) : ?>
                        <p><strong><?php esc_html_e( 'Last Import:', 'apprenticeship-connect' ); ?></strong></p>
                        <ul>
                            <li><?php esc_html_e( 'Fetched:', 'apprenticeship-connect' ); ?> <?php echo esc_html( $log_stats['last_run']['total_fetched'] ); ?></li>
                            <li><?php esc_html_e( 'Created:', 'apprenticeship-connect' ); ?> <?php echo esc_html( $log_stats['last_run']['total_created'] ); ?></li>
                            <li><?php esc_html_e( 'Updated:', 'apprenticeship-connect' ); ?> <?php echo esc_html( $log_stats['last_run']['total_updated'] ); ?></li>
                            <li><?php esc_html_e( 'Status:', 'apprenticeship-connect' ); ?> <?php echo esc_html( $log_stats['last_run']['status'] ); ?></li>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="apprco-card">
                    <h2><?php esc_html_e( 'Elementor Integration', 'apprenticeship-connect' ); ?></h2>
                    <?php if ( Apprco_Elementor::is_elementor_active() ) : ?>
                        <p class="apprco-badge apprco-badge-success"><?php esc_html_e( 'Elementor Active', 'apprenticeship-connect' ); ?></p>
                        <p><?php esc_html_e( 'Use Dynamic Tags in Elementor to display vacancy data in your templates.', 'apprenticeship-connect' ); ?></p>
                        <p><?php esc_html_e( 'Available in Loop Grid, Single Post templates, and more.', 'apprenticeship-connect' ); ?></p>
                    <?php else : ?>
                        <p class="apprco-badge apprco-badge-info"><?php esc_html_e( 'Elementor Not Detected', 'apprenticeship-connect' ); ?></p>
                        <p><?php esc_html_e( 'Install Elementor to use dynamic tags and loop grid features.', 'apprenticeship-connect' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
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
     * Import Wizard page
     */
    public function import_wizard_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import Wizard', 'apprenticeship-connect' ); ?></h1>
            <p><?php esc_html_e( 'Use this wizard to import apprenticeship vacancies from external APIs.', 'apprenticeship-connect' ); ?></p>

            <div id="apprco-import-wizard">
                <ul class="wizard-steps">
                    <li class="step active" data-step="connect">
                        <span class="step-number">1</span>
                        <?php esc_html_e( 'Test Connection', 'apprenticeship-connect' ); ?>
                    </li>
                    <li class="step" data-step="configure">
                        <span class="step-number">2</span>
                        <?php esc_html_e( 'Configure Import', 'apprenticeship-connect' ); ?>
                    </li>
                    <li class="step" data-step="preview">
                        <span class="step-number">3</span>
                        <?php esc_html_e( 'Preview Data', 'apprenticeship-connect' ); ?>
                    </li>
                    <li class="step" data-step="execute">
                        <span class="step-number">4</span>
                        <?php esc_html_e( 'Execute Import', 'apprenticeship-connect' ); ?>
                    </li>
                </ul>

                <div class="wizard-content">
                    <!-- Step content rendered by JavaScript -->
                </div>

                <div class="wizard-navigation">
                    <button type="button" class="button wizard-prev" disabled>
                        &larr; <?php esc_html_e( 'Previous', 'apprenticeship-connect' ); ?>
                    </button>
                    <button type="button" class="button button-primary wizard-next" disabled>
                        <?php esc_html_e( 'Next', 'apprenticeship-connect' ); ?> &rarr;
                    </button>
                </div>

                <div class="wizard-status">
                    <?php esc_html_e( 'Select a provider to begin.', 'apprenticeship-connect' ); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Admin settings page
     */
    public function admin_page(): void {
        ?>
        <div class="wrap apprco-settings">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php" class="apprco-form">
                <?php
                settings_fields( 'apprco_plugin_options' );
                do_settings_sections( 'apprco-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get sync status
     *
     * @return array
     */
    private function get_sync_status(): array {
        $last_sync       = get_option( 'apprco_last_sync' );
        $total_vacancies = wp_count_posts( 'apprco_vacancy' );
        $options         = get_option( 'apprco_plugin_options', array() );

        return array(
            'last_sync'       => $last_sync,
            'last_sync_human' => $last_sync ? human_time_diff( $last_sync ) . ' ago' : 'Never',
            'total_vacancies' => $total_vacancies->publish ?? 0,
            'is_configured'   => ! empty( $options['api_subscription_key'] ),
        );
    }

    // AJAX Handlers
    public function ajax_manual_sync(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $core   = Apprco_Core::get_instance();
        $result = $core->manual_sync();
        $stats  = $core->get_import_stats();

        if ( $result ) {
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

        $core   = Apprco_Core::get_instance();
        $result = $core->test_api_connection();

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

        $saved = get_option( 'apprco_plugin_options', array() );

        $api_base_url = isset( $_POST['api_base_url'] ) && $_POST['api_base_url'] !== '' ? esc_url_raw( wp_unslash( $_POST['api_base_url'] ) ) : ( $saved['api_base_url'] ?? '' );
        $api_key      = isset( $_POST['api_subscription_key'] ) && $_POST['api_subscription_key'] !== '' ? sanitize_text_field( wp_unslash( $_POST['api_subscription_key'] ) ) : ( $saved['api_subscription_key'] ?? '' );
        $api_ukprn    = isset( $_POST['api_ukprn'] ) && $_POST['api_ukprn'] !== '' ? sanitize_text_field( wp_unslash( $_POST['api_ukprn'] ) ) : ( $saved['api_ukprn'] ?? '' );

        if ( empty( $api_key ) || empty( $api_base_url ) ) {
            wp_send_json_error( __( 'API credentials not configured.', 'apprenticeship-connect' ) );
        }

        $main_plugin = Apprco_Connector::get_instance();
        $main_plugin->override_options_for_sync( array(
            'api_base_url'         => $api_base_url,
            'api_subscription_key' => $api_key,
            'api_ukprn'            => $api_ukprn,
        ) );

        $sync_result = $main_plugin->manual_sync();
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

        $options                        = get_option( 'apprco_plugin_options', array() );
        $options['api_base_url']        = $api_base_url;
        $options['api_subscription_key'] = $api_key;
        $options['api_ukprn']           = $api_ukprn;

        update_option( 'apprco_plugin_options', $options );
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

        $core = Apprco_Core::get_instance();
        $core->clear_cache();

        wp_send_json_success( __( 'Cache cleared successfully.', 'apprenticeship-connect' ) );
    }

    public function ajax_reschedule_sync(): void {
        check_ajax_referer( 'apprco_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        // If frequency is provided, save it first
        if ( isset( $_POST['frequency'] ) ) {
            $options = get_option( 'apprco_plugin_options', array() );
            $options['sync_frequency'] = sanitize_text_field( wp_unslash( $_POST['frequency'] ) );
            update_option( 'apprco_plugin_options', $options );
        }

        $scheduler = Apprco_Scheduler::get_instance();
        $scheduler->reschedule();

        wp_send_json_success( __( 'Sync rescheduled.', 'apprenticeship-connect' ) );
    }

    /**
     * Import Tasks list/edit page
     */
    public function import_tasks_page(): void {
        $action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
        $task_id = isset( $_GET['task_id'] ) ? absint( $_GET['task_id'] ) : 0;

        if ( $action === 'edit' || $action === 'new' ) {
            $this->render_task_editor( $task_id );
        } else {
            $this->render_tasks_list();
        }
    }

    /**
     * Render import tasks list
     */
    private function render_tasks_list(): void {
        $tasks_manager = Apprco_Import_Tasks::get_instance();
        $tasks = $tasks_manager->get_all();
        ?>
        <div class="wrap apprco-import-tasks">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Import Tasks', 'apprenticeship-connect' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-import-tasks&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'apprenticeship-connect' ); ?></a>
            <hr class="wp-header-end">

            <p class="description"><?php esc_html_e( 'Configure import tasks to fetch apprenticeship vacancies from external APIs. Each task can have its own API configuration, field mappings, and schedule.', 'apprenticeship-connect' ); ?></p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="column-name"><?php esc_html_e( 'Name', 'apprenticeship-connect' ); ?></th>
                        <th scope="col" class="column-status"><?php esc_html_e( 'Status', 'apprenticeship-connect' ); ?></th>
                        <th scope="col" class="column-provider"><?php esc_html_e( 'API Endpoint', 'apprenticeship-connect' ); ?></th>
                        <th scope="col" class="column-last-run"><?php esc_html_e( 'Last Run', 'apprenticeship-connect' ); ?></th>
                        <th scope="col" class="column-stats"><?php esc_html_e( 'Last Results', 'apprenticeship-connect' ); ?></th>
                        <th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'apprenticeship-connect' ); ?></th>
                    </tr>
                </thead>
                <tbody id="apprco-tasks-list">
                    <?php if ( empty( $tasks ) ) : ?>
                        <tr><td colspan="6"><?php esc_html_e( 'No import tasks found. Create one to get started.', 'apprenticeship-connect' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $tasks as $task ) : ?>
                            <tr data-task-id="<?php echo esc_attr( $task['id'] ); ?>">
                                <td class="column-name">
                                    <strong>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-import-tasks&action=edit&task_id=' . $task['id'] ) ); ?>">
                                            <?php echo esc_html( $task['name'] ); ?>
                                        </a>
                                    </strong>
                                    <?php if ( $task['description'] ) : ?>
                                        <br><span class="description"><?php echo esc_html( wp_trim_words( $task['description'], 10 ) ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-status">
                                    <span class="apprco-badge apprco-badge-<?php echo esc_attr( $task['status'] ); ?>">
                                        <?php echo esc_html( ucfirst( $task['status'] ) ); ?>
                                    </span>
                                    <?php if ( $task['schedule_enabled'] ) : ?>
                                        <br><small><?php echo esc_html( ucfirst( $task['schedule_frequency'] ) ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-provider">
                                    <code><?php echo esc_html( $task['api_endpoint'] ); ?></code>
                                    <br><small><?php echo esc_html( wp_parse_url( $task['api_base_url'], PHP_URL_HOST ) ); ?></small>
                                </td>
                                <td class="column-last-run">
                                    <?php if ( $task['last_run_at'] ) : ?>
                                        <?php echo esc_html( human_time_diff( strtotime( $task['last_run_at'] ) ) ); ?> ago
                                        <br><span class="apprco-badge apprco-badge-<?php echo $task['last_run_status'] === 'success' ? 'success' : 'warning'; ?>">
                                            <?php echo esc_html( $task['last_run_status'] ); ?>
                                        </span>
                                    <?php else : ?>
                                        <?php esc_html_e( 'Never', 'apprenticeship-connect' ); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="column-stats">
                                    <?php if ( $task['last_run_at'] ) : ?>
                                        <span title="<?php esc_attr_e( 'Fetched', 'apprenticeship-connect' ); ?>"><?php echo esc_html( $task['last_run_fetched'] ); ?> fetched</span>
                                        <br>
                                        <span title="<?php esc_attr_e( 'Created', 'apprenticeship-connect' ); ?>" style="color: green;"><?php echo esc_html( $task['last_run_created'] ); ?> new</span> /
                                        <span title="<?php esc_attr_e( 'Updated', 'apprenticeship-connect' ); ?>"><?php echo esc_html( $task['last_run_updated'] ); ?> updated</span>
                                        <?php if ( $task['last_run_errors'] > 0 ) : ?>
                                            <br><span style="color: red;"><?php echo esc_html( $task['last_run_errors'] ); ?> errors</span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="column-actions">
                                    <button type="button" class="button button-small apprco-run-task" data-task-id="<?php echo esc_attr( $task['id'] ); ?>" <?php echo $task['status'] !== 'active' ? 'disabled' : ''; ?>>
                                        <?php esc_html_e( 'Run Now', 'apprenticeship-connect' ); ?>
                                    </button>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-import-tasks&action=edit&task_id=' . $task['id'] ) ); ?>" class="button button-small">
                                        <?php esc_html_e( 'Edit', 'apprenticeship-connect' ); ?>
                                    </a>
                                    <button type="button" class="button button-small button-link-delete apprco-delete-task" data-task-id="<?php echo esc_attr( $task['id'] ); ?>">
                                        <?php esc_html_e( 'Delete', 'apprenticeship-connect' ); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Run task
            $('.apprco-run-task').on('click', function() {
                var $btn = $(this);
                var taskId = $btn.data('task-id');
                $btn.prop('disabled', true).text('Running...');

                $.post(apprcoAjax.ajaxurl, {
                    action: 'apprco_run_task',
                    nonce: apprcoAjax.nonce,
                    task_id: taskId
                }, function(response) {
                    if (response.success) {
                        alert('Import completed!\n' + response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                    $btn.prop('disabled', false).text('Run Now');
                });
            });

            // Delete task
            $('.apprco-delete-task').on('click', function() {
                if (!confirm('Are you sure you want to delete this task?')) return;

                var taskId = $(this).data('task-id');
                $.post(apprcoAjax.ajaxurl, {
                    action: 'apprco_delete_task',
                    nonce: apprcoAjax.nonce,
                    task_id: taskId
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render task editor
     *
     * @param int $task_id Task ID (0 for new).
     */
    private function render_task_editor( int $task_id ): void {
        $tasks_manager = Apprco_Import_Tasks::get_instance();
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
        ?>
        <div class="wrap apprco-task-editor">
            <h1>
                <?php echo $is_new ? esc_html__( 'Add New Import Task', 'apprenticeship-connect' ) : esc_html__( 'Edit Import Task', 'apprenticeship-connect' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-import-tasks' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to List', 'apprenticeship-connect' ); ?></a>
            </h1>

            <form id="apprco-task-form" method="post">
                <input type="hidden" name="task_id" value="<?php echo esc_attr( $task['id'] ); ?>">
                <?php wp_nonce_field( 'apprco_admin_nonce', 'apprco_nonce' ); ?>

                <div class="apprco-task-sections">
                    <!-- Basic Info -->
                    <div class="apprco-section">
                        <h2><?php esc_html_e( 'Basic Information', 'apprenticeship-connect' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="task_name"><?php esc_html_e( 'Task Name', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <input type="text" id="task_name" name="name" value="<?php echo esc_attr( $task['name'] ); ?>" class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="task_description"><?php esc_html_e( 'Description', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <textarea id="task_description" name="description" rows="2" class="large-text"><?php echo esc_textarea( $task['description'] ); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="task_status"><?php esc_html_e( 'Status', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <select id="task_status" name="status">
                                        <option value="draft" <?php selected( $task['status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'apprenticeship-connect' ); ?></option>
                                        <option value="active" <?php selected( $task['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'apprenticeship-connect' ); ?></option>
                                        <option value="inactive" <?php selected( $task['status'], 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'apprenticeship-connect' ); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Only active tasks can be run or scheduled.', 'apprenticeship-connect' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- API Configuration -->
                    <div class="apprco-section">
                        <h2><?php esc_html_e( 'API Configuration', 'apprenticeship-connect' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="api_base_url"><?php esc_html_e( 'API Base URL', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <input type="url" id="api_base_url" name="api_base_url" value="<?php echo esc_attr( $task['api_base_url'] ); ?>" class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="api_endpoint"><?php esc_html_e( 'API Endpoint', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <input type="text" id="api_endpoint" name="api_endpoint" value="<?php echo esc_attr( $task['api_endpoint'] ); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e( 'Path appended to base URL (e.g., /vacancy)', 'apprenticeship-connect' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="api_auth_key"><?php esc_html_e( 'Auth Header Name', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <input type="text" id="api_auth_key" name="api_auth_key" value="<?php echo esc_attr( $task['api_auth_key'] ); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="api_auth_value"><?php esc_html_e( 'API Key / Subscription Key', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <input type="password" id="api_auth_value" name="api_auth_value" value="<?php echo esc_attr( $task['api_auth_value'] ); ?>" class="regular-text" autocomplete="new-password">
                                    <p class="description"><?php esc_html_e( 'Your API subscription key', 'apprenticeship-connect' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="api_headers"><?php esc_html_e( 'Additional Headers', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <textarea id="api_headers" name="api_headers" rows="3" class="large-text code"><?php echo esc_textarea( wp_json_encode( $task['api_headers'], JSON_PRETTY_PRINT ) ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'JSON object of headers (e.g., {"X-Version": "2"})', 'apprenticeship-connect' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="api_params"><?php esc_html_e( 'Query Parameters', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <textarea id="api_params" name="api_params" rows="3" class="large-text code"><?php echo esc_textarea( wp_json_encode( $task['api_params'], JSON_PRETTY_PRINT ) ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'JSON object of query params (e.g., {"Sort": "AgeDesc", "Ukprn": "12345678"})', 'apprenticeship-connect' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <h3><?php esc_html_e( 'Test API Connection', 'apprenticeship-connect' ); ?></h3>
                        <p>
                            <button type="button" id="apprco-test-connection" class="button button-primary"><?php esc_html_e( 'Test Connection & Fetch Sample', 'apprenticeship-connect' ); ?></button>
                            <span id="apprco-test-status"></span>
                        </p>
                        <div id="apprco-test-result" class="apprco-test-result" style="display:none;">
                            <h4><?php esc_html_e( 'API Response', 'apprenticeship-connect' ); ?></h4>
                            <div id="apprco-test-summary"></div>
                            <h4><?php esc_html_e( 'Available Fields (click to copy path)', 'apprenticeship-connect' ); ?></h4>
                            <div id="apprco-available-fields"></div>
                            <h4><?php esc_html_e( 'Sample Data (First 10 Records)', 'apprenticeship-connect' ); ?></h4>
                            <div id="apprco-sample-data" style="max-height: 400px; overflow: auto;"></div>
                        </div>
                    </div>

                    <!-- Response Parsing -->
                    <div class="apprco-section">
                        <h2><?php esc_html_e( 'Response Parsing', 'apprenticeship-connect' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="data_path"><?php esc_html_e( 'Data Path', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <input type="text" id="data_path" name="data_path" value="<?php echo esc_attr( $task['data_path'] ); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e( 'JSONPath to the array of items (e.g., "vacancies" or "data.items")', 'apprenticeship-connect' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="total_path"><?php esc_html_e( 'Total Count Path', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <input type="text" id="total_path" name="total_path" value="<?php echo esc_attr( $task['total_path'] ); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e( 'JSONPath to total record count (e.g., "total")', 'apprenticeship-connect' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="unique_id_field"><?php esc_html_e( 'Unique ID Field', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <input type="text" id="unique_id_field" name="unique_id_field" value="<?php echo esc_attr( $task['unique_id_field'] ); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e( 'Field to identify unique records (prevents duplicates)', 'apprenticeship-connect' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="page_param"><?php esc_html_e( 'Page Parameter', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <input type="text" id="page_param" name="page_param" value="<?php echo esc_attr( $task['page_param'] ); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="page_size"><?php esc_html_e( 'Page Size', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <input type="number" id="page_size" name="page_size" value="<?php echo esc_attr( $task['page_size'] ); ?>" min="10" max="500">
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Field Mappings -->
                    <div class="apprco-section">
                        <h2><?php esc_html_e( 'Field Mappings', 'apprenticeship-connect' ); ?></h2>
                        <p class="description"><?php esc_html_e( 'Map API response fields to WordPress post fields and meta. Use dot notation for nested fields (e.g., addresses[0].postcode).', 'apprenticeship-connect' ); ?></p>

                        <table class="wp-list-table widefat fixed" id="apprco-field-mappings">
                            <thead>
                                <tr>
                                    <th style="width: 40%;"><?php esc_html_e( 'Target Field (WordPress)', 'apprenticeship-connect' ); ?></th>
                                    <th style="width: 50%;"><?php esc_html_e( 'Source Field (API)', 'apprenticeship-connect' ); ?></th>
                                    <th style="width: 10%;"></th>
                                </tr>
                            </thead>
                            <tbody id="apprco-mappings-body">
                                <?php foreach ( $task['field_mappings'] as $target => $source ) : ?>
                                    <tr>
                                        <td><input type="text" name="mapping_target[]" value="<?php echo esc_attr( $target ); ?>" class="widefat"></td>
                                        <td><input type="text" name="mapping_source[]" value="<?php echo esc_attr( $source ); ?>" class="widefat"></td>
                                        <td><button type="button" class="button button-small apprco-remove-mapping">&times;</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p>
                            <button type="button" id="apprco-add-mapping" class="button"><?php esc_html_e( 'Add Mapping', 'apprenticeship-connect' ); ?></button>
                            <button type="button" id="apprco-reset-mappings" class="button"><?php esc_html_e( 'Reset to Defaults', 'apprenticeship-connect' ); ?></button>
                        </p>
                    </div>

                    <!-- ETL Transforms -->
                    <div class="apprco-section">
                        <h2><?php esc_html_e( 'ETL Transforms (Advanced)', 'apprenticeship-connect' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="transforms_enabled"><?php esc_html_e( 'Enable Transforms', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <input type="checkbox" id="transforms_enabled" name="transforms_enabled" value="1" <?php checked( $task['transforms_enabled'], 1 ); ?>>
                                    <label for="transforms_enabled"><?php esc_html_e( 'Apply custom PHP transforms to each record', 'apprenticeship-connect' ); ?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="transforms_code"><?php esc_html_e( 'Transform Code', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <textarea id="transforms_code" name="transforms_code" rows="10" class="large-text code" placeholder="// $item contains the API record array&#10;// Modify $item as needed&#10;// Example:&#10;// $item['customField'] = strtoupper($item['title']);"><?php echo esc_textarea( $task['transforms_code'] ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'PHP code to transform each record. The $item variable contains the API record.', 'apprenticeship-connect' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Schedule -->
                    <div class="apprco-section">
                        <h2><?php esc_html_e( 'Schedule', 'apprenticeship-connect' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="schedule_enabled"><?php esc_html_e( 'Enable Schedule', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <input type="checkbox" id="schedule_enabled" name="schedule_enabled" value="1" <?php checked( $task['schedule_enabled'], 1 ); ?>>
                                    <label for="schedule_enabled"><?php esc_html_e( 'Run this task automatically on a schedule', 'apprenticeship-connect' ); ?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="schedule_frequency"><?php esc_html_e( 'Frequency', 'apprenticeship-connect' ); ?></label></th>
                                <td>
                                    <select id="schedule_frequency" name="schedule_frequency">
                                        <option value="hourly" <?php selected( $task['schedule_frequency'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'apprenticeship-connect' ); ?></option>
                                        <option value="twicedaily" <?php selected( $task['schedule_frequency'], 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'apprenticeship-connect' ); ?></option>
                                        <option value="daily" <?php selected( $task['schedule_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'apprenticeship-connect' ); ?></option>
                                        <option value="weekly" <?php selected( $task['schedule_frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'apprenticeship-connect' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_new ? esc_html__( 'Create Task', 'apprenticeship-connect' ) : esc_html__( 'Save Changes', 'apprenticeship-connect' ); ?></button>
                    <?php if ( ! $is_new && $task['status'] === 'active' ) : ?>
                        <button type="button" id="apprco-run-task-now" class="button"><?php esc_html_e( 'Run Task Now', 'apprenticeship-connect' ); ?></button>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <style>
            .apprco-task-sections { max-width: 1000px; }
            .apprco-section { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; }
            .apprco-section h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
            .apprco-test-result { background: #f9f9f9; padding: 15px; margin-top: 15px; border: 1px solid #ddd; }
            #apprco-available-fields { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 15px; }
            #apprco-available-fields .field-tag { background: #e1e1e1; padding: 3px 8px; border-radius: 3px; cursor: pointer; font-family: monospace; font-size: 12px; }
            #apprco-available-fields .field-tag:hover { background: #0073aa; color: #fff; }
            #apprco-sample-data table { font-size: 12px; }
            #apprco-field-mappings input { font-family: monospace; }
            .apprco-badge-active { background: #00a32a; color: #fff; }
            .apprco-badge-inactive { background: #dba617; color: #fff; }
            .apprco-badge-draft { background: #72777c; color: #fff; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var defaultMappings = <?php echo wp_json_encode( Apprco_Import_Tasks::get_default_field_mappings() ); ?>;

            // Test connection
            $('#apprco-test-connection').on('click', function() {
                var $btn = $(this);
                var $status = $('#apprco-test-status');
                var $result = $('#apprco-test-result');

                $btn.prop('disabled', true);
                $status.text('Testing connection...');
                $result.hide();

                // Gather current form values
                var formData = {
                    action: 'apprco_test_task_connection',
                    nonce: $('#apprco_nonce').val(),
                    api_base_url: $('#api_base_url').val(),
                    api_endpoint: $('#api_endpoint').val(),
                    api_auth_key: $('#api_auth_key').val(),
                    api_auth_value: $('#api_auth_value').val(),
                    api_headers: $('#api_headers').val(),
                    api_params: $('#api_params').val(),
                    data_path: $('#data_path').val(),
                    total_path: $('#total_path').val()
                };

                $.post(apprcoAjax.ajaxurl, formData, function(response) {
                    $btn.prop('disabled', false);

                    if (response.success) {
                        $status.html('<span style="color: green;">Connected successfully!</span>');
                        $result.show();

                        var data = response.data;
                        $('#apprco-test-summary').html(
                            '<p><strong>Total Records:</strong> ' + data.total +
                            ' | <strong>Fetched:</strong> ' + data.fetched +
                            ' | <strong>Response Keys:</strong> ' + data.response_keys.join(', ') + '</p>'
                        );

                        // Show available fields
                        var fieldsHtml = '';
                        if (data.available_fields && data.available_fields.length > 0) {
                            data.available_fields.forEach(function(field) {
                                fieldsHtml += '<span class="field-tag" data-field="' + field + '">' + field + '</span>';
                            });
                        }
                        $('#apprco-available-fields').html(fieldsHtml);

                        // Show sample data as table
                        if (data.sample && data.sample.length > 0) {
                            var tableHtml = '<table class="wp-list-table widefat striped"><thead><tr><th>#</th>';
                            var keys = Object.keys(data.sample[0]).slice(0, 8);
                            keys.forEach(function(key) {
                                tableHtml += '<th>' + key + '</th>';
                            });
                            tableHtml += '</tr></thead><tbody>';

                            data.sample.forEach(function(item, idx) {
                                tableHtml += '<tr><td>' + (idx + 1) + '</td>';
                                keys.forEach(function(key) {
                                    var val = item[key];
                                    if (typeof val === 'object') val = JSON.stringify(val).substring(0, 50) + '...';
                                    if (typeof val === 'string' && val.length > 50) val = val.substring(0, 50) + '...';
                                    tableHtml += '<td>' + (val || '') + '</td>';
                                });
                                tableHtml += '</tr>';
                            });
                            tableHtml += '</tbody></table>';
                            $('#apprco-sample-data').html(tableHtml);
                        }
                    } else {
                        $status.html('<span style="color: red;">Error: ' + response.data.error + '</span>');
                        if (response.data.raw_response) {
                            $result.show();
                            $('#apprco-test-summary').html('<pre>' + response.data.raw_response + '</pre>');
                        }
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $status.html('<span style="color: red;">Request failed</span>');
                });
            });

            // Click field tag to copy
            $(document).on('click', '.field-tag', function() {
                var field = $(this).data('field');
                navigator.clipboard.writeText(field);
                $(this).css('background', '#00a32a').css('color', '#fff');
                setTimeout(function() {
                    $('.field-tag').css('background', '').css('color', '');
                }, 500);
            });

            // Add mapping row
            $('#apprco-add-mapping').on('click', function() {
                $('#apprco-mappings-body').append(
                    '<tr>' +
                    '<td><input type="text" name="mapping_target[]" value="" class="widefat"></td>' +
                    '<td><input type="text" name="mapping_source[]" value="" class="widefat"></td>' +
                    '<td><button type="button" class="button button-small apprco-remove-mapping">&times;</button></td>' +
                    '</tr>'
                );
            });

            // Remove mapping row
            $(document).on('click', '.apprco-remove-mapping', function() {
                $(this).closest('tr').remove();
            });

            // Reset mappings to defaults
            $('#apprco-reset-mappings').on('click', function() {
                if (!confirm('Reset all field mappings to defaults?')) return;

                var html = '';
                for (var target in defaultMappings) {
                    html += '<tr>' +
                        '<td><input type="text" name="mapping_target[]" value="' + target + '" class="widefat"></td>' +
                        '<td><input type="text" name="mapping_source[]" value="' + defaultMappings[target] + '" class="widefat"></td>' +
                        '<td><button type="button" class="button button-small apprco-remove-mapping">&times;</button></td>' +
                        '</tr>';
                }
                $('#apprco-mappings-body').html(html);
            });

            // Save task via AJAX
            $('#apprco-task-form').on('submit', function(e) {
                e.preventDefault();

                var $form = $(this);
                var $submitBtn = $form.find('button[type="submit"]');

                // Build field mappings
                var mappings = {};
                $('input[name="mapping_target[]"]').each(function(idx) {
                    var target = $(this).val().trim();
                    var source = $('input[name="mapping_source[]"]').eq(idx).val().trim();
                    if (target && source) {
                        mappings[target] = source;
                    }
                });

                var formData = {
                    action: 'apprco_save_task',
                    nonce: $('#apprco_nonce').val(),
                    task_id: $('input[name="task_id"]').val(),
                    name: $('#task_name').val(),
                    description: $('#task_description').val(),
                    status: $('#task_status').val(),
                    api_base_url: $('#api_base_url').val(),
                    api_endpoint: $('#api_endpoint').val(),
                    api_auth_key: $('#api_auth_key').val(),
                    api_auth_value: $('#api_auth_value').val(),
                    api_headers: $('#api_headers').val(),
                    api_params: $('#api_params').val(),
                    data_path: $('#data_path').val(),
                    total_path: $('#total_path').val(),
                    unique_id_field: $('#unique_id_field').val(),
                    page_param: $('#page_param').val(),
                    page_size: $('#page_size').val(),
                    field_mappings: JSON.stringify(mappings),
                    transforms_enabled: $('#transforms_enabled').is(':checked') ? 1 : 0,
                    transforms_code: $('#transforms_code').val(),
                    schedule_enabled: $('#schedule_enabled').is(':checked') ? 1 : 0,
                    schedule_frequency: $('#schedule_frequency').val()
                };

                $submitBtn.prop('disabled', true).text('Saving...');

                $.post(apprcoAjax.ajaxurl, formData, function(response) {
                    if (response.success) {
                        alert('Task saved successfully!');
                        if (!formData.task_id || formData.task_id == '0') {
                            window.location.href = 'admin.php?page=apprco-import-tasks&action=edit&task_id=' + response.data.task_id;
                        } else {
                            $submitBtn.prop('disabled', false).text('Save Changes');
                        }
                    } else {
                        alert('Error: ' + response.data);
                        $submitBtn.prop('disabled', false).text('Save Changes');
                    }
                }).fail(function() {
                    alert('Request failed');
                    $submitBtn.prop('disabled', false).text('Save Changes');
                });
            });

            // Run task now
            $('#apprco-run-task-now').on('click', function() {
                var $btn = $(this);
                var taskId = $('input[name="task_id"]').val();

                if (!taskId || taskId == '0') {
                    alert('Please save the task first.');
                    return;
                }

                $btn.prop('disabled', true).text('Running...');

                $.post(apprcoAjax.ajaxurl, {
                    action: 'apprco_run_task',
                    nonce: apprcoAjax.nonce,
                    task_id: taskId
                }, function(response) {
                    if (response.success) {
                        alert('Import completed!\n' + response.data.message);
                    } else {
                        alert('Error: ' + response.data);
                    }
                    $btn.prop('disabled', false).text('Run Task Now');
                });
            });
        });
        </script>
        <?php
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
