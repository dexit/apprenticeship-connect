<?php
/**
 * Blocks Manager Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Blocks
 *
 * Handles registration of Gutenberg blocks.
 */
class Apprco_Blocks {

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Blocks|null
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
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers Gutenberg blocks.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		$blocks = array(
			'vacancy-card',
			'vacancy-listing',
		);

		foreach ( $blocks as $block ) {
			$block_dir = APPRCO_PLUGIN_DIR . 'build/blocks/' . $block;
			if ( file_exists( $block_dir . '/block.json' ) ) {
				register_block_type( $block_dir );
			}
		}
	}

	/**
	 * Renders the vacancy list block.
	 *
	 * @return string
	 */
	public function render_vacancy_list() {
		return '<div class="apprco-vacancy-list"></div>';
	}

	/**
	 * Renders the vacancy search block.
	 *
	 * @return string
	 */
	public function render_vacancy_search() {
		return '<div id="apprco-search-root"></div>';
	}
}
