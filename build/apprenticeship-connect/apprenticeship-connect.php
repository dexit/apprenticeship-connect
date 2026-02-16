<?php
/**
 * Plugin Name: Apprenticeship Connect
 * Plugin URI: https://wordpress.org/plugins/apprenticeship-connect
 * Description: Apprenticeship Connect is a WordPress plugin that seamlessly integrates with the official UK Government's Find an Apprenticeship service. Features automated cron-based data syncing, Action Scheduler support, comprehensive logging, and full Elementor Loop Grid compatibility.
 * Version: 3.0.0
 * Author: ePark Team
 * Author URI: https://e-park.uk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: apprenticeship-connect
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 *
 * @package ApprenticeshipConnect
 * @version 2.0.0
 * @author ePark Team
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Load Composer autoloader if it exists
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Define plugin constants
define( 'APPRCO_PLUGIN_VERSION', '3.0.0' );
define( 'APPRCO_PLUGIN_FILE', __FILE__ );
define( 'APPRCO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'APPRCO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'APPRCO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'APPRCO_DB_VERSION', '2.0.0' );

// Include required files
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-database.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-settings-manager.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-import-logger.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-api-client.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-geocoder.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-employer.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-import-tasks.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-task-views.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-import-adapter.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-task-scheduler.php';

// Provider abstraction layer
require_once APPRCO_PLUGIN_DIR . 'includes/interfaces/interface-apprco-provider.php';
require_once APPRCO_PLUGIN_DIR . 'includes/providers/abstract-apprco-provider.php';
require_once APPRCO_PLUGIN_DIR . 'includes/providers/class-apprco-provider-registry.php';
require_once APPRCO_PLUGIN_DIR . 'includes/providers/class-apprco-uk-gov-provider.php';

// Core functionality
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-admin.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-elementor.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-meta-box.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-rest-api.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-rest-controller.php';
require_once APPRCO_PLUGIN_DIR . 'includes/rest/class-apprco-rest-proxy.php';
require_once APPRCO_PLUGIN_DIR . 'includes/rest/class-apprco-rest-geocoding.php';
require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-shortcodes.php';

/**
 * Main plugin class
 */
class Apprco_Connector {

    /**
     * Plugin instance
     *
     * @var self|null
     */
    private static $instance = null;


    /**
     * Get plugin instance
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
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'plugins_loaded', array( $this, 'init_integrations' ), 20 );

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Check for database updates
        add_action( 'plugins_loaded', array( $this, 'maybe_upgrade_db' ) );
    }

    /**
     * Initialize plugin
     */
    public function init(): void {
        // Initialize database manager (ensures tables exist)
        $database = Apprco_Database::get_instance();
        $database->init();

        // Initialize and register providers
        $this->register_providers();

        // Initialize import task scheduler
        $task_scheduler = Apprco_Task_Scheduler::get_instance();
        $task_scheduler->init();

        // Initialize admin
        if ( is_admin() ) {
            new Apprco_Admin();
            Apprco_Meta_Box::get_instance();
        }

        // Initialize REST API (extended endpoints)
        Apprco_REST_API::get_instance();

        // Initialize shortcodes and templating
        Apprco_Shortcodes::get_instance();

        // Register custom post type
        $this->register_vacancy_cpt();

        // Register taxonomies
        $this->register_taxonomies();

        // Add shortcode
        add_shortcode( 'apprco_vacancies', array( $this, 'vacancies_shortcode' ) );

        // Enqueue frontend assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        // Add REST API endpoints
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // AJAX handlers for frontend forms
        add_action( 'wp_ajax_apprco_frontend_edit', array( $this, 'ajax_frontend_edit' ) );
        add_action( 'wp_ajax_apprco_search_vacancies', array( $this, 'ajax_search_vacancies' ) );
        add_action( 'wp_ajax_nopriv_apprco_search_vacancies', array( $this, 'ajax_search_vacancies' ) );
    }

