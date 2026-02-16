<?php
/**
 * REST API class for vacancy CRUD operations
 *
 * @package ApprenticeshipConnect
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles REST API endpoints for vacancies
 */
class Apprco_REST_API {

    /**
     * Plugin instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * REST namespace
     *
     * @var string
     */
    const NAMESPACE = 'apprco/v1';

    /**
     * Get singleton instance
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
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void {
        // Get single vacancy
        register_rest_route(
            self::NAMESPACE,
            '/vacancy/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_vacancy' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'id' => array(
                            'required'          => true,
                            'validate_callback' => array( $this, 'validate_vacancy_id' ),
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_vacancy' ),
                    'permission_callback' => array( $this, 'check_edit_permission' ),
                    'args'                => $this->get_vacancy_schema_args(),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_vacancy' ),
                    'permission_callback' => array( $this, 'check_delete_permission' ),
                    'args'                => array(
                        'id' => array(
                            'required'          => true,
                            'validate_callback' => array( $this, 'validate_vacancy_id' ),
                        ),
                        'force' => array(
                            'default'           => false,
                            'type'              => 'boolean',
                            'description'       => __( 'Force delete (skip trash)', 'apprenticeship-connect' ),
                        ),
                    ),
                ),
            )
        );

        // Create vacancy
        register_rest_route(
            self::NAMESPACE,
            '/vacancy',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_vacancy' ),
                'permission_callback' => array( $this, 'check_create_permission' ),
                'args'                => $this->get_vacancy_schema_args( true ),
            )
        );

        // Bulk operations
        register_rest_route(
            self::NAMESPACE,
            '/vacancies/bulk',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'bulk_update' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
                'args'                => array(
                    'ids'    => array(
                        'required'          => true,
                        'type'              => 'array',
                        'items'             => array( 'type' => 'integer' ),
                        'description'       => __( 'Array of vacancy IDs', 'apprenticeship-connect' ),
                    ),
                    'action' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'enum'              => array( 'publish', 'draft', 'trash', 'delete' ),
                        'description'       => __( 'Bulk action to perform', 'apprenticeship-connect' ),
                    ),
                ),
            )
        );

        // Get vacancy meta schema
        register_rest_route(
            self::NAMESPACE,
            '/vacancy/schema',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_meta_schema' ),
                'permission_callback' => '__return_true',
            )
        );

        // Search vacancies with filters
        register_rest_route(
            self::NAMESPACE,
            '/vacancies/search',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'search_vacancies' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'search'   => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'employer' => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'level'    => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'route'    => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'postcode' => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'distance' => array(
                        'type'              => 'integer',
                        'default'           => 25,
                    ),
                    'per_page' => array(
                        'type'              => 'integer',
                        'default'           => 10,
                        'maximum'           => 100,
                    ),
                    'page'     => array(
                        'type'              => 'integer',
                        'default'           => 1,
                    ),
                    'orderby'  => array(
                        'type'              => 'string',
                        'default'           => 'posted_date',
                        'enum'              => array( 'posted_date', 'closing_date', 'title', 'employer' ),
                    ),
                    'order'    => array(
                        'type'              => 'string',
                        'default'           => 'DESC',
                        'enum'              => array( 'ASC', 'DESC' ),
                    ),
                    'active_only' => array(
                        'type'              => 'boolean',
                        'default'           => true,
                    ),
                ),
            )
        );

        // Get filter options (for dropdowns)
        register_rest_route(
            self::NAMESPACE,
            '/vacancies/filters',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_filter_options' ),
                'permission_callback' => '__return_true',
            )
        );

        // Get example API response for a provider
        register_rest_route(
            self::NAMESPACE,
            '/provider/(?P<provider_id>[a-z0-9-]+)/example-response',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_example_response' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'provider_id' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => array( $this, 'validate_provider_id' ),
                    ),
                ),
            )
        );

        // Get example API request for a provider
        register_rest_route(
            self::NAMESPACE,
            '/provider/(?P<provider_id>[a-z0-9-]+)/example-request',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_example_request' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'provider_id' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => array( $this, 'validate_provider_id' ),
                    ),
                ),
            )
        );

        // Get response body template for a provider
        register_rest_route(
            self::NAMESPACE,
            '/provider/(?P<provider_id>[a-z0-9-]+)/response-template',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_response_template' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'provider_id' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => array( $this, 'validate_provider_id' ),
                    ),
                ),
            )
        );

        // Get request body template for a provider
        register_rest_route(
            self::NAMESPACE,
            '/provider/(?P<provider_id>[a-z0-9-]+)/request-body-template',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_request_body_template' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'provider_id' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => array( $this, 'validate_provider_id' ),
                    ),
                ),
            )
        );

        // Get rate limit information for a provider
        register_rest_route(
            self::NAMESPACE,
            '/provider/(?P<provider_id>[a-z0-9-]+)/rate-limits',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_rate_limits' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'provider_id' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => array( $this, 'validate_provider_id' ),
                    ),
                ),
            )
        );

        // Extract template variables from custom data
        register_rest_route(
            self::NAMESPACE,
            '/tools/extract-template-vars',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'extract_template_variables' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'data' => array(
                        'required'          => true,
                        'type'              => 'object',
                        'description'       => 'JSON data structure to analyze',
                    ),
                ),
            )
        );
    }

    /**
     * Get vacancy schema args
     *
     * @param bool $require_title Whether title is required.
     * @return array
     */
    private function get_vacancy_schema_args( bool $require_title = false ): array {
        return array(
            'title' => array(
                'type'              => 'string',
                'required'          => $require_title,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'content' => array(
                'type'              => 'string',
                'sanitize_callback' => 'wp_kses_post',
            ),
            'excerpt' => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            'status' => array(
                'type'              => 'string',
                'default'           => 'publish',
                'enum'              => array( 'publish', 'draft', 'pending', 'private' ),
            ),
            'meta' => array(
                'type'              => 'object',
                'properties'        => $this->get_meta_properties(),
            ),
            'taxonomies' => array(
                'type'              => 'object',
                'properties'        => array(
                    'level'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                    'route'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                    'employer' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                ),
            ),
        );
    }

    /**
     * Get meta field properties for schema
     *
     * @return array
     */
    private function get_meta_properties(): array {
        $meta_fields = Apprco_Elementor::get_vacancy_meta_fields();
        $properties  = array();

        foreach ( $meta_fields as $key => $field ) {
            $clean_key = str_replace( '_apprco_', '', $key );
            $type      = 'string';

            switch ( $field['type'] ) {
                case 'number':
                    $type = 'number';
                    break;
                case 'boolean':
                    $type = 'boolean';
                    break;
            }

            $properties[ $clean_key ] = array(
                'type'        => $type,
                'description' => $field['label'],
            );
        }

        return $properties;
    }

    /**
     * Validate vacancy ID
     *
     * @param mixed $value Value to validate.
     * @return bool
     */
    public function validate_vacancy_id( $value ): bool {
        $post = get_post( absint( $value ) );
        return $post && $post->post_type === 'apprco_vacancy';
    }

    /**
     * Check edit permission
     *
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    public function check_edit_permission( WP_REST_Request $request ): bool {
        $post_id = $request->get_param( 'id' );
        if ( $post_id ) {
            return current_user_can( 'edit_post', $post_id );
        }
        return current_user_can( 'edit_posts' );
    }

    /**
     * Check create permission
     *
     * @return bool
     */
    public function check_create_permission(): bool {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Check delete permission
     *
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    public function check_delete_permission( WP_REST_Request $request ): bool {
        $post_id = $request->get_param( 'id' );
        return current_user_can( 'delete_post', $post_id );
    }

    /**
     * Get single vacancy
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_vacancy( WP_REST_Request $request ) {
        $post_id = absint( $request->get_param( 'id' ) );
        $post    = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'apprco_vacancy' ) {
            return new WP_Error(
                'vacancy_not_found',
                __( 'Vacancy not found.', 'apprenticeship-connect' ),
                array( 'status' => 404 )
            );
        }

        return rest_ensure_response( $this->format_vacancy( $post ) );
    }

    /**
     * Create vacancy
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function create_vacancy( WP_REST_Request $request ) {
        $post_data = array(
            'post_type'    => 'apprco_vacancy',
            'post_title'   => $request->get_param( 'title' ),
            'post_content' => $request->get_param( 'content' ) ?? '',
            'post_excerpt' => $request->get_param( 'excerpt' ) ?? '',
            'post_status'  => $request->get_param( 'status' ) ?? 'publish',
            'post_author'  => get_current_user_id(),
        );

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Save meta
        $this->save_vacancy_meta( $post_id, $request->get_param( 'meta' ) ?? array() );

        // Save taxonomies
        $this->save_vacancy_taxonomies( $post_id, $request->get_param( 'taxonomies' ) ?? array() );

        $post = get_post( $post_id );

        return rest_ensure_response( array(
            'success' => true,
            'id'      => $post_id,
            'vacancy' => $this->format_vacancy( $post ),
        ) );
    }

    /**
     * Update vacancy
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_vacancy( WP_REST_Request $request ) {
        $post_id = absint( $request->get_param( 'id' ) );
        $post    = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'apprco_vacancy' ) {
            return new WP_Error(
                'vacancy_not_found',
                __( 'Vacancy not found.', 'apprenticeship-connect' ),
                array( 'status' => 404 )
            );
        }

        $post_data = array( 'ID' => $post_id );

        if ( $request->has_param( 'title' ) ) {
            $post_data['post_title'] = $request->get_param( 'title' );
        }
        if ( $request->has_param( 'content' ) ) {
            $post_data['post_content'] = $request->get_param( 'content' );
        }
        if ( $request->has_param( 'excerpt' ) ) {
            $post_data['post_excerpt'] = $request->get_param( 'excerpt' );
        }
        if ( $request->has_param( 'status' ) ) {
            $post_data['post_status'] = $request->get_param( 'status' );
        }

        if ( count( $post_data ) > 1 ) {
            $result = wp_update_post( $post_data, true );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        // Update meta
        if ( $request->has_param( 'meta' ) ) {
            $this->save_vacancy_meta( $post_id, $request->get_param( 'meta' ) );
        }

        // Update taxonomies
        if ( $request->has_param( 'taxonomies' ) ) {
            $this->save_vacancy_taxonomies( $post_id, $request->get_param( 'taxonomies' ) );
        }

        $post = get_post( $post_id );

        return rest_ensure_response( array(
            'success' => true,
            'vacancy' => $this->format_vacancy( $post ),
        ) );
    }

    /**
     * Delete vacancy
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_vacancy( WP_REST_Request $request ) {
        $post_id = absint( $request->get_param( 'id' ) );
        $force   = $request->get_param( 'force' );

        $result = wp_delete_post( $post_id, $force );

        if ( ! $result ) {
            return new WP_Error(
                'delete_failed',
                __( 'Failed to delete vacancy.', 'apprenticeship-connect' ),
                array( 'status' => 500 )
            );
        }

        return rest_ensure_response( array(
            'success' => true,
            'deleted' => $post_id,
            'trashed' => ! $force,
        ) );
    }

    /**
     * Bulk update vacancies
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function bulk_update( WP_REST_Request $request ) {
        $ids    = $request->get_param( 'ids' );
        $action = $request->get_param( 'action' );

        $results = array(
            'success' => array(),
            'failed'  => array(),
        );

        foreach ( $ids as $id ) {
            $post = get_post( absint( $id ) );
            if ( ! $post || $post->post_type !== 'apprco_vacancy' ) {
                $results['failed'][] = $id;
                continue;
            }

            switch ( $action ) {
                case 'publish':
                    wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
                    $results['success'][] = $id;
                    break;

                case 'draft':
                    wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
                    $results['success'][] = $id;
                    break;

                case 'trash':
                    wp_trash_post( $id );
                    $results['success'][] = $id;
                    break;

                case 'delete':
                    wp_delete_post( $id, true );
                    $results['success'][] = $id;
                    break;
            }
        }

        return rest_ensure_response( $results );
    }

    /**
     * Search vacancies with filters
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function search_vacancies( WP_REST_Request $request ) {
        $args = array(
            'post_type'      => 'apprco_vacancy',
            'post_status'    => 'publish',
            'posts_per_page' => min( $request->get_param( 'per_page' ), 100 ),
            'paged'          => $request->get_param( 'page' ),
        );

        // Search term
        if ( $request->get_param( 'search' ) ) {
            $args['s'] = $request->get_param( 'search' );
        }

        // Meta queries
        $meta_query = array( 'relation' => 'AND' );

        // Filter out expired if requested
        if ( $request->get_param( 'active_only' ) ) {
            $meta_query[] = array(
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
        }

        // Postcode/distance filtering (if coordinates available)
        if ( $request->get_param( 'postcode' ) && $request->get_param( 'distance' ) ) {
            // This would require geocoding the postcode
            // For now, filter by postcode prefix match
            $postcode = strtoupper( $request->get_param( 'postcode' ) );
            $postcode_prefix = substr( preg_replace( '/\s+/', '', $postcode ), 0, 3 );
            $meta_query[] = array(
                'key'     => '_apprco_postcode',
                'value'   => $postcode_prefix,
                'compare' => 'LIKE',
            );
        }

        if ( count( $meta_query ) > 1 ) {
            $args['meta_query'] = $meta_query;
        }

        // Taxonomy queries
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

        // Ordering
        $orderby = $request->get_param( 'orderby' );
        switch ( $orderby ) {
            case 'closing_date':
                $args['meta_key'] = '_apprco_closing_date';
                $args['orderby']  = 'meta_value';
                break;
            case 'title':
                $args['orderby'] = 'title';
                break;
            case 'employer':
                $args['meta_key'] = '_apprco_employer_name';
                $args['orderby']  = 'meta_value';
                break;
            default:
                $args['meta_key'] = '_apprco_posted_date';
                $args['orderby']  = 'meta_value';
        }
        $args['order'] = $request->get_param( 'order' );

        $query     = new WP_Query( $args );
        $vacancies = array();

        foreach ( $query->posts as $post ) {
            $vacancies[] = $this->format_vacancy( $post );
        }

        return rest_ensure_response( array(
            'vacancies'   => $vacancies,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'page'        => $request->get_param( 'page' ),
            'per_page'    => $request->get_param( 'per_page' ),
        ) );
    }

    /**
     * Get filter options
     *
     * @return WP_REST_Response
     */
    public function get_filter_options() {
        $levels = get_terms( array(
            'taxonomy'   => 'apprco_level',
            'hide_empty' => true,
        ) );

        $routes = get_terms( array(
            'taxonomy'   => 'apprco_route',
            'hide_empty' => true,
        ) );

        $employers = get_terms( array(
            'taxonomy'   => 'apprco_employer',
            'hide_empty' => true,
            'number'     => 100,
        ) );

        return rest_ensure_response( array(
            'levels'    => wp_list_pluck( $levels, 'name', 'slug' ),
            'routes'    => wp_list_pluck( $routes, 'name', 'slug' ),
            'employers' => wp_list_pluck( $employers, 'name', 'slug' ),
        ) );
    }

    /**
     * Get meta schema
     *
     * @return WP_REST_Response
     */
    public function get_meta_schema() {
        $meta_fields = Apprco_Elementor::get_vacancy_meta_fields();
        $schema      = array();

        foreach ( $meta_fields as $key => $field ) {
            $clean_key = str_replace( '_apprco_', '', $key );
            $schema[ $clean_key ] = array(
                'key'   => $key,
                'label' => $field['label'],
                'type'  => $field['type'],
            );
        }

        return rest_ensure_response( $schema );
    }

    /**
     * Format vacancy for response
     *
     * @param WP_Post $post Post object.
     * @return array
     */
    private function format_vacancy( WP_Post $post ): array {
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
            'status'       => $post->post_status,
            'permalink'    => get_permalink( $post->ID ),
            'edit_link'    => current_user_can( 'edit_post', $post->ID ) ? get_edit_post_link( $post->ID, 'raw' ) : null,
            'date'         => $post->post_date,
            'modified'     => $post->post_modified,
            'author'       => get_the_author_meta( 'display_name', $post->post_author ),
            'meta'         => $meta_data,
            'taxonomies'   => array(
                'levels'    => wp_get_post_terms( $post->ID, 'apprco_level', array( 'fields' => 'names' ) ),
                'routes'    => wp_get_post_terms( $post->ID, 'apprco_route', array( 'fields' => 'names' ) ),
                'employers' => wp_get_post_terms( $post->ID, 'apprco_employer', array( 'fields' => 'names' ) ),
            ),
        );
    }

    /**
     * Save vacancy meta from request
     *
     * @param int   $post_id Post ID.
     * @param array $meta    Meta data.
     */
    private function save_vacancy_meta( int $post_id, array $meta ): void {
        $meta_fields = Apprco_Elementor::get_vacancy_meta_fields();

        foreach ( $meta as $key => $value ) {
            $full_key = '_apprco_' . $key;

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

            update_post_meta( $post_id, $full_key, $value );
        }
    }

    /**
     * Save vacancy taxonomies from request
     *
     * @param int   $post_id    Post ID.
     * @param array $taxonomies Taxonomy data.
     */
    private function save_vacancy_taxonomies( int $post_id, array $taxonomies ): void {
        $tax_map = array(
            'level'    => 'apprco_level',
            'route'    => 'apprco_route',
            'employer' => 'apprco_employer',
        );

        foreach ( $tax_map as $key => $taxonomy ) {
            if ( isset( $taxonomies[ $key ] ) ) {
                $terms = is_array( $taxonomies[ $key ] ) ? $taxonomies[ $key ] : array( $taxonomies[ $key ] );
                wp_set_object_terms( $post_id, $terms, $taxonomy );
            }
        }
    }

    /**
     * Get example API response for a provider
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_example_response( WP_REST_Request $request ) {
        $provider_id = $request->get_param( 'provider_id' );

        try {
            $provider = $this->get_provider_instance( $provider_id );

            $example = $provider->get_example_response();

            return new WP_REST_Response( $example, 200 );

        } catch ( Exception $e ) {
            return new WP_Error(
                'provider_error',
                sprintf( 'Failed to get example response: %s', $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Get example API request for a provider
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_example_request( WP_REST_Request $request ) {
        $provider_id = $request->get_param( 'provider_id' );

        try {
            $provider = $this->get_provider_instance( $provider_id );

            $example = $provider->get_example_request();

            return new WP_REST_Response( $example, 200 );

        } catch ( Exception $e ) {
            return new WP_Error(
                'provider_error',
                sprintf( 'Failed to get example request: %s', $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Get response body template for a provider
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_response_template( WP_REST_Request $request ) {
        $provider_id = $request->get_param( 'provider_id' );

        try {
            $provider = $this->get_provider_instance( $provider_id );

            if ( method_exists( $provider, 'get_response_body_template' ) ) {
                $template = $provider->get_response_body_template();
            } else {
                // Fallback to extracting from example response
                $example_response = $provider->get_example_response();
                $template = array(
                    'template' => $example_response['template_vars'] ?? array(),
                    'description' => 'Automatically extracted from example response',
                );
            }

            return new WP_REST_Response( $template, 200 );

        } catch ( Exception $e ) {
            return new WP_Error(
                'provider_error',
                sprintf( 'Failed to get response template: %s', $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Get request body template for a provider
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_request_body_template( WP_REST_Request $request ) {
        $provider_id = $request->get_param( 'provider_id' );

        try {
            $provider = $this->get_provider_instance( $provider_id );

            if ( method_exists( $provider, 'get_request_body_template' ) ) {
                $template = $provider->get_request_body_template();
            } else {
                $template = array(
                    'template' => array(),
                    'template_vars' => array(),
                    'description' => 'This provider does not require a request body',
                );
            }

            return new WP_REST_Response( $template, 200 );

        } catch ( Exception $e ) {
            return new WP_Error(
                'provider_error',
                sprintf( 'Failed to get request body template: %s', $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Get rate limit information for a provider
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_rate_limits( WP_REST_Request $request ) {
        $provider_id = $request->get_param( 'provider_id' );

        try {
            $provider = $this->get_provider_instance( $provider_id );

            if ( method_exists( $provider, 'get_rate_limit_info' ) ) {
                $rate_limits = $provider->get_rate_limit_info();
            } else {
                $rate_limits = $provider->get_rate_limits();
            }

            return new WP_REST_Response( $rate_limits, 200 );

        } catch ( Exception $e ) {
            return new WP_Error(
                'provider_error',
                sprintf( 'Failed to get rate limits: %s', $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Extract template variables from custom data
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function extract_template_variables( WP_REST_Request $request ) {
        $data = $request->get_param( 'data' );

        if ( empty( $data ) ) {
            return new WP_Error(
                'invalid_data',
                'No data provided for analysis',
                array( 'status' => 400 )
            );
        }

        try {
            // Use UK Gov provider as a helper for extraction
            $provider = new Apprco_UK_Gov_Provider();
            $template_vars = $provider->extract_template_variables( $data );

            return new WP_REST_Response(
                array(
                    'template_vars' => $template_vars,
                    'count' => count( $template_vars ),
                ),
                200
            );

        } catch ( Exception $e ) {
            return new WP_Error(
                'extraction_error',
                sprintf( 'Failed to extract template variables: %s', $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Validate provider ID
     *
     * @param string $provider_id Provider ID to validate.
     * @return bool True if valid.
     */
    public function validate_provider_id( string $provider_id ): bool {
        // List of valid provider IDs
        $valid_providers = array(
            'uk-gov-apprenticeships',
            // Add more as needed
        );

        return in_array( $provider_id, $valid_providers, true );
    }

    /**
     * Get provider instance by ID
     *
     * @param string $provider_id Provider ID.
     * @return Apprco_Provider_Interface Provider instance.
     * @throws Exception If provider not found.
     */
    private function get_provider_instance( string $provider_id ): Apprco_Provider_Interface {
        switch ( $provider_id ) {
            case 'uk-gov-apprenticeships':
                return new Apprco_UK_Gov_Provider();

            default:
                throw new Exception( sprintf( 'Unknown provider: %s', $provider_id ) );
        }
    }
}
