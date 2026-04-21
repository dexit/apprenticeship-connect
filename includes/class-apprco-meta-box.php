<?php
/**
 * Meta Box Manager - Enhanced V3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) die;

class Apprco_Meta_Box {

    private static $instance = null;
    const NONCE_ACTION = 'apprco_vacancy_meta_nonce';
    const NONCE_NAME = 'apprco_vacancy_meta_nonce_field';

    public static function get_instance(): self {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_vacancy_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta_box_data' ), 10, 2 );
    }

    public function add_vacancy_meta_boxes(): void {
        add_meta_box( 'apprco_vacancy_core', __( 'Core Vacancy Details', 'apprenticeship-connect' ), array( $this, 'render_core_meta_box' ), 'apprco_vacancy', 'normal', 'high' );
        add_meta_box( 'apprco_vacancy_wage', __( 'Wage & Benefits', 'apprenticeship-connect' ), array( $this, 'render_wage_meta_box' ), 'apprco_vacancy', 'normal', 'default' );
        add_meta_box( 'apprco_vacancy_employer', __( 'Employer & Location', 'apprenticeship-connect' ), array( $this, 'render_employer_meta_box' ), 'apprco_vacancy', 'side', 'default' );
        add_meta_box( 'apprco_vacancy_requirements', __( 'Requirements & Outcome', 'apprenticeship-connect' ), array( $this, 'render_requirements_meta_box' ), 'apprco_vacancy', 'normal', 'low' );
    }

    private function render_fields_grid( $post, $fields ) {
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; padding: 10px;">';
        foreach ( $fields as $key => $field ) {
            $val = get_post_meta( $post->ID, $key, true );
            echo '<div>';
            echo '<label style="display:block;font-weight:600;margin-bottom:4px;">' . esc_html( $field['label'] ) . '</label>';
            $readonly = ! empty($field['readonly']) ? 'readonly style="background:#f6f7f7;"' : '';
            if ($field['type'] === 'textarea') {
                echo "<textarea name='apprco_meta[$key]' class='widefat' rows='3' $readonly>" . esc_textarea($val) . "</textarea>";
            } else {
                echo "<input type='{$field['type']}' name='apprco_meta[$key]' value='" . esc_attr($val) . "' class='widefat' $readonly />";
            }
            echo '</div>';
        }
        echo '</div>';
    }

    public function render_core_meta_box( $post ) {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
        $this->render_fields_grid( $post, array(
            '_apprco_vacancy_reference'    => array( 'label' => 'Reference', 'type' => 'text', 'readonly' => true ),
            '_apprco_vacancy_url'          => array( 'label' => 'Application URL', 'type' => 'url' ),
            '_apprco_closing_date'         => array( 'label' => 'Closing Date', 'type' => 'date' ),
            '_apprco_start_date'           => array( 'label' => 'Start Date', 'type' => 'date' ),
            '_apprco_number_of_positions'  => array( 'label' => 'Positions', 'type' => 'number' ),
            '_apprco_apprenticeship_level' => array( 'label' => 'Appr. Level', 'type' => 'text' ),
        ));
    }

    public function render_wage_meta_box( $post ) {
        $this->render_fields_grid( $post, array(
            '_apprco_wage_amount'       => array( 'label' => 'Wage Amount', 'type' => 'text' ),
            '_apprco_wage_type'         => array( 'label' => 'Wage Type', 'type' => 'text' ),
            '_apprco_wage_unit'         => array( 'label' => 'Wage Unit', 'type' => 'text' ),
            '_apprco_hours_per_week'    => array( 'label' => 'Hours/Week', 'type' => 'number' ),
            '_apprco_expected_duration' => array( 'label' => 'Duration', 'type' => 'text' ),
        ));
    }

    public function render_employer_meta_box( $post ) {
        foreach ( array(
            '_apprco_employer_name' => 'Employer Name',
            '_apprco_postcode'      => 'Postcode',
            '_apprco_latitude'      => 'Latitude',
            '_apprco_longitude'     => 'Longitude',
        ) as $key => $label ) {
            $val = get_post_meta( $post->ID, $key, true );
            echo "<p><strong>$label:</strong><br/><input type='text' name='apprco_meta[$key]' value='" . esc_attr($val) . "' class='widefat'/></p>";
        }
    }

    public function render_requirements_meta_box( $post ) {
        $this->render_fields_grid( $post, array(
            '_apprco_skills'              => array( 'label' => 'Skills', 'type' => 'textarea' ),
            '_apprco_qualifications'      => array( 'label' => 'Qualifications', 'type' => 'textarea' ),
            '_apprco_outcome_description' => array( 'label' => 'Outcome', 'type' => 'textarea' ),
        ));
    }

    public function save_meta_box_data( $post_id, $post ) {
        if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_NAME ], self::NONCE_ACTION ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( 'apprco_vacancy' !== $post->post_type ) return;
        if ( ! isset( $_POST['apprco_meta'] ) ) return;

        foreach ( $_POST['apprco_meta'] as $key => $value ) {
            update_post_meta( $post_id, sanitize_key($key), wp_unslash($value) );
        }
    }
}