    /**
     * Load text domain
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'apprenticeship-connect',
            false,
            dirname( APPRCO_PLUGIN_BASENAME ) . '/languages/'
        );
    }

    /**
     * Register vacancy data providers
     */
    private function register_providers(): void {
        $registry = Apprco_Provider_Registry::get_instance();

        // Register UK Government Apprenticeships provider
        $uk_gov_provider = new Apprco_UK_Gov_Provider();
        $registry->register( $uk_gov_provider );

        // Load provider configurations from Settings Manager
        $settings_manager = Apprco_Settings_Manager::get_instance();

        // Configure UK Gov provider from settings
        $uk_gov_provider->set_config( array(
            'subscription_key' => $settings_manager->get( 'api', 'subscription_key' ),
            'base_url'         => $settings_manager->get( 'api', 'base_url', Apprco_UK_Gov_Provider::BASE_URL ),
            'ukprn'            => $settings_manager->get( 'api', 'ukprn' ),
        ) );

        /**
         * Allow other plugins/themes to register additional providers
         *
         * @param Apprco_Provider_Registry $registry The provider registry instance.
         */
        do_action( 'apprco_register_providers', $registry );
    }

    /**
     * Initialize integrations (Elementor, etc.)
     */
    public function init_integrations(): void {
        // Initialize Elementor integration
        if ( Apprco_Elementor::is_elementor_active() ) {
            Apprco_Elementor::get_instance();
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets(): void {
        // Load modern built frontend assets
        $frontend_asset_file = APPRCO_PLUGIN_DIR . 'assets/build/frontend.asset.php';

        if ( file_exists( $frontend_asset_file ) ) {
            $frontend_asset = include $frontend_asset_file;
            wp_enqueue_style( 'apprco-style', APPRCO_PLUGIN_URL . 'assets/build/style-frontend.css', array(), $frontend_asset['version'] );
            wp_register_script( 'apprco-frontend', APPRCO_PLUGIN_URL . 'assets/build/frontend.js', $frontend_asset['dependencies'], $frontend_asset['version'], true );
        } else {
            // Fallback to legacy assets
            wp_enqueue_style( 'apprco-style', APPRCO_PLUGIN_URL . 'assets/css/apprco.css', array(), APPRCO_PLUGIN_VERSION );
            wp_register_script( 'apprco-frontend', APPRCO_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), APPRCO_PLUGIN_VERSION, true );
        }

        wp_localize_script( 'apprco-frontend', 'apprcoFrontend', array(
            'ajaxurl'  => admin_url( 'admin-ajax.php' ),
            'restUrl'  => rest_url( 'apprco/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'strings'  => array(
                'loading'   => __( 'Loading...', 'apprenticeship-connect' ),
                'saving'    => __( 'Saving...', 'apprenticeship-connect' ),
                'saved'     => __( 'Saved!', 'apprenticeship-connect' ),
                'error'     => __( 'An error occurred.', 'apprenticeship-connect' ),
                'noResults' => __( 'No vacancies found.', 'apprenticeship-connect' ),
            ),
        ) );

        // Enqueue on vacancy pages and archives
        if ( is_singular( 'apprco_vacancy' ) || is_post_type_archive( 'apprco_vacancy' ) ) {
            wp_enqueue_script( 'apprco-frontend' );
        }
    }

    /**
     * AJAX handler for frontend vacancy editing
     */
    public function ajax_frontend_edit(): void {
        // Verify nonce
        if ( ! isset( $_POST['apprco_edit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprco_edit_nonce'] ) ), 'apprco_frontend_edit' ) ) {
            wp_send_json_error( __( 'Security check failed.', 'apprenticeship-connect' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
        }

        $vacancy_id = isset( $_POST['vacancy_id'] ) ? absint( $_POST['vacancy_id'] ) : 0;
        $is_new     = empty( $vacancy_id );

        // Prepare post data
        $post_data = array(
            'post_type'   => 'apprco_vacancy',
            'post_status' => 'publish',
            'post_title'  => isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '',
            'post_author' => get_current_user_id(),
        );

        if ( empty( $post_data['post_title'] ) ) {
            wp_send_json_error( __( 'Title is required.', 'apprenticeship-connect' ) );
        }

        if ( $is_new ) {
            $vacancy_id = wp_insert_post( $post_data, true );
        } else {
            // Check edit permission for existing post
            if ( ! current_user_can( 'edit_post', $vacancy_id ) ) {
                wp_send_json_error( __( 'Permission denied.', 'apprenticeship-connect' ) );
            }
            $post_data['ID'] = $vacancy_id;
            $result = wp_update_post( $post_data, true );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( $result->get_error_message() );
            }
        }

        if ( is_wp_error( $vacancy_id ) ) {
            wp_send_json_error( $vacancy_id->get_error_message() );
        }

        // Save meta fields
        if ( isset( $_POST['meta'] ) && is_array( $_POST['meta'] ) ) {
            $meta_fields = Apprco_Elementor::get_vacancy_meta_fields();
            $meta_data   = array_map( 'wp_unslash', $_POST['meta'] );

            foreach ( $meta_data as $key => $value ) {
                $full_key = '_apprco_' . sanitize_key( $key );

                if ( ! isset( $meta_fields[ $full_key ] ) ) {
                    continue;
                }

                $field_type = $meta_fields[ $full_key ]['type'];

                // Sanitize based on type
                switch ( $field_type ) {
                    case 'url':
                        $value = esc_url_raw( $value );
                        break;
                    case 'textarea':
                        $value = wp_kses_post( $value );
                        break;
                    case 'number':
                        $value = is_numeric( $value ) ? $value : '';
                        break;
                    case 'boolean':
                        $value = $value ? '1' : '0';
                        break;
                    default:
                        $value = sanitize_text_field( $value );
                }

                update_post_meta( $vacancy_id, $full_key, $value );
            }
        }

        wp_send_json_success( array(
            'id'        => $vacancy_id,
            'message'   => $is_new ? __( 'Vacancy created.', 'apprenticeship-connect' ) : __( 'Vacancy updated.', 'apprenticeship-connect' ),
            'permalink' => get_permalink( $vacancy_id ),
        ) );
    }

