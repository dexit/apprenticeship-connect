<?php
/**
 * Settings admin page.
 *
 * @package ApprenticeshipConnector\Admin
 */

namespace ApprenticeshipConnector\Admin;

use ApprenticeshipConnector\Core\Settings;

class SettingsPage {

	private const OPTION_GROUP = 'appcon_settings_group';
	private const PAGE_SLUG    = 'appcon-settings';

	public static function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Apprenticeship Connector Settings', 'apprenticeship-connector' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public static function register(): void {
		register_setting( self::OPTION_GROUP, 'appcon_settings', [
			'sanitize_callback' => [ self::class, 'sanitize' ],
		] );

		add_settings_section( 'appcon_api', __( 'API Configuration', 'apprenticeship-connector' ), '__return_false', self::PAGE_SLUG );

		add_settings_field( 'api_key', __( 'Subscription Key', 'apprenticeship-connector' ), [ self::class, 'field_api_key' ], self::PAGE_SLUG, 'appcon_api' );
		add_settings_field( 'api_base_url', __( 'API Base URL', 'apprenticeship-connector' ), [ self::class, 'field_api_url' ], self::PAGE_SLUG, 'appcon_api' );
		add_settings_field( 'rate_limit_ms', __( 'Rate Limit (ms)', 'apprenticeship-connector' ), [ self::class, 'field_rate_limit' ], self::PAGE_SLUG, 'appcon_api' );
	}

	public static function field_api_key(): void {
		$val = esc_attr( Settings::get( 'api_key', '' ) );
		echo "<input type='password' name='appcon_settings[api_key]' value='{$val}' class='regular-text' autocomplete='off' />";
		echo '<p class="description">' . esc_html__( 'Ocp-Apim-Subscription-Key from the UK Government API portal.', 'apprenticeship-connector' ) . '</p>';
	}

	public static function field_api_url(): void {
		$val = esc_attr( Settings::get( 'api_base_url', 'https://api.apprenticeships.education.gov.uk/vacancies' ) );
		echo "<input type='url' name='appcon_settings[api_base_url]' value='{$val}' class='regular-text' />";
	}

	public static function field_rate_limit(): void {
		$val = (int) Settings::get( 'rate_limit_ms', 250 );
		echo "<input type='number' name='appcon_settings[rate_limit_ms]' value='{$val}' min='100' max='5000' step='50' /> ms";
		echo '<p class="description">' . esc_html__( 'Minimum delay between API requests. Default: 250ms.', 'apprenticeship-connector' ) . '</p>';
	}

	public static function sanitize( array $input ): array {
		return [
			'api_key'       => sanitize_text_field( $input['api_key']       ?? '' ),
			'api_base_url'  => esc_url_raw(          $input['api_base_url']  ?? '' ),
			'rate_limit_ms' => max( 100, (int)       ( $input['rate_limit_ms'] ?? 250 ) ),
		];
	}
}
