<?php
/**
 * REST API Controller
 */

if ( ! defined( 'ABSPATH' ) ) die;

class Apprco_REST_Controller {

    private static $instance = null;

    public static function get_instance(): Apprco_REST_Controller {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    public function register_routes(): void {
        register_rest_route( 'apprco/v1', '/stats', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_stats' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( 'apprco/v1', '/tasks', array(
            array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_tasks' ),
                'permission_callback' => array( $this, 'permission_check' ),
            ),
            array(
                'methods' => 'POST',
                'callback' => array( $this, 'create_task' ),
                'permission_callback' => array( $this, 'permission_check' ),
            ),
        ) );

        register_rest_route( 'apprco/v1', '/tasks/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_task' ),
                'permission_callback' => array( $this, 'permission_check' ),
            ),
            array(
                'methods' => 'POST',
                'callback' => array( $this, 'update_task' ),
                'permission_callback' => array( $this, 'permission_check' ),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array( $this, 'delete_task' ),
                'permission_callback' => array( $this, 'permission_check' ),
            ),
        ) );

        register_rest_route( 'apprco/v1', '/tasks/(?P<id>\d+)/run', array(
            'methods' => 'POST',
            'callback' => array( $this, 'run_task' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( 'apprco/v1', '/tasks/test', array(
            'methods' => 'POST',
            'callback' => array( $this, 'test_task' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );

        register_rest_route( 'apprco/v1', '/logs/(?P<import_id>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_import_logs' ),
            'permission_callback' => array( $this, 'permission_check' ),
        ) );
    }

    public function permission_check(): bool {
        return current_user_can( 'manage_options' );
    }

    public function get_stats(): WP_REST_Response {
        $logger = Apprco_Import_Logger::get_instance();
        $stats = $logger->get_stats();

        // Add Resilience Stats from last client run if available
        $stats['resilience'] = get_transient('apprco_last_api_stats') ?: array();

        return new WP_REST_Response( $stats, 200 );
    }

    public function get_tasks(): WP_REST_Response {
        $manager = Apprco_Import_Tasks::get_instance();
        return new WP_REST_Response( $manager->get_all(), 200 );
    }

    public function get_task( WP_REST_Request $request ): WP_REST_Response {
        $manager = Apprco_Import_Tasks::get_instance();
        return new WP_REST_Response( $manager->get( (int) $request['id'] ), 200 );
    }

    public function create_task( WP_REST_Request $request ): WP_REST_Response {
        $manager = Apprco_Import_Tasks::get_instance();
        $id = $manager->create( $request->get_json_params() );
        return new WP_REST_Response( array( 'id' => $id ), 200 );
    }

    public function update_task( WP_REST_Request $request ): WP_REST_Response {
        $manager = Apprco_Import_Tasks::get_instance();
        $success = $manager->update( (int) $request['id'], $request->get_json_params() );
        return new WP_REST_Response( array( 'success' => $success ), 200 );
    }

    public function delete_task( WP_REST_Request $request ): WP_REST_Response {
        $manager = Apprco_Import_Tasks::get_instance();
        $success = $manager->delete( (int) $request['id'] );
        return new WP_REST_Response( array( 'success' => $success ), 200 );
    }

    public function run_task( WP_REST_Request $request ): WP_REST_Response {
        $id = (int) $request['id'];
        $manager = Apprco_Import_Tasks::get_instance();
        $result = $manager->run_import( $id );
        return new WP_REST_Response( $result, $result['success'] ? 200 : 400 );
    }

    public function test_task( WP_REST_Request $request ): WP_REST_Response {
        $data = $request->get_json_params();
        $client = new Apprco_API_Client( $data['api_base_url'] );
        $client->set_default_headers( $data['api_headers'] ?? array() );

        $params = $data['api_params'] ?? array();
        $params[ $data['page_param'] ] = 1;

        $res = $client->get( $data['api_endpoint'], $params );

        // Store resilience stats for UI
        set_transient('apprco_last_api_stats', $client->get_stats(), 300);

        return new WP_REST_Response( $res, 200 );
    }

    public function get_import_logs( WP_REST_Request $request ): WP_REST_Response {
        $logger = Apprco_Import_Logger::get_instance();
        $logs = $logger->get_logs_by_import( $request['import_id'] );
        return new WP_REST_Response( array( 'logs' => $logs ), 200 );
    }
}

add_action( 'rest_api_init', array( Apprco_REST_Controller::get_instance(), 'register_routes' ) );
