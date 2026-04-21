<?php
/**
 * Elementor Integration
 */

if ( ! defined( 'ABSPATH' ) ) die;

class Apprco_Elementor {
    private static $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'elementor/dynamic_tags/register', array( $this, 'register_dynamic_tags' ) );
    }

    public function register_dynamic_tags( $dynamic_tags_manager ): void {
        if ( ! class_exists( 'Elementor\Core\DynamicTags\Tag' ) ) return;

        require_once APPRCO_PLUGIN_DIR . 'includes/class-apprco-elementor-tag.php';
        $dynamic_tags_manager->register( new Apprco_Vacancy_Dynamic_Tag() );
    }
}
