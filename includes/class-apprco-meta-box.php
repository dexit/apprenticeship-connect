<?php
/**
 * Meta Box Manager Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Meta_Box
 *
 * Handles the display and saving of vacancy meta data in the admin.
 */
class Apprco_Meta_Box {

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Meta_Box|null
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
		add_action( 'add_meta_boxes', array( $this, 'add_vacancy_meta_boxes' ) );
		add_action( 'save_post_apprco_vacancy', array( $this, 'save_vacancy_meta_box' ) );
	}

	/**
	 * Adds the vacancy meta box.
	 *
	 * @return void
	 */
	public function add_vacancy_meta_boxes(): void {
		add_meta_box(
			'apprco_vacancy_details',
			__( 'Vacancy Details', 'apprenticeship-connect' ),
			array( $this, 'render_vacancy_meta_box' ),
			'apprco_vacancy',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the meta box HTML.
	 *
	 * @param WP_Post $post The current post object.
	 * @return void
	 */
	public function render_vacancy_meta_box( $post ): void {
		wp_nonce_field( 'apprco_save_meta', 'apprco_meta_nonce' );

		$ref      = get_post_meta( $post->ID, '_apprco_vacancy_reference', true );
		$url      = get_post_meta( $post->ID, '_apprco_vacancy_url', true );
		$employer = get_post_meta( $post->ID, '_apprco_employer_name', true );
		$postcode = get_post_meta( $post->ID, '_apprco_postcode', true );

		?>
		<div class="apprco-meta-field">
			<label for="apprco_ref"><?php esc_html_e( 'Vacancy Reference', 'apprenticeship-connect' ); ?></label>
			<input type="text" id="apprco_ref" name="apprco_vacancy_reference" value="<?php echo esc_attr( (string) $ref ); ?>" class="widefat" />
		</div>
		<div class="apprco-meta-field" style="margin-top: 10px;">
			<label for="apprco_url"><?php esc_html_e( 'API Vacancy URL', 'apprenticeship-connect' ); ?></label>
			<input type="url" id="apprco_url" name="apprco_vacancy_url" value="<?php echo esc_url( (string) $url ); ?>" class="widefat" />
		</div>
		<div class="apprco-meta-field" style="margin-top: 10px;">
			<label for="apprco_employer"><?php esc_html_e( 'Employer Name', 'apprenticeship-connect' ); ?></label>
			<input type="text" id="apprco_employer" name="apprco_employer_name" value="<?php echo esc_attr( (string) $employer ); ?>" class="widefat" />
		</div>
		<div class="apprco-meta-field" style="margin-top: 10px;">
			<label for="apprco_postcode"><?php esc_html_e( 'Postcode', 'apprenticeship-connect' ); ?></label>
			<input type="text" id="apprco_postcode" name="apprco_postcode" value="<?php echo esc_attr( (string) $postcode ); ?>" class="widefat" />
		</div>
		<?php
	}

	/**
	 * Saves the meta box data.
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return void
	 */
	public function save_vacancy_meta_box( int $post_id ): void {
		if ( ! isset( $_POST['apprco_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['apprco_meta_nonce'] ), 'apprco_save_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'apprco_vacancy_reference' => '_apprco_vacancy_reference',
			'apprco_vacancy_url'       => '_apprco_vacancy_url',
			'apprco_employer_name'     => '_apprco_employer_name',
			'apprco_postcode'          => '_apprco_postcode',
		);

		foreach ( $fields as $post_key => $meta_key ) {
			if ( isset( $_POST[ $post_key ] ) ) {
				$val = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
				if ( '_apprco_vacancy_url' === $meta_key ) {
					$val = esc_url_raw( $val );
				}
				update_post_meta( $post_id, $meta_key, $val );
			}
		}
	}
}
