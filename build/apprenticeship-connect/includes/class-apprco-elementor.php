<?php
/**
 * Elementor integration class for dynamic tags and loop builder compatibility
 *
 * @package ApprenticeshipConnect
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles Elementor integration
 */
class Apprco_Elementor {

    /**
     * Plugin instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Dynamic tags group name
     *
     * @var string
     */
    public const TAG_GROUP = 'apprco-vacancy';

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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Register dynamic tags
        add_action( 'elementor/dynamic_tags/register', array( $this, 'register_dynamic_tags' ) );

        // Register custom skin/template for loop grid
        add_action( 'elementor/theme/register_locations', array( $this, 'register_locations' ) );

        // Add vacancy fields to Elementor
        add_filter( 'elementor/theme/posts_archive/query_posts/query_vars', array( $this, 'modify_archive_query' ), 10, 2 );

        // Register custom controls
        add_action( 'elementor/controls/register', array( $this, 'register_controls' ) );
    }

    /**
     * Check if Elementor is active
     *
     * @return bool
     */
    public static function is_elementor_active(): bool {
        return defined( 'ELEMENTOR_VERSION' );
    }

    /**
     * Check if Elementor Pro is active
     *
     * @return bool
     */
    public static function is_elementor_pro_active(): bool {
        return defined( 'ELEMENTOR_PRO_VERSION' );
    }

    /**
     * Register dynamic tags with Elementor
     *
     * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager Dynamic tags manager.
     */
    public function register_dynamic_tags( $dynamic_tags_manager ): void {
        // Register the tag group
        $dynamic_tags_manager->register_group(
            self::TAG_GROUP,
            array(
                'title' => __( 'Apprenticeship Vacancy', 'apprenticeship-connect' ),
            )
        );

        // Include and register dynamic tag classes
        $tag_classes = array(
            'Apprco_Tag_Vacancy_Title',
            'Apprco_Tag_Vacancy_Description',
            'Apprco_Tag_Vacancy_Employer',
            'Apprco_Tag_Vacancy_Location',
            'Apprco_Tag_Vacancy_Postcode',
            'Apprco_Tag_Vacancy_Wage',
            'Apprco_Tag_Vacancy_Closing_Date',
            'Apprco_Tag_Vacancy_Start_Date',
            'Apprco_Tag_Vacancy_Posted_Date',
            'Apprco_Tag_Vacancy_Hours',
            'Apprco_Tag_Vacancy_Duration',
            'Apprco_Tag_Vacancy_Level',
            'Apprco_Tag_Vacancy_Provider',
            'Apprco_Tag_Vacancy_URL',
            'Apprco_Tag_Vacancy_Reference',
            'Apprco_Tag_Vacancy_Positions',
            'Apprco_Tag_Vacancy_Course',
            'Apprco_Tag_Vacancy_Route',
            'Apprco_Tag_Vacancy_Latitude',
            'Apprco_Tag_Vacancy_Longitude',
        );

        foreach ( $tag_classes as $class_name ) {
            if ( class_exists( $class_name ) ) {
                $dynamic_tags_manager->register( new $class_name() );
            }
        }
    }

    /**
     * Register theme locations
     *
     * @param \Elementor\Core\Theme_Templates\Theme_Templates $theme_manager Theme manager.
     */
    public function register_locations( $theme_manager ): void {
        $theme_manager->register_location(
            'apprco-vacancy-single',
            array(
                'label'           => __( 'Single Vacancy', 'apprenticeship-connect' ),
                'multiple'        => false,
                'edit_in_content' => true,
            )
        );

        $theme_manager->register_location(
            'apprco-vacancy-archive',
            array(
                'label'           => __( 'Vacancy Archive', 'apprenticeship-connect' ),
                'multiple'        => false,
                'edit_in_content' => true,
            )
        );
    }

    /**
     * Modify archive query for Elementor
     *
     * @param array $query_vars Query variables.
     * @param mixed $widget     Widget instance.
     * @return array
     */
    public function modify_archive_query( array $query_vars, $widget ): array {
        if ( isset( $query_vars['post_type'] ) && $query_vars['post_type'] === 'apprco_vacancy' ) {
            // Add default ordering by posted date
            if ( ! isset( $query_vars['orderby'] ) ) {
                $query_vars['orderby']  = 'meta_value';
                $query_vars['meta_key'] = '_apprco_posted_date';
                $query_vars['order']    = 'DESC';
            }

            // Filter out expired vacancies by default
            if ( ! isset( $query_vars['meta_query'] ) ) {
                $query_vars['meta_query'] = array();
            }

            $query_vars['meta_query'][] = array(
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

        return $query_vars;
    }

    /**
     * Register custom controls for Elementor widgets
     *
     * @param \Elementor\Controls_Manager $controls_manager Controls manager.
     */
    public function register_controls( $controls_manager ): void {
        // Custom controls can be added here if needed
    }

    /**
     * Get all vacancy meta fields for Elementor
     *
     * @return array
     */
    public static function get_vacancy_meta_fields(): array {
        return array(
            '_apprco_vacancy_reference'          => array(
                'label' => __( 'Vacancy Reference', 'apprenticeship-connect' ),
                'type'  => 'text',
            ),
            '_apprco_vacancy_description_short'  => array(
                'label' => __( 'Short Description', 'apprenticeship-connect' ),
                'type'  => 'textarea',
            ),
            '_apprco_number_of_positions'        => array(
                'label' => __( 'Number of Positions', 'apprenticeship-connect' ),
                'type'  => 'number',
            ),
            '_apprco_posted_date'                => array(
                'label' => __( 'Posted Date', 'apprenticeship-connect' ),
                'type'  => 'date',
            ),
            '_apprco_closing_date'               => array(
                'label' => __( 'Closing Date', 'apprenticeship-connect' ),
                'type'  => 'date',
            ),
            '_apprco_start_date'                 => array(
                'label' => __( 'Start Date', 'apprenticeship-connect' ),
                'type'  => 'date',
            ),
            '_apprco_hours_per_week'             => array(
                'label' => __( 'Hours Per Week', 'apprenticeship-connect' ),
                'type'  => 'number',
            ),
            '_apprco_expected_duration'          => array(
                'label' => __( 'Expected Duration', 'apprenticeship-connect' ),
                'type'  => 'text',
            ),
            '_apprco_employer_name'              => array(
                'label' => __( 'Employer Name', 'apprenticeship-connect' ),
                'type'  => 'text',
            ),
            '_apprco_vacancy_url'                => array(
                'label' => __( 'Vacancy URL', 'apprenticeship-connect' ),
                'type'  => 'url',
            ),
            '_apprco_apprenticeship_level'       => array(
                'label' => __( 'Apprenticeship Level', 'apprenticeship-connect' ),
                'type'  => 'text',
            ),
            '_apprco_provider_name'              => array(
                'label' => __( 'Training Provider', 'apprenticeship-connect' ),
                'type'  => 'text',
            ),
            '_apprco_wage_type'                  => array(
                'label' => __( 'Wage Type', 'apprenticeship-connect' ),
                'type'  => 'text',
            ),
            '_apprco_wage_amount'                => array(
                'label' => __( 'Wage Amount', 'apprenticeship-connect' ),
                'type'  => 'number',
            ),
            '_apprco_wage_unit'                  => array(
                'label' => __( 'Wage Unit', 'apprenticeship-connect' ),
                'type'  => 'text',
            ),
            '_apprco_wage_additional_information' => array(
                'label' => __( 'Wage Additional Info', 'apprenticeship-connect' ),
                'type'  => 'textarea',
            ),
            '_apprco_address_line_1'             => array(
                'label' => __( 'Address Line 1', 'apprenticeship-connect' ),
                'type'  => 'text',
            ),
            '_apprco_postcode'                   => array(
                'label' => __( 'Postcode', 'apprenticeship-connect' ),
                'type'  => 'text',
            ),
            '_apprco_latitude'                   => array(
                'label' => __( 'Latitude', 'apprenticeship-connect' ),
                'type'  => 'number',
            ),
            '_apprco_longitude'                  => array(
                'label' => __( 'Longitude', 'apprenticeship-connect' ),
                'type'  => 'number',
            ),
            '_apprco_course_title'               => array(
                'label' => __( 'Course Title', 'apprenticeship-connect' ),
                'type'  => 'text',
            ),
            '_apprco_course_level'               => array(
                'label' => __( 'Course Level', 'apprenticeship-connect' ),
                'type'  => 'number',
            ),
            '_apprco_course_route'               => array(
                'label' => __( 'Course Route', 'apprenticeship-connect' ),
                'type'  => 'text',
            ),
            '_apprco_distance'                   => array(
                'label' => __( 'Distance', 'apprenticeship-connect' ),
                'type'  => 'number',
            ),
            '_apprco_is_positive_about_disability' => array(
                'label' => __( 'Disability Confident', 'apprenticeship-connect' ),
                'type'  => 'boolean',
            ),
            '_apprco_is_employer_anonymous'      => array(
                'label' => __( 'Anonymous Employer', 'apprenticeship-connect' ),
                'type'  => 'boolean',
            ),
        );
    }

    /**
     * Get meta value formatted for display
     *
     * @param int    $post_id  Post ID.
     * @param string $meta_key Meta key.
     * @param string $format   Optional. Format for dates.
     * @return string
     */
    public static function get_formatted_meta( int $post_id, string $meta_key, string $format = '' ): string {
        $value  = get_post_meta( $post_id, $meta_key, true );
        $fields = self::get_vacancy_meta_fields();

        if ( empty( $value ) ) {
            return '';
        }

        $field_type = $fields[ $meta_key ]['type'] ?? 'text';

        switch ( $field_type ) {
            case 'date':
                $timestamp = strtotime( $value );
                if ( $timestamp ) {
                    $format = $format ?: get_option( 'date_format' );
                    return wp_date( $format, $timestamp );
                }
                return $value;

            case 'number':
                return is_numeric( $value ) ? number_format_i18n( (float) $value ) : $value;

            case 'boolean':
                return $value ? __( 'Yes', 'apprenticeship-connect' ) : __( 'No', 'apprenticeship-connect' );

            case 'url':
                return esc_url( $value );

            default:
                return esc_html( $value );
        }
    }
}

/**
 * Base class for Apprenticeship Connect dynamic tags
 */
if ( class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {

    /**
     * Abstract base tag for vacancy fields
     */
    abstract class Apprco_Tag_Base extends \Elementor\Core\DynamicTags\Tag {

        /**
         * Get tag group
         *
         * @return array
         */
        public function get_group(): array {
            return array( Apprco_Elementor::TAG_GROUP );
        }

        /**
         * Get categories
         *
         * @return array
         */
        public function get_categories(): array {
            return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
        }

        /**
         * Get meta key for this tag
         *
         * @return string
         */
        abstract protected function get_meta_key(): string;

        /**
         * Render the tag
         */
        public function render(): void {
            $post_id = get_the_ID();

            if ( ! $post_id || get_post_type( $post_id ) !== 'apprco_vacancy' ) {
                return;
            }

            echo wp_kses_post( Apprco_Elementor::get_formatted_meta( $post_id, $this->get_meta_key() ) );
        }
    }

    /**
     * Vacancy Title tag
     */
    class Apprco_Tag_Vacancy_Title extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-title';
        }

        public function get_title(): string {
            return __( 'Vacancy Title', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '';
        }

        public function render(): void {
            echo esc_html( get_the_title() );
        }
    }

    /**
     * Vacancy Description tag
     */
    class Apprco_Tag_Vacancy_Description extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-description';
        }

        public function get_title(): string {
            return __( 'Short Description', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_vacancy_description_short';
        }
    }

    /**
     * Employer Name tag
     */
    class Apprco_Tag_Vacancy_Employer extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-employer';
        }

        public function get_title(): string {
            return __( 'Employer Name', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_employer_name';
        }
    }

    /**
     * Location/Address tag
     */
    class Apprco_Tag_Vacancy_Location extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-location';
        }

        public function get_title(): string {
            return __( 'Location', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_address_line_1';
        }
    }

    /**
     * Postcode tag
     */
    class Apprco_Tag_Vacancy_Postcode extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-postcode';
        }

        public function get_title(): string {
            return __( 'Postcode', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_postcode';
        }
    }

    /**
     * Wage tag with formatted output
     */
    class Apprco_Tag_Vacancy_Wage extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-wage';
        }

        public function get_title(): string {
            return __( 'Wage', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_wage_amount';
        }

        public function render(): void {
            $post_id = get_the_ID();

            if ( ! $post_id || get_post_type( $post_id ) !== 'apprco_vacancy' ) {
                return;
            }

            $wage_type   = get_post_meta( $post_id, '_apprco_wage_type', true );
            $wage_amount = get_post_meta( $post_id, '_apprco_wage_amount', true );
            $wage_unit   = get_post_meta( $post_id, '_apprco_wage_unit', true );
            $wage_info   = get_post_meta( $post_id, '_apprco_wage_additional_information', true );

            $output = '';

            if ( $wage_amount ) {
                $output = '&pound;' . number_format_i18n( (float) $wage_amount, 2 );
                if ( $wage_unit ) {
                    $output .= ' ' . esc_html( $wage_unit );
                }
            } elseif ( $wage_type ) {
                $output = esc_html( $wage_type );
            } elseif ( $wage_info ) {
                $output = esc_html( $wage_info );
            }

            echo wp_kses_post( $output );
        }
    }

    /**
     * Closing Date tag
     */
    class Apprco_Tag_Vacancy_Closing_Date extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-closing-date';
        }

        public function get_title(): string {
            return __( 'Closing Date', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_closing_date';
        }
    }

    /**
     * Start Date tag
     */
    class Apprco_Tag_Vacancy_Start_Date extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-start-date';
        }

        public function get_title(): string {
            return __( 'Start Date', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_start_date';
        }
    }

    /**
     * Posted Date tag
     */
    class Apprco_Tag_Vacancy_Posted_Date extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-posted-date';
        }

        public function get_title(): string {
            return __( 'Posted Date', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_posted_date';
        }
    }

    /**
     * Hours Per Week tag
     */
    class Apprco_Tag_Vacancy_Hours extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-hours';
        }

        public function get_title(): string {
            return __( 'Hours Per Week', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_hours_per_week';
        }
    }

    /**
     * Duration tag
     */
    class Apprco_Tag_Vacancy_Duration extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-duration';
        }

        public function get_title(): string {
            return __( 'Expected Duration', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_expected_duration';
        }
    }

    /**
     * Apprenticeship Level tag
     */
    class Apprco_Tag_Vacancy_Level extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-level';
        }

        public function get_title(): string {
            return __( 'Apprenticeship Level', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_apprenticeship_level';
        }
    }

    /**
     * Training Provider tag
     */
    class Apprco_Tag_Vacancy_Provider extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-provider';
        }

        public function get_title(): string {
            return __( 'Training Provider', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_provider_name';
        }
    }

    /**
     * Vacancy URL tag
     */
    class Apprco_Tag_Vacancy_URL extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-url';
        }

        public function get_title(): string {
            return __( 'Vacancy URL', 'apprenticeship-connect' );
        }

        public function get_categories(): array {
            return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
        }

        protected function get_meta_key(): string {
            return '_apprco_vacancy_url';
        }

        public function render(): void {
            $post_id = get_the_ID();

            if ( ! $post_id || get_post_type( $post_id ) !== 'apprco_vacancy' ) {
                return;
            }

            echo esc_url( get_post_meta( $post_id, '_apprco_vacancy_url', true ) );
        }
    }

    /**
     * Vacancy Reference tag
     */
    class Apprco_Tag_Vacancy_Reference extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-reference';
        }

        public function get_title(): string {
            return __( 'Vacancy Reference', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_vacancy_reference';
        }
    }

    /**
     * Number of Positions tag
     */
    class Apprco_Tag_Vacancy_Positions extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-positions';
        }

        public function get_title(): string {
            return __( 'Number of Positions', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_number_of_positions';
        }
    }

    /**
     * Course Title tag
     */
    class Apprco_Tag_Vacancy_Course extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-course';
        }

        public function get_title(): string {
            return __( 'Course Title', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_course_title';
        }
    }

    /**
     * Course Route tag
     */
    class Apprco_Tag_Vacancy_Route extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-route';
        }

        public function get_title(): string {
            return __( 'Course Route', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_course_route';
        }
    }

    /**
     * Latitude tag
     */
    class Apprco_Tag_Vacancy_Latitude extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-latitude';
        }

        public function get_title(): string {
            return __( 'Latitude', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_latitude';
        }
    }

    /**
     * Longitude tag
     */
    class Apprco_Tag_Vacancy_Longitude extends Apprco_Tag_Base {
        public function get_name(): string {
            return 'apprco-vacancy-longitude';
        }

        public function get_title(): string {
            return __( 'Longitude', 'apprenticeship-connect' );
        }

        protected function get_meta_key(): string {
            return '_apprco_longitude';
        }
    }
}
