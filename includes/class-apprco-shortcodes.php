<?php
/**
 * Shortcodes class for templating tags and vacancy display
 *
 * @package ApprenticeshipConnect
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles all shortcodes and templating tags
 */
class Apprco_Shortcodes {

    /**
     * Plugin instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Current vacancy ID for template tags
     *
     * @var int|null
     */
    private static $current_vacancy_id = null;

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
        $this->register_shortcodes();
    }

    /**
     * Register all shortcodes
     */
    private function register_shortcodes(): void {
        // Single vacancy display
        add_shortcode( 'apprco_vacancy', array( $this, 'shortcode_single_vacancy' ) );

        // Vacancy list with filters
        add_shortcode( 'apprco_vacancy_list', array( $this, 'shortcode_vacancy_list' ) );

        // Search/filter form
        add_shortcode( 'apprco_vacancy_search', array( $this, 'shortcode_vacancy_search' ) );

        // Individual field tags
        add_shortcode( 'apprco_field', array( $this, 'shortcode_field' ) );

        // Templating tags (for use within loops)
        $this->register_template_tags();

        // Edit form shortcode
        add_shortcode( 'apprco_edit_form', array( $this, 'shortcode_edit_form' ) );

        // Application form shortcode
        add_shortcode( 'apprco_apply_button', array( $this, 'shortcode_apply_button' ) );

        // Stats/counters
        add_shortcode( 'apprco_stats', array( $this, 'shortcode_stats' ) );
    }

    /**
     * Register individual template tag shortcodes
     */
    private function register_template_tags(): void {
        $tags = array(
            'apprco_title'           => '_title',
            'apprco_description'     => '_apprco_vacancy_description_short',
            'apprco_full_description' => '_apprco_full_description',
            'apprco_employer'        => '_apprco_employer_name',
            'apprco_employer_desc'   => '_apprco_employer_description',
            'apprco_location'        => '_apprco_address_line_1',
            'apprco_town'            => '_apprco_town',
            'apprco_county'          => '_apprco_county',
            'apprco_postcode'        => '_apprco_postcode',
            'apprco_wage'            => '_apprco_wage_amount',
            'apprco_wage_type'       => '_apprco_wage_type',
            'apprco_hours'           => '_apprco_hours_per_week',
            'apprco_duration'        => '_apprco_expected_duration',
            'apprco_level'           => '_apprco_apprenticeship_level',
            'apprco_provider'        => '_apprco_provider_name',
            'apprco_course'          => '_apprco_course_title',
            'apprco_route'           => '_apprco_course_route',
            'apprco_posted_date'     => '_apprco_posted_date',
            'apprco_closing_date'    => '_apprco_closing_date',
            'apprco_start_date'      => '_apprco_start_date',
            'apprco_positions'       => '_apprco_number_of_positions',
            'apprco_reference'       => '_apprco_vacancy_reference',
            'apprco_url'             => '_apprco_vacancy_url',
            'apprco_lat'             => '_apprco_latitude',
            'apprco_lng'             => '_apprco_longitude',
            'apprco_training'        => '_apprco_training_to_be_provided',
            'apprco_qualifications'  => '_apprco_qualifications_required',
            'apprco_skills'          => '_apprco_skills_required',
            'apprco_prospects'       => '_apprco_future_prospects',
        );

        foreach ( $tags as $shortcode => $meta_key ) {
            add_shortcode( $shortcode, function( $atts ) use ( $meta_key ) {
                return $this->render_template_tag( $meta_key, $atts );
            } );
        }
    }

    /**
     * Render a template tag
     *
     * @param string $meta_key Meta key or special key.
     * @param array  $atts     Shortcode attributes.
     * @return string
     */
    private function render_template_tag( string $meta_key, $atts ): string {
        $atts = shortcode_atts( array(
            'id'      => null,
            'format'  => '',
            'before'  => '',
            'after'   => '',
            'default' => '',
        ), $atts );

        $post_id = $this->get_vacancy_id( $atts['id'] );

        if ( ! $post_id ) {
            return esc_html( $atts['default'] );
        }

        // Special handling for title
        if ( $meta_key === '_title' ) {
            $value = get_the_title( $post_id );
        } else {
            $value = get_post_meta( $post_id, $meta_key, true );
        }

        if ( empty( $value ) ) {
            return esc_html( $atts['default'] );
        }

        // Format the value based on type
        $value = $this->format_value( $value, $meta_key, $atts['format'] );

        return $atts['before'] . $value . $atts['after'];
    }

    /**
     * Format value based on meta key type
     *
     * @param mixed  $value    Value to format.
     * @param string $meta_key Meta key.
     * @param string $format   Custom format.
     * @return string
     */
    private function format_value( $value, string $meta_key, string $format = '' ): string {
        // Date fields
        if ( in_array( $meta_key, array( '_apprco_posted_date', '_apprco_closing_date', '_apprco_start_date' ), true ) ) {
            $timestamp = strtotime( $value );
            if ( $timestamp ) {
                $date_format = $format ?: get_option( 'date_format' );
                return esc_html( wp_date( $date_format, $timestamp ) );
            }
        }

        // Wage amount
        if ( $meta_key === '_apprco_wage_amount' && is_numeric( $value ) ) {
            return '&pound;' . number_format_i18n( (float) $value, 2 );
        }

        // Hours
        if ( $meta_key === '_apprco_hours_per_week' && is_numeric( $value ) ) {
            return number_format_i18n( (float) $value, 1 ) . ' ' . __( 'hours/week', 'apprenticeship-connect' );
        }

        // URL field
        if ( $meta_key === '_apprco_vacancy_url' ) {
            return esc_url( $value );
        }

        // HTML content fields
        if ( in_array( $meta_key, array( '_apprco_full_description', '_apprco_training_to_be_provided', '_apprco_qualifications_required', '_apprco_skills_required', '_apprco_future_prospects' ), true ) ) {
            return wp_kses_post( $value );
        }

        return esc_html( $value );
    }

    /**
     * Get vacancy ID from context or attribute
     *
     * @param mixed $attr_id ID from attribute.
     * @return int|null
     */
    private function get_vacancy_id( $attr_id ): ?int {
        // From shortcode attribute
        if ( $attr_id ) {
            return absint( $attr_id );
        }

        // From static context (within a loop)
        if ( self::$current_vacancy_id ) {
            return self::$current_vacancy_id;
        }

        // From current post
        $post_id = get_the_ID();
        if ( $post_id && get_post_type( $post_id ) === 'apprco_vacancy' ) {
            return $post_id;
        }

        return null;
    }

    /**
     * Set current vacancy ID for template tags
     *
     * @param int $post_id Post ID.
     */
    public static function set_current_vacancy( int $post_id ): void {
        self::$current_vacancy_id = $post_id;
    }

    /**
     * Clear current vacancy ID
     */
    public static function clear_current_vacancy(): void {
        self::$current_vacancy_id = null;
    }

    /**
     * Single vacancy shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_single_vacancy( $atts ): string {
        $atts = shortcode_atts( array(
            'id'       => 0,
            'template' => 'default',
            'class'    => '',
        ), $atts );

        $post_id = absint( $atts['id'] );

        if ( ! $post_id ) {
            $post_id = get_the_ID();
        }

        $post = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'apprco_vacancy' ) {
            return '<p class="apprco-error">' . esc_html__( 'Vacancy not found.', 'apprenticeship-connect' ) . '</p>';
        }

        self::set_current_vacancy( $post_id );

        ob_start();

        $template = $atts['template'];
        $class    = 'apprco-single-vacancy apprco-template-' . sanitize_html_class( $template );
        if ( $atts['class'] ) {
            $class .= ' ' . sanitize_html_class( $atts['class'] );
        }

        echo '<div class="' . esc_attr( $class ) . '" data-vacancy-id="' . esc_attr( $post_id ) . '">';

        if ( $template === 'card' ) {
            $this->render_vacancy_card( $post );
        } elseif ( $template === 'full' ) {
            $this->render_vacancy_full( $post );
        } else {
            $this->render_vacancy_default( $post );
        }

        echo '</div>';

        self::clear_current_vacancy();

        return ob_get_clean();
    }

    /**
     * Render default vacancy template
     *
     * @param WP_Post $post Post object.
     */
    private function render_vacancy_default( WP_Post $post ): void {
        $employer     = get_post_meta( $post->ID, '_apprco_employer_name', true );
        $description  = get_post_meta( $post->ID, '_apprco_vacancy_description_short', true );
        $postcode     = get_post_meta( $post->ID, '_apprco_postcode', true );
        $level        = get_post_meta( $post->ID, '_apprco_apprenticeship_level', true );
        $closing_date = get_post_meta( $post->ID, '_apprco_closing_date', true );
        $vacancy_url  = get_post_meta( $post->ID, '_apprco_vacancy_url', true );
        ?>
        <h2 class="apprco-vacancy-title"><?php echo esc_html( $post->post_title ); ?></h2>

        <?php if ( $level ) : ?>
            <span class="apprco-level-badge"><?php echo esc_html( $level ); ?></span>
        <?php endif; ?>

        <?php if ( $employer ) : ?>
            <p class="apprco-employer">
                <strong><?php esc_html_e( 'Employer:', 'apprenticeship-connect' ); ?></strong>
                <?php echo esc_html( $employer ); ?>
                <?php if ( $postcode ) : ?>
                    <span class="apprco-postcode"> - <?php echo esc_html( $postcode ); ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ( $description ) : ?>
            <div class="apprco-description"><?php echo wp_kses_post( $description ); ?></div>
        <?php endif; ?>

        <?php if ( $closing_date ) : ?>
            <p class="apprco-closing-date">
                <strong><?php esc_html_e( 'Closing Date:', 'apprenticeship-connect' ); ?></strong>
                <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $closing_date ) ) ); ?>
            </p>
        <?php endif; ?>

        <?php if ( $vacancy_url ) : ?>
            <p class="apprco-apply">
                <a href="<?php echo esc_url( $vacancy_url ); ?>" target="_blank" rel="noopener" class="apprco-apply-button">
                    <?php esc_html_e( 'Apply Now', 'apprenticeship-connect' ); ?> &raquo;
                </a>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render card vacancy template
     *
     * @param WP_Post $post Post object.
     */
    private function render_vacancy_card( WP_Post $post ): void {
        $employer     = get_post_meta( $post->ID, '_apprco_employer_name', true );
        $level        = get_post_meta( $post->ID, '_apprco_apprenticeship_level', true );
        $postcode     = get_post_meta( $post->ID, '_apprco_postcode', true );
        $wage         = get_post_meta( $post->ID, '_apprco_wage_amount', true );
        $wage_type    = get_post_meta( $post->ID, '_apprco_wage_type', true );
        $closing_date = get_post_meta( $post->ID, '_apprco_closing_date', true );
        $vacancy_url  = get_post_meta( $post->ID, '_apprco_vacancy_url', true );
        $permalink    = get_permalink( $post->ID );
        ?>
        <div class="apprco-card-header">
            <?php if ( $level ) : ?>
                <span class="apprco-level-badge"><?php echo esc_html( $level ); ?></span>
            <?php endif; ?>
            <h3 class="apprco-card-title">
                <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $post->post_title ); ?></a>
            </h3>
        </div>

        <div class="apprco-card-body">
            <?php if ( $employer ) : ?>
                <p class="apprco-employer"><i class="dashicons dashicons-building"></i> <?php echo esc_html( $employer ); ?></p>
            <?php endif; ?>

            <?php if ( $postcode ) : ?>
                <p class="apprco-location"><i class="dashicons dashicons-location"></i> <?php echo esc_html( $postcode ); ?></p>
            <?php endif; ?>

            <?php if ( $wage ) : ?>
                <p class="apprco-wage"><i class="dashicons dashicons-money-alt"></i> &pound;<?php echo esc_html( number_format_i18n( (float) $wage, 2 ) ); ?></p>
            <?php elseif ( $wage_type ) : ?>
                <p class="apprco-wage"><i class="dashicons dashicons-money-alt"></i> <?php echo esc_html( $wage_type ); ?></p>
            <?php endif; ?>
        </div>

        <div class="apprco-card-footer">
            <?php if ( $closing_date ) : ?>
                <span class="apprco-closing">
                    <?php esc_html_e( 'Closes:', 'apprenticeship-connect' ); ?>
                    <?php echo esc_html( wp_date( 'j M Y', strtotime( $closing_date ) ) ); ?>
                </span>
            <?php endif; ?>

            <a href="<?php echo esc_url( $vacancy_url ?: $permalink ); ?>"
               <?php echo $vacancy_url ? 'target="_blank" rel="noopener"' : ''; ?>
               class="apprco-card-link">
                <?php esc_html_e( 'View Details', 'apprenticeship-connect' ); ?> &raquo;
            </a>
        </div>
        <?php
    }

    /**
     * Render full vacancy template
     *
     * @param WP_Post $post Post object.
     */
    private function render_vacancy_full( WP_Post $post ): void {
        // Get all meta
        $meta = array(
            'employer'        => get_post_meta( $post->ID, '_apprco_employer_name', true ),
            'employer_desc'   => get_post_meta( $post->ID, '_apprco_employer_description', true ),
            'employer_web'    => get_post_meta( $post->ID, '_apprco_employer_website_url', true ),
            'description'     => get_post_meta( $post->ID, '_apprco_vacancy_description_short', true ),
            'full_desc'       => get_post_meta( $post->ID, '_apprco_full_description', true ),
            'level'           => get_post_meta( $post->ID, '_apprco_apprenticeship_level', true ),
            'positions'       => get_post_meta( $post->ID, '_apprco_number_of_positions', true ),
            'address'         => get_post_meta( $post->ID, '_apprco_address_line_1', true ),
            'town'            => get_post_meta( $post->ID, '_apprco_town', true ),
            'postcode'        => get_post_meta( $post->ID, '_apprco_postcode', true ),
            'wage'            => get_post_meta( $post->ID, '_apprco_wage_amount', true ),
            'wage_type'       => get_post_meta( $post->ID, '_apprco_wage_type', true ),
            'wage_unit'       => get_post_meta( $post->ID, '_apprco_wage_unit', true ),
            'hours'           => get_post_meta( $post->ID, '_apprco_hours_per_week', true ),
            'duration'        => get_post_meta( $post->ID, '_apprco_expected_duration', true ),
            'posted_date'     => get_post_meta( $post->ID, '_apprco_posted_date', true ),
            'closing_date'    => get_post_meta( $post->ID, '_apprco_closing_date', true ),
            'start_date'      => get_post_meta( $post->ID, '_apprco_start_date', true ),
            'provider'        => get_post_meta( $post->ID, '_apprco_provider_name', true ),
            'course'          => get_post_meta( $post->ID, '_apprco_course_title', true ),
            'route'           => get_post_meta( $post->ID, '_apprco_course_route', true ),
            'training'        => get_post_meta( $post->ID, '_apprco_training_to_be_provided', true ),
            'qualifications'  => get_post_meta( $post->ID, '_apprco_qualifications_required', true ),
            'skills'          => get_post_meta( $post->ID, '_apprco_skills_required', true ),
            'prospects'       => get_post_meta( $post->ID, '_apprco_future_prospects', true ),
            'vacancy_url'     => get_post_meta( $post->ID, '_apprco_vacancy_url', true ),
            'reference'       => get_post_meta( $post->ID, '_apprco_vacancy_reference', true ),
        );
        ?>
        <article class="apprco-full-vacancy">
            <header class="apprco-header">
                <h1 class="apprco-title"><?php echo esc_html( $post->post_title ); ?></h1>

                <div class="apprco-meta-bar">
                    <?php if ( $meta['level'] ) : ?>
                        <span class="apprco-level-badge"><?php echo esc_html( $meta['level'] ); ?></span>
                    <?php endif; ?>

                    <?php if ( $meta['reference'] ) : ?>
                        <span class="apprco-reference"><?php esc_html_e( 'Ref:', 'apprenticeship-connect' ); ?> <?php echo esc_html( $meta['reference'] ); ?></span>
                    <?php endif; ?>

                    <?php if ( $meta['positions'] && $meta['positions'] > 1 ) : ?>
                        <span class="apprco-positions"><?php echo esc_html( $meta['positions'] ); ?> <?php esc_html_e( 'positions', 'apprenticeship-connect' ); ?></span>
                    <?php endif; ?>
                </div>
            </header>

            <div class="apprco-info-grid">
                <!-- Key Details -->
                <div class="apprco-info-section apprco-key-details">
                    <h3><?php esc_html_e( 'Key Details', 'apprenticeship-connect' ); ?></h3>
                    <dl>
                        <?php if ( $meta['employer'] ) : ?>
                            <dt><?php esc_html_e( 'Employer', 'apprenticeship-connect' ); ?></dt>
                            <dd><?php echo esc_html( $meta['employer'] ); ?></dd>
                        <?php endif; ?>

                        <?php if ( $meta['town'] || $meta['postcode'] ) : ?>
                            <dt><?php esc_html_e( 'Location', 'apprenticeship-connect' ); ?></dt>
                            <dd>
                                <?php echo esc_html( implode( ', ', array_filter( array( $meta['address'], $meta['town'], $meta['postcode'] ) ) ) ); ?>
                            </dd>
                        <?php endif; ?>

                        <?php if ( $meta['wage'] || $meta['wage_type'] ) : ?>
                            <dt><?php esc_html_e( 'Salary', 'apprenticeship-connect' ); ?></dt>
                            <dd>
                                <?php if ( $meta['wage'] ) : ?>
                                    &pound;<?php echo esc_html( number_format_i18n( (float) $meta['wage'], 2 ) ); ?>
                                    <?php if ( $meta['wage_unit'] ) : ?>
                                        <?php echo esc_html( strtolower( $meta['wage_unit'] ) ); ?>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <?php echo esc_html( $meta['wage_type'] ); ?>
                                <?php endif; ?>
                            </dd>
                        <?php endif; ?>

                        <?php if ( $meta['hours'] ) : ?>
                            <dt><?php esc_html_e( 'Hours', 'apprenticeship-connect' ); ?></dt>
                            <dd><?php echo esc_html( $meta['hours'] ); ?> <?php esc_html_e( 'hours per week', 'apprenticeship-connect' ); ?></dd>
                        <?php endif; ?>

                        <?php if ( $meta['duration'] ) : ?>
                            <dt><?php esc_html_e( 'Duration', 'apprenticeship-connect' ); ?></dt>
                            <dd><?php echo esc_html( $meta['duration'] ); ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>

                <!-- Important Dates -->
                <div class="apprco-info-section apprco-dates">
                    <h3><?php esc_html_e( 'Important Dates', 'apprenticeship-connect' ); ?></h3>
                    <dl>
                        <?php if ( $meta['closing_date'] ) : ?>
                            <dt><?php esc_html_e( 'Closing Date', 'apprenticeship-connect' ); ?></dt>
                            <dd class="apprco-date-highlight"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $meta['closing_date'] ) ) ); ?></dd>
                        <?php endif; ?>

                        <?php if ( $meta['start_date'] ) : ?>
                            <dt><?php esc_html_e( 'Start Date', 'apprenticeship-connect' ); ?></dt>
                            <dd><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $meta['start_date'] ) ) ); ?></dd>
                        <?php endif; ?>

                        <?php if ( $meta['posted_date'] ) : ?>
                            <dt><?php esc_html_e( 'Posted', 'apprenticeship-connect' ); ?></dt>
                            <dd><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $meta['posted_date'] ) ) ); ?></dd>
                        <?php endif; ?>
                    </dl>

                    <?php if ( $meta['vacancy_url'] ) : ?>
                        <a href="<?php echo esc_url( $meta['vacancy_url'] ); ?>" target="_blank" rel="noopener" class="apprco-apply-button apprco-button-large">
                            <?php esc_html_e( 'Apply Now', 'apprenticeship-connect' ); ?> &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <?php if ( $meta['full_desc'] || $meta['description'] ) : ?>
                <section class="apprco-content-section">
                    <h3><?php esc_html_e( 'About This Apprenticeship', 'apprenticeship-connect' ); ?></h3>
                    <?php echo wp_kses_post( $meta['full_desc'] ?: $meta['description'] ); ?>
                </section>
            <?php endif; ?>

            <!-- Employer -->
            <?php if ( $meta['employer_desc'] ) : ?>
                <section class="apprco-content-section">
                    <h3><?php esc_html_e( 'About the Employer', 'apprenticeship-connect' ); ?></h3>
                    <?php echo wp_kses_post( $meta['employer_desc'] ); ?>
                    <?php if ( $meta['employer_web'] ) : ?>
                        <p><a href="<?php echo esc_url( $meta['employer_web'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Visit employer website', 'apprenticeship-connect' ); ?></a></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- Training -->
            <section class="apprco-content-section apprco-training">
                <h3><?php esc_html_e( 'Training & Qualifications', 'apprenticeship-connect' ); ?></h3>

                <?php if ( $meta['provider'] || $meta['course'] ) : ?>
                    <dl>
                        <?php if ( $meta['provider'] ) : ?>
                            <dt><?php esc_html_e( 'Training Provider', 'apprenticeship-connect' ); ?></dt>
                            <dd><?php echo esc_html( $meta['provider'] ); ?></dd>
                        <?php endif; ?>

                        <?php if ( $meta['course'] ) : ?>
                            <dt><?php esc_html_e( 'Course', 'apprenticeship-connect' ); ?></dt>
                            <dd><?php echo esc_html( $meta['course'] ); ?></dd>
                        <?php endif; ?>

                        <?php if ( $meta['route'] ) : ?>
                            <dt><?php esc_html_e( 'Route', 'apprenticeship-connect' ); ?></dt>
                            <dd><?php echo esc_html( $meta['route'] ); ?></dd>
                        <?php endif; ?>
                    </dl>
                <?php endif; ?>

                <?php if ( $meta['training'] ) : ?>
                    <h4><?php esc_html_e( 'Training Provided', 'apprenticeship-connect' ); ?></h4>
                    <?php echo wp_kses_post( $meta['training'] ); ?>
                <?php endif; ?>
            </section>

            <!-- Requirements -->
            <?php if ( $meta['qualifications'] || $meta['skills'] ) : ?>
                <section class="apprco-content-section apprco-requirements">
                    <h3><?php esc_html_e( 'What We\'re Looking For', 'apprenticeship-connect' ); ?></h3>

                    <?php if ( $meta['qualifications'] ) : ?>
                        <h4><?php esc_html_e( 'Qualifications', 'apprenticeship-connect' ); ?></h4>
                        <?php echo wp_kses_post( $meta['qualifications'] ); ?>
                    <?php endif; ?>

                    <?php if ( $meta['skills'] ) : ?>
                        <h4><?php esc_html_e( 'Skills', 'apprenticeship-connect' ); ?></h4>
                        <?php echo wp_kses_post( $meta['skills'] ); ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- Prospects -->
            <?php if ( $meta['prospects'] ) : ?>
                <section class="apprco-content-section">
                    <h3><?php esc_html_e( 'Future Prospects', 'apprenticeship-connect' ); ?></h3>
                    <?php echo wp_kses_post( $meta['prospects'] ); ?>
                </section>
            <?php endif; ?>

            <!-- Apply CTA -->
            <?php if ( $meta['vacancy_url'] ) : ?>
                <footer class="apprco-apply-footer">
                    <a href="<?php echo esc_url( $meta['vacancy_url'] ); ?>" target="_blank" rel="noopener" class="apprco-apply-button apprco-button-large">
                        <?php esc_html_e( 'Apply for this Apprenticeship', 'apprenticeship-connect' ); ?> &raquo;
                    </a>
                </footer>
            <?php endif; ?>
        </article>
        <?php
    }

    /**
     * Vacancy list shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_vacancy_list( $atts ): string {
        $atts = shortcode_atts( array(
            'count'        => 10,
            'level'        => '',
            'route'        => '',
            'employer'     => '',
            'orderby'      => 'posted_date',
            'order'        => 'DESC',
            'template'     => 'card',
            'columns'      => 3,
            'active_only'  => 'true',
            'class'        => '',
        ), $atts );

        $args = array(
            'post_type'      => 'apprco_vacancy',
            'post_status'    => 'publish',
            'posts_per_page' => absint( $atts['count'] ),
            'order'          => $atts['order'],
        );

        // Ordering
        switch ( $atts['orderby'] ) {
            case 'closing_date':
                $args['meta_key'] = '_apprco_closing_date';
                $args['orderby']  = 'meta_value';
                break;
            case 'title':
                $args['orderby'] = 'title';
                break;
            default:
                $args['meta_key'] = '_apprco_posted_date';
                $args['orderby']  = 'meta_value';
        }

        // Filter active only
        if ( $atts['active_only'] === 'true' ) {
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
        }

        // Taxonomy filters
        $tax_query = array();

        if ( $atts['level'] ) {
            $tax_query[] = array(
                'taxonomy' => 'apprco_level',
                'field'    => 'slug',
                'terms'    => explode( ',', $atts['level'] ),
            );
        }

        if ( $atts['route'] ) {
            $tax_query[] = array(
                'taxonomy' => 'apprco_route',
                'field'    => 'slug',
                'terms'    => explode( ',', $atts['route'] ),
            );
        }

        if ( $atts['employer'] ) {
            $tax_query[] = array(
                'taxonomy' => 'apprco_employer',
                'field'    => 'slug',
                'terms'    => explode( ',', $atts['employer'] ),
            );
        }

        if ( ! empty( $tax_query ) ) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query( $args );

        ob_start();

        $class = 'apprco-vacancy-list apprco-columns-' . absint( $atts['columns'] );
        if ( $atts['class'] ) {
            $class .= ' ' . sanitize_html_class( $atts['class'] );
        }

        echo '<div class="' . esc_attr( $class ) . '">';

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                echo do_shortcode( '[apprco_vacancy id="' . get_the_ID() . '" template="' . esc_attr( $atts['template'] ) . '"]' );
            }
            wp_reset_postdata();
        } else {
            echo '<p class="apprco-no-results">' . esc_html__( 'No vacancies found.', 'apprenticeship-connect' ) . '</p>';
        }

        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Search form shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_vacancy_search( $atts ): string {
        $atts = shortcode_atts( array(
            'show_keyword' => 'true',
            'show_level'   => 'true',
            'show_route'   => 'true',
            'show_location' => 'true',
            'ajax'         => 'true',
            'results_id'   => '',
            'class'        => '',
        ), $atts );

        wp_enqueue_script( 'apprco-frontend' );

        $levels = get_terms( array(
            'taxonomy'   => 'apprco_level',
            'hide_empty' => true,
        ) );

        $routes = get_terms( array(
            'taxonomy'   => 'apprco_route',
            'hide_empty' => true,
        ) );

        ob_start();

        $class = 'apprco-search-form';
        if ( $atts['class'] ) {
            $class .= ' ' . sanitize_html_class( $atts['class'] );
        }
        ?>
        <form class="<?php echo esc_attr( $class ); ?>"
              method="get"
              action="<?php echo esc_url( get_post_type_archive_link( 'apprco_vacancy' ) ); ?>"
              data-ajax="<?php echo esc_attr( $atts['ajax'] ); ?>"
              data-results="<?php echo esc_attr( $atts['results_id'] ); ?>">

            <?php wp_nonce_field( 'apprco_search', 'apprco_search_nonce' ); ?>

            <?php if ( $atts['show_keyword'] === 'true' ) : ?>
                <div class="apprco-field apprco-field-keyword">
                    <label for="apprco-search-keyword"><?php esc_html_e( 'Keyword', 'apprenticeship-connect' ); ?></label>
                    <input type="text" id="apprco-search-keyword" name="s" placeholder="<?php esc_attr_e( 'Job title, skills, or employer...', 'apprenticeship-connect' ); ?>" />
                </div>
            <?php endif; ?>

            <?php if ( $atts['show_location'] === 'true' ) : ?>
                <div class="apprco-field apprco-field-location">
                    <label for="apprco-search-postcode"><?php esc_html_e( 'Location', 'apprenticeship-connect' ); ?></label>
                    <input type="text" id="apprco-search-postcode" name="postcode" placeholder="<?php esc_attr_e( 'Postcode', 'apprenticeship-connect' ); ?>" />
                </div>
            <?php endif; ?>

            <?php if ( $atts['show_level'] === 'true' && ! empty( $levels ) && ! is_wp_error( $levels ) ) : ?>
                <div class="apprco-field apprco-field-level">
                    <label for="apprco-search-level"><?php esc_html_e( 'Level', 'apprenticeship-connect' ); ?></label>
                    <select id="apprco-search-level" name="level">
                        <option value=""><?php esc_html_e( 'All Levels', 'apprenticeship-connect' ); ?></option>
                        <?php foreach ( $levels as $level ) : ?>
                            <option value="<?php echo esc_attr( $level->slug ); ?>"><?php echo esc_html( $level->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ( $atts['show_route'] === 'true' && ! empty( $routes ) && ! is_wp_error( $routes ) ) : ?>
                <div class="apprco-field apprco-field-route">
                    <label for="apprco-search-route"><?php esc_html_e( 'Route', 'apprenticeship-connect' ); ?></label>
                    <select id="apprco-search-route" name="route">
                        <option value=""><?php esc_html_e( 'All Routes', 'apprenticeship-connect' ); ?></option>
                        <?php foreach ( $routes as $route ) : ?>
                            <option value="<?php echo esc_attr( $route->slug ); ?>"><?php echo esc_html( $route->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="apprco-field apprco-field-submit">
                <button type="submit" class="apprco-search-submit">
                    <?php esc_html_e( 'Search', 'apprenticeship-connect' ); ?>
                </button>
            </div>
        </form>
        <?php

        return ob_get_clean();
    }

    /**
     * Individual field shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_field( $atts ): string {
        $atts = shortcode_atts( array(
            'name'    => '',
            'id'      => null,
            'format'  => '',
            'before'  => '',
            'after'   => '',
            'default' => '',
        ), $atts );

        if ( ! $atts['name'] ) {
            return '';
        }

        $meta_key = '_apprco_' . sanitize_key( $atts['name'] );
        return $this->render_template_tag( $meta_key, $atts );
    }

    /**
     * Edit form shortcode (for frontend editing)
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_edit_form( $atts ): string {
        $atts = shortcode_atts( array(
            'id'       => 0,
            'fields'   => 'basic',
            'redirect' => '',
            'class'    => '',
        ), $atts );

        // Check permissions
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            return '<p class="apprco-error">' . esc_html__( 'You do not have permission to edit vacancies.', 'apprenticeship-connect' ) . '</p>';
        }

        $post_id = absint( $atts['id'] );
        $is_new  = empty( $post_id );

        if ( ! $is_new ) {
            $post = get_post( $post_id );
            if ( ! $post || $post->post_type !== 'apprco_vacancy' ) {
                return '<p class="apprco-error">' . esc_html__( 'Vacancy not found.', 'apprenticeship-connect' ) . '</p>';
            }
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return '<p class="apprco-error">' . esc_html__( 'You do not have permission to edit this vacancy.', 'apprenticeship-connect' ) . '</p>';
            }
        }

        wp_enqueue_script( 'apprco-frontend' );

        // Define field groups
        $field_groups = array(
            'basic' => array( 'title', 'employer_name', 'vacancy_description_short', 'postcode', 'closing_date' ),
            'full'  => array_keys( Apprco_Elementor::get_vacancy_meta_fields() ),
        );

        $fields_to_show = $field_groups[ $atts['fields'] ] ?? $field_groups['basic'];

        ob_start();

        $class = 'apprco-edit-form';
        if ( $atts['class'] ) {
            $class .= ' ' . sanitize_html_class( $atts['class'] );
        }
        ?>
        <form class="<?php echo esc_attr( $class ); ?>"
              method="post"
              data-post-id="<?php echo esc_attr( $post_id ); ?>"
              data-redirect="<?php echo esc_url( $atts['redirect'] ); ?>">

            <?php wp_nonce_field( 'apprco_frontend_edit', 'apprco_edit_nonce' ); ?>
            <input type="hidden" name="vacancy_id" value="<?php echo esc_attr( $post_id ); ?>" />

            <div class="apprco-form-fields">
                <!-- Title (always shown) -->
                <div class="apprco-field apprco-field-text">
                    <label for="apprco-edit-title"><?php esc_html_e( 'Title', 'apprenticeship-connect' ); ?> <span class="required">*</span></label>
                    <input type="text" id="apprco-edit-title" name="post_title" required
                           value="<?php echo $is_new ? '' : esc_attr( $post->post_title ); ?>" />
                </div>

                <?php foreach ( $fields_to_show as $field_key ) :
                    $meta_key = strpos( $field_key, '_apprco_' ) === 0 ? $field_key : '_apprco_' . $field_key;
                    $clean_key = str_replace( '_apprco_', '', $meta_key );

                    $meta_fields = Apprco_Elementor::get_vacancy_meta_fields();
                    if ( ! isset( $meta_fields[ $meta_key ] ) ) {
                        continue;
                    }

                    $field = $meta_fields[ $meta_key ];
                    $value = $is_new ? '' : get_post_meta( $post_id, $meta_key, true );
                    $field_id = 'apprco-edit-' . sanitize_key( $clean_key );
                    ?>
                    <div class="apprco-field apprco-field-<?php echo esc_attr( $field['type'] ); ?>">
                        <label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>

                        <?php if ( $field['type'] === 'textarea' ) : ?>
                            <textarea id="<?php echo esc_attr( $field_id ); ?>"
                                      name="meta[<?php echo esc_attr( $clean_key ); ?>]"><?php echo esc_textarea( $value ); ?></textarea>

                        <?php elseif ( $field['type'] === 'date' ) : ?>
                            <input type="date" id="<?php echo esc_attr( $field_id ); ?>"
                                   name="meta[<?php echo esc_attr( $clean_key ); ?>]"
                                   value="<?php echo esc_attr( $value ); ?>" />

                        <?php elseif ( $field['type'] === 'number' ) : ?>
                            <input type="number" id="<?php echo esc_attr( $field_id ); ?>"
                                   name="meta[<?php echo esc_attr( $clean_key ); ?>]"
                                   value="<?php echo esc_attr( $value ); ?>" step="any" />

                        <?php elseif ( $field['type'] === 'url' ) : ?>
                            <input type="url" id="<?php echo esc_attr( $field_id ); ?>"
                                   name="meta[<?php echo esc_attr( $clean_key ); ?>]"
                                   value="<?php echo esc_attr( $value ); ?>" />

                        <?php elseif ( $field['type'] === 'boolean' ) : ?>
                            <input type="checkbox" id="<?php echo esc_attr( $field_id ); ?>"
                                   name="meta[<?php echo esc_attr( $clean_key ); ?>]"
                                   value="1" <?php checked( $value, '1' ); ?> />

                        <?php else : ?>
                            <input type="text" id="<?php echo esc_attr( $field_id ); ?>"
                                   name="meta[<?php echo esc_attr( $clean_key ); ?>]"
                                   value="<?php echo esc_attr( $value ); ?>" />
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="apprco-form-actions">
                <button type="submit" class="apprco-submit-button">
                    <?php echo $is_new ? esc_html__( 'Create Vacancy', 'apprenticeship-connect' ) : esc_html__( 'Update Vacancy', 'apprenticeship-connect' ); ?>
                </button>
                <span class="apprco-form-status"></span>
            </div>
        </form>
        <?php

        return ob_get_clean();
    }

    /**
     * Apply button shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_apply_button( $atts ): string {
        $atts = shortcode_atts( array(
            'id'    => null,
            'text'  => __( 'Apply Now', 'apprenticeship-connect' ),
            'class' => '',
        ), $atts );

        $post_id = $this->get_vacancy_id( $atts['id'] );

        if ( ! $post_id ) {
            return '';
        }

        $vacancy_url = get_post_meta( $post_id, '_apprco_vacancy_url', true );

        if ( ! $vacancy_url ) {
            return '';
        }

        $class = 'apprco-apply-button';
        if ( $atts['class'] ) {
            $class .= ' ' . sanitize_html_class( $atts['class'] );
        }

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener" class="%s">%s &raquo;</a>',
            esc_url( $vacancy_url ),
            esc_attr( $class ),
            esc_html( $atts['text'] )
        );
    }

    /**
     * Stats shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_stats( $atts ): string {
        $atts = shortcode_atts( array(
            'type'   => 'total',
            'format' => '',
            'before' => '',
            'after'  => '',
        ), $atts );

        $value = 0;

        switch ( $atts['type'] ) {
            case 'total':
                $counts = wp_count_posts( 'apprco_vacancy' );
                $value  = $counts->publish ?? 0;
                break;

            case 'active':
                $query = new WP_Query( array(
                    'post_type'      => 'apprco_vacancy',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
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
                ) );
                $value = $query->found_posts;
                break;

            case 'employers':
                $terms = get_terms( array(
                    'taxonomy'   => 'apprco_employer',
                    'hide_empty' => true,
                ) );
                $value = is_array( $terms ) ? count( $terms ) : 0;
                break;

            case 'levels':
                $terms = get_terms( array(
                    'taxonomy'   => 'apprco_level',
                    'hide_empty' => true,
                ) );
                $value = is_array( $terms ) ? count( $terms ) : 0;
                break;
        }

        $formatted = $atts['format'] ? sprintf( $atts['format'], $value ) : number_format_i18n( $value );

        return $atts['before'] . esc_html( $formatted ) . $atts['after'];
    }
}
