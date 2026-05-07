<?php
/**
 * Abstract Base Tag Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Tag_Base
 *
 * Base class for all Apprenticeship Connect dynamic tags.
 */
abstract class Apprco_Tag_Base extends \Elementor\Core\DynamicTags\Tag {

	/**
	 * Get tag group.
	 *
	 * @return array
	 */
	public function get_group(): array {
		return array( Apprco_Elementor::TAG_GROUP );
	}

	/**
	 * Get categories.
	 *
	 * @return array
	 */
	public function get_categories(): array {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	/**
	 * Get meta key for this tag.
	 *
	 * @return string
	 */
	abstract protected function get_meta_key(): string;

	/**
	 * Render the tag.
	 *
	 * @return void
	 */
	public function render(): void {
		$post_id = get_the_ID();

		if ( ! $post_id || 'apprco_vacancy' !== get_post_type( $post_id ) ) {
			return;
		}

		$value = get_post_meta( $post_id, $this->get_meta_key(), true );
		echo esc_html( (string) $value );
	}
}