    /**
     * AJAX handler for vacancy search
     */
    public function ajax_search_vacancies(): void {
        // Verify nonce (optional for public search)
        $args = array(
            'post_type'      => 'apprco_vacancy',
            'post_status'    => 'publish',
            'posts_per_page' => isset( $_GET['per_page'] ) ? min( absint( $_GET['per_page'] ), 50 ) : 10,
            'paged'          => isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1,
        );

        // Search query
        if ( ! empty( $_GET['s'] ) ) {
            $args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
        }

        // Taxonomy filters
        $tax_query = array();

        if ( ! empty( $_GET['level'] ) ) {
            $tax_query[] = array(
                'taxonomy' => 'apprco_level',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( wp_unslash( $_GET['level'] ) ),
            );
        }

        if ( ! empty( $_GET['route'] ) ) {
            $tax_query[] = array(
                'taxonomy' => 'apprco_route',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( wp_unslash( $_GET['route'] ) ),
            );
        }

        if ( ! empty( $tax_query ) ) {
            $args['tax_query'] = $tax_query;
        }

        // Meta query for active vacancies
        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key'     => '_apprco_closing_date',
                'value'   => current_time( 'Y-m-d' ),
                'compare' => '>=',
                'type'    => 'DATE',
            ),
            array(
                'key'     => '_apprco_closing_date',
                'compare' => 'NOT EXISTS',
            ),
        );

        // Ordering
        $args['meta_key'] = '_apprco_posted_date';
        $args['orderby']  = 'meta_value';
        $args['order']    = 'DESC';

        $query = new WP_Query( $args );
        $vacancies = array();

        foreach ( $query->posts as $post ) {
            $vacancies[] = $this->format_vacancy_for_rest( $post );
        }

        wp_send_json_success( array(
            'vacancies'   => $vacancies,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'page'        => $args['paged'],
        ) );
    }

    /**
     * Manual sync function
     *
     * @return bool
     */
    public function manual_sync(): bool {
        $adapter = Apprco_Import_Adapter::get_instance();
        $result  = $adapter->run_manual_sync();
        return $result['success'] ?? false;
    }

    /**
     * Plugin activation
     */
    public function activate(): void {
        // Create database tables
        Apprco_Import_Logger::create_table();
        Apprco_Employer::create_table();
        Apprco_Import_Tasks::create_table();

        // Register CPT for rewrite rules
        $this->register_vacancy_cpt();
        $this->register_taxonomies();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        update_option( 'apprco_plugin_activated', true );
        update_option( 'apprco_db_version', APPRCO_DB_VERSION );

        // Schedule initial sync (delayed by 5 minutes to allow setup)
        wp_schedule_single_event( time() + 300, 'apprco_initial_sync' );
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        // Clear all scheduled events
        $task_scheduler = Apprco_Task_Scheduler::get_instance();
        $task_scheduler->unschedule_all();

        // Legacy WP-Cron cleanup
        $timestamp = wp_next_scheduled( 'apprco_daily_fetch_vacancies' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'apprco_daily_fetch_vacancies' );
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Maybe upgrade database
     */
    public function maybe_upgrade_db(): void {
        $database = Apprco_Database::get_instance();
        $database->maybe_upgrade();
    }

    /**
     * Register Custom Post Type for Vacancies
     */
    public function register_vacancy_cpt(): void {
        $labels = array(
            'name'                  => _x( 'Vacancies', 'Post Type General Name', 'apprenticeship-connect' ),
            'singular_name'         => _x( 'Vacancy', 'Post Type Singular Name', 'apprenticeship-connect' ),
            'menu_name'             => __( 'Vacancies', 'apprenticeship-connect' ),
            'name_admin_bar'        => __( 'Vacancy', 'apprenticeship-connect' ),
            'archives'              => __( 'Vacancy Archives', 'apprenticeship-connect' ),
            'attributes'            => __( 'Vacancy Attributes', 'apprenticeship-connect' ),
            'parent_item_colon'     => __( 'Parent Vacancy:', 'apprenticeship-connect' ),
            'all_items'             => __( 'All Vacancies', 'apprenticeship-connect' ),
            'add_new_item'          => __( 'Add New Vacancy', 'apprenticeship-connect' ),
            'add_new'               => __( 'Add New', 'apprenticeship-connect' ),
            'new_item'              => __( 'New Vacancy', 'apprenticeship-connect' ),
            'edit_item'             => __( 'Edit Vacancy', 'apprenticeship-connect' ),
            'update_item'           => __( 'Update Vacancy', 'apprenticeship-connect' ),
            'view_item'             => __( 'View Vacancy', 'apprenticeship-connect' ),
            'view_items'            => __( 'View Vacancies', 'apprenticeship-connect' ),
            'search_items'          => __( 'Search Vacancy', 'apprenticeship-connect' ),
            'not_found'             => __( 'Not found', 'apprenticeship-connect' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'apprenticeship-connect' ),
            'featured_image'        => __( 'Featured Image', 'apprenticeship-connect' ),
            'set_featured_image'    => __( 'Set featured image', 'apprenticeship-connect' ),
            'remove_featured_image' => __( 'Remove featured image', 'apprenticeship-connect' ),
            'use_featured_image'    => __( 'Use as featured image', 'apprenticeship-connect' ),
            'insert_into_item'      => __( 'Insert into vacancy', 'apprenticeship-connect' ),
            'uploaded_to_this_item' => __( 'Uploaded to this vacancy', 'apprenticeship-connect' ),
            'items_list'            => __( 'Vacancies list', 'apprenticeship-connect' ),
            'items_list_navigation' => __( 'Vacancies list navigation', 'apprenticeship-connect' ),
            'filter_items_list'     => __( 'Filter vacancies list', 'apprenticeship-connect' ),
        );

        $args = array(
            'label'               => __( 'Vacancy', 'apprenticeship-connect' ),
            'description'         => __( 'Apprenticeship Vacancies from external API', 'apprenticeship-connect' ),
            'labels'              => $labels,
            'supports'            => array( 'title', 'editor', 'custom-fields', 'author', 'thumbnail', 'excerpt' ),
            'taxonomies'          => array( 'apprco_level', 'apprco_route', 'apprco_employer' ),
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-welcome-learn-more',
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'can_export'          => true,
            'has_archive'         => true,
            'rewrite'             => array(
                'slug'       => 'vacancies',
                'with_front' => false,
            ),
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'show_in_rest'        => true,
            'rest_base'           => 'vacancies',
        );

        register_post_type( 'apprco_vacancy', $args );
    }

    /**
     * Register taxonomies for vacancies
     */
    public function register_taxonomies(): void {
        // Apprenticeship Level taxonomy
        register_taxonomy(
            'apprco_level',
            'apprco_vacancy',
            array(
                'labels'            => array(
                    'name'          => __( 'Apprenticeship Levels', 'apprenticeship-connect' ),
                    'singular_name' => __( 'Level', 'apprenticeship-connect' ),
                ),
                'hierarchical'      => true,
                'public'            => true,
                'show_in_rest'      => true,
                'show_admin_column' => true,
                'rewrite'           => array( 'slug' => 'apprenticeship-level' ),
            )
        );

        // Course Route taxonomy
        register_taxonomy(
            'apprco_route',
            'apprco_vacancy',
            array(
                'labels'            => array(
                    'name'          => __( 'Course Routes', 'apprenticeship-connect' ),
                    'singular_name' => __( 'Route', 'apprenticeship-connect' ),
                ),
                'hierarchical'      => true,
                'public'            => true,
                'show_in_rest'      => true,
                'show_admin_column' => true,
                'rewrite'           => array( 'slug' => 'course-route' ),
            )
        );

        // Employer taxonomy
        register_taxonomy(
            'apprco_employer',
            'apprco_vacancy',
            array(
                'labels'            => array(
                    'name'          => __( 'Employers', 'apprenticeship-connect' ),
                    'singular_name' => __( 'Employer', 'apprenticeship-connect' ),
                ),
                'hierarchical'      => false,
                'public'            => true,
                'show_in_rest'      => true,
                'show_admin_column' => true,
                'rewrite'           => array( 'slug' => 'employer' ),
            )
        );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void {
        register_rest_route(
            'apprco/v1',
            '/vacancies',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_get_vacancies' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'per_page' => array(
                        'default'           => 10,
                        'sanitize_callback' => 'absint',
                    ),
                    'page'     => array(
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ),
                    'level'    => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'route'    => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'employer' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            'apprco/v1',
            '/sync',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_trigger_sync' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        register_rest_route(
            'apprco/v1',
            '/status',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_get_status' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        // Register CORS proxy endpoints for Display Advert API v2
        $proxy = new Apprco_REST_Proxy();
        $proxy->register_routes();

        // Register geocoding endpoints for location lookup
        $geocoding = new Apprco_REST_Geocoding();
        $geocoding->register_routes();

        // Register settings and import REST endpoints
        $rest_controller = Apprco_REST_Controller::get_instance();
        $rest_controller->register_routes();

        $settings_manager = Apprco_Settings_Manager::get_instance();
        $settings_manager->register_rest_routes();
    }

    /**
     * REST API: Get vacancies
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_get_vacancies( WP_REST_Request $request ): WP_REST_Response {
        $args = array(
            'post_type'      => 'apprco_vacancy',
            'post_status'    => 'publish',
            'posts_per_page' => $request->get_param( 'per_page' ),
            'paged'          => $request->get_param( 'page' ),
            'orderby'        => 'meta_value',
            'meta_key'       => '_apprco_posted_date',
            'order'          => 'DESC',
        );

        // Add taxonomy filters
        $tax_query = array();

        if ( $request->get_param( 'level' ) ) {
            $tax_query[] = array(
                'taxonomy' => 'apprco_level',
                'field'    => 'slug',
                'terms'    => $request->get_param( 'level' ),
            );
        }

        if ( $request->get_param( 'route' ) ) {
            $tax_query[] = array(
                'taxonomy' => 'apprco_route',
                'field'    => 'slug',
                'terms'    => $request->get_param( 'route' ),
            );
        }

        if ( $request->get_param( 'employer' ) ) {
            $tax_query[] = array(
                'taxonomy' => 'apprco_employer',
                'field'    => 'slug',
                'terms'    => $request->get_param( 'employer' ),
            );
        }

        if ( ! empty( $tax_query ) ) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query( $args );
        $vacancies = array();

        foreach ( $query->posts as $post ) {
            $vacancies[] = $this->format_vacancy_for_rest( $post );
        }

        return new WP_REST_Response(
            array(
                'vacancies'   => $vacancies,
                'total'       => $query->found_posts,
                'total_pages' => $query->max_num_pages,
                'page'        => $request->get_param( 'page' ),
            ),
            200
        );
    }

    /**
     * Format vacancy post for REST response
     *
     * @param WP_Post $post Post object.
     * @return array
     */
    private function format_vacancy_for_rest( WP_Post $post ): array {
        $meta_fields = Apprco_Elementor::get_vacancy_meta_fields();
        $meta_data   = array();

        foreach ( array_keys( $meta_fields ) as $meta_key ) {
            $clean_key              = str_replace( '_apprco_', '', $meta_key );
            $meta_data[ $clean_key ] = get_post_meta( $post->ID, $meta_key, true );
        }

        return array(
            'id'           => $post->ID,
            'title'        => $post->post_title,
            'content'      => $post->post_content,
            'excerpt'      => $post->post_excerpt,
            'permalink'    => get_permalink( $post->ID ),
            'date'         => $post->post_date,
            'modified'     => $post->post_modified,
            'meta'         => $meta_data,
            'levels'       => wp_get_post_terms( $post->ID, 'apprco_level', array( 'fields' => 'names' ) ),
            'routes'       => wp_get_post_terms( $post->ID, 'apprco_route', array( 'fields' => 'names' ) ),
            'employers'    => wp_get_post_terms( $post->ID, 'apprco_employer', array( 'fields' => 'names' ) ),
        );
    }

    /**
     * REST API: Trigger sync
     *
     * @return WP_REST_Response
     */
    public function rest_trigger_sync(): WP_REST_Response {
        $result = $this->manual_sync();

        return new WP_REST_Response(
            array(
                'success' => $result,
                'message' => $result ? 'Sync completed successfully.' : 'Sync failed.',
            ),
            $result ? 200 : 500
        );
    }

    /**
     * REST API: Get status
     *
     * @return WP_REST_Response
     */
    public function rest_get_status(): WP_REST_Response {
        $adapter = Apprco_Import_Adapter::get_instance();
        $stats   = $adapter->get_stats();

        return new WP_REST_Response(
            array(
                'sync' => $stats,
            ),
            200
        );
    }

    /**
     * Shortcode to display vacancies
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function vacancies_shortcode( $atts ): string {
        $settings_manager = Apprco_Settings_Manager::get_instance();

        // Use only settings, no shortcode parameters
        $display_settings = array(
            'count'               => $settings_manager->get( 'display', 'items_per_page', 10 ),
            'show_employer'       => $settings_manager->get( 'display', 'show_employer', true ),
            'show_location'       => $settings_manager->get( 'display', 'show_location', true ),
            'show_closing_date'   => $settings_manager->get( 'display', 'show_closing_date', true ),
            'show_apply_button'   => $settings_manager->get( 'display', 'show_apply_button', true ),
            'no_vacancy_image'    => $settings_manager->get( 'display', 'no_vacancy_image', APPRCO_PLUGIN_URL . 'assets/images/bg-no-vacancy.png' ),
            'show_no_vacancy_image' => $settings_manager->get( 'display', 'show_no_vacancy_image', true ),
        );

        $args = array(
            'post_type'      => 'apprco_vacancy',
            'post_status'    => 'publish',
            'posts_per_page' => absint( $display_settings['count'] ),
            'orderby'        => 'meta_value',
            'meta_key'       => '_apprco_posted_date',
            'order'          => 'DESC',
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_apprco_closing_date',
                    'value'   => current_time( 'Y-m-d' ),
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
                array(
                    'key'     => '_apprco_closing_date',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        $vacancies_query = new WP_Query( $args );

        ob_start();

        if ( $vacancies_query->have_posts() ) {
            echo '<div class="apprco-vacancies-list">';
            while ( $vacancies_query->have_posts() ) {
                $vacancies_query->the_post();
                $this->display_vacancy_item( $display_settings );
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<div class="apprco-no-vacancies">';
            if ( $display_settings['show_no_vacancy_image'] && ! empty( $display_settings['no_vacancy_image'] ) ) {
                echo '<img src="' . esc_url( $display_settings['no_vacancy_image'] ) . '" alt="' . esc_attr__( 'No vacancies available', 'apprenticeship-connect' ) . '" class="apprco-no-vacancy-image" />';
            }
            echo '<p>' . esc_html__( 'No vacancies found at the moment.', 'apprenticeship-connect' ) . '</p>';
            echo '</div>';
        }

        return ob_get_clean();
    }

    /**
     * Display individual vacancy item
     *
     * @param array $display_settings Display settings.
     */
    private function display_vacancy_item( array $display_settings ): void {
        $title             = get_the_title();
        $vacancy_url       = get_post_meta( get_the_ID(), '_apprco_vacancy_url', true );
        $short_description = get_post_meta( get_the_ID(), '_apprco_vacancy_description_short', true );
        $employer_name     = get_post_meta( get_the_ID(), '_apprco_employer_name', true );
        $postcode          = get_post_meta( get_the_ID(), '_apprco_postcode', true );
        $closing_date      = get_post_meta( get_the_ID(), '_apprco_closing_date', true );
        $level             = get_post_meta( get_the_ID(), '_apprco_apprenticeship_level', true );

        echo '<div class="apprco-vacancy-item">';
        echo '<h3><a href="' . esc_url( $vacancy_url ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a></h3>';

        if ( $level ) {
            echo '<span class="apprco-level-badge">' . esc_html( $level ) . '</span>';
        }

        if ( $display_settings['show_employer'] && $employer_name ) {
            echo '<p class="apprco-employer"><strong>' . esc_html__( 'Employer:', 'apprenticeship-connect' ) . '</strong> ' . esc_html( $employer_name );
            if ( $display_settings['show_location'] && $postcode ) {
                echo ' - ' . esc_html( $postcode );
            }
            echo '</p>';
        }

        if ( $short_description ) {
            echo '<p class="apprco-description">' . wp_kses_post( $short_description ) . '</p>';
        }

        if ( $display_settings['show_closing_date'] && $closing_date ) {
            $formatted_date = wp_date( get_option( 'date_format' ), strtotime( $closing_date ) );
            echo '<p class="apprco-closing-date"><strong>' . esc_html__( 'Closing Date:', 'apprenticeship-connect' ) . '</strong> ' . esc_html( $formatted_date ) . '</p>';
        }

        if ( $display_settings['show_apply_button'] ) {
            echo '<p class="apprco-apply-link"><a href="' . esc_url( $vacancy_url ) . '" target="_blank" rel="noopener" class="apprco-apply-button">' . esc_html__( 'Apply Now', 'apprenticeship-connect' ) . ' &raquo;</a></p>';
        }

        echo '</div>';
    }
}

// Initialize the plugin
Apprco_Connector::get_instance();
