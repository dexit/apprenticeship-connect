<?php
/**
 * Settings admin page – full options surface for Apprenticeship Connector.
 *
 * Sections:
 *   appcon_api      – API credentials & rate limiting
 *   appcon_import   – Import defaults (page size, max pages, batch size)
 *   appcon_expiry   – Automatic vacancy expiry
 *   appcon_cache    – Transient / object-cache settings
 *   appcon_display  – Frontend display defaults
 *
 * @package ApprenticeshipConnector\Admin
 */

namespace ApprenticeshipConnector\Admin;

use ApprenticeshipConnector\Core\Settings;

class SettingsPage {

	private const OPTION_GROUP = 'appcon_settings_group';
	private const PAGE_SLUG    = 'appcon-settings';
	private const OPTION_KEY   = 'appcon_settings';

	// ── Render ─────────────────────────────────────────────────────────────

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'apprenticeship-connector' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Apprenticeship Connector – Settings', 'apprenticeship-connector' ); ?></h1>

			<?php settings_errors( self::OPTION_KEY ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save Settings', 'apprenticeship-connector' ) );
				?>
			</form>
		</div>
		<?php
	}

	// ── Registration ───────────────────────────────────────────────────────

	/**
	 * Register all settings, sections, and fields.
	 * Hook this to admin_init.
	 */
	public static function register(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			[
				'sanitize_callback' => [ self::class, 'sanitize' ],
				'default'           => [],
			]
		);

		self::register_api_section();
		self::register_import_section();
		self::register_expiry_section();
		self::register_cache_section();
		self::register_display_section();
	}

	// ── Section: API ───────────────────────────────────────────────────────

	private static function register_api_section(): void {
		add_settings_section(
			'appcon_api',
			__( 'API Configuration', 'apprenticeship-connector' ),
			static function () {
				echo '<p>' . esc_html__( 'Credentials and rate-limiting for the UK Government Display Advert API v2.', 'apprenticeship-connector' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		add_settings_field( 'api_key',        __( 'Subscription Key', 'apprenticeship-connector' ), [ self::class, 'field_api_key' ],     self::PAGE_SLUG, 'appcon_api' );
		add_settings_field( 'api_base_url',   __( 'API Base URL',     'apprenticeship-connector' ), [ self::class, 'field_api_url' ],     self::PAGE_SLUG, 'appcon_api' );
		add_settings_field( 'rate_limit_ms',  __( 'Rate Limit (ms)',  'apprenticeship-connector' ), [ self::class, 'field_rate_limit' ],  self::PAGE_SLUG, 'appcon_api' );
	}

	// ── Section: Import defaults ───────────────────────────────────────────

	private static function register_import_section(): void {
		add_settings_section(
			'appcon_import',
			__( 'Import Defaults', 'apprenticeship-connector' ),
			static function () {
				echo '<p>' . esc_html__( 'Default values applied to new Import Jobs. Individual jobs may override these.', 'apprenticeship-connector' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		add_settings_field( 'stage1_page_size',  __( 'Stage 1 – Page Size',   'apprenticeship-connector' ), [ self::class, 'field_stage1_page_size' ],  self::PAGE_SLUG, 'appcon_import' );
		add_settings_field( 'stage1_max_pages',  __( 'Stage 1 – Max Pages',   'apprenticeship-connector' ), [ self::class, 'field_stage1_max_pages' ],  self::PAGE_SLUG, 'appcon_import' );
		add_settings_field( 'stage2_delay_ms',   __( 'Stage 2 – Request Delay (ms)', 'apprenticeship-connector' ), [ self::class, 'field_stage2_delay' ], self::PAGE_SLUG, 'appcon_import' );
		add_settings_field( 'stage2_batch_size', __( 'Stage 2 – Batch Size',  'apprenticeship-connector' ), [ self::class, 'field_stage2_batch_size' ], self::PAGE_SLUG, 'appcon_import' );
	}

	// ── Section: Expiry ────────────────────────────────────────────────────

	private static function register_expiry_section(): void {
		add_settings_section(
			'appcon_expiry',
			__( 'Vacancy Expiry', 'apprenticeship-connector' ),
			static function () {
				echo '<p>' . esc_html__( 'Control how the plugin handles vacancies whose closing date has passed.', 'apprenticeship-connector' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		add_settings_field( 'auto_expiry_enabled', __( 'Auto-Expire Vacancies', 'apprenticeship-connector' ), [ self::class, 'field_auto_expiry' ],      self::PAGE_SLUG, 'appcon_expiry' );
		add_settings_field( 'expiry_notice_days',  __( 'Expiry Notice (days)',  'apprenticeship-connector' ), [ self::class, 'field_expiry_notice_days' ], self::PAGE_SLUG, 'appcon_expiry' );
	}

	// ── Section: Cache ─────────────────────────────────────────────────────

	private static function register_cache_section(): void {
		add_settings_section(
			'appcon_cache',
			__( 'Cache & Performance', 'apprenticeship-connector' ),
			static function () {
				echo '<p>' . esc_html__( 'Transient-based caching reduces API calls during sampling and preview operations.', 'apprenticeship-connector' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		add_settings_field( 'cache_api_responses', __( 'Cache API Responses', 'apprenticeship-connector' ), [ self::class, 'field_cache_enabled' ], self::PAGE_SLUG, 'appcon_cache' );
		add_settings_field( 'cache_ttl_minutes',   __( 'Cache TTL (minutes)', 'apprenticeship-connector' ), [ self::class, 'field_cache_ttl' ],     self::PAGE_SLUG, 'appcon_cache' );
	}

	// ── Section: Display ───────────────────────────────────────────────────

	private static function register_display_section(): void {
		add_settings_section(
			'appcon_display',
			__( 'Display Settings', 'apprenticeship-connector' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field( 'vacancies_per_page', __( 'Vacancies Per Page', 'apprenticeship-connector' ), [ self::class, 'field_per_page' ],      self::PAGE_SLUG, 'appcon_display' );
		add_settings_field( 'vacancy_slug',       __( 'Vacancy URL Slug',  'apprenticeship-connector' ), [ self::class, 'field_vacancy_slug' ],   self::PAGE_SLUG, 'appcon_display' );
	}

	// ── Field callbacks ────────────────────────────────────────────────────

	public static function field_api_key(): void {
		$val = esc_attr( Settings::get( 'api_key', '' ) );
		echo "<input type='password' name='appcon_settings[api_key]' value='{$val}' class='regular-text' autocomplete='off' />";
		echo '<p class="description">' . esc_html__( 'Ocp-Apim-Subscription-Key obtained from the UK Government API portal.', 'apprenticeship-connector' ) . '</p>';
	}

	public static function field_api_url(): void {
		$val = esc_attr( Settings::get( 'api_base_url', 'https://api.apprenticeships.education.gov.uk/vacancies' ) );
		echo "<input type='url' name='appcon_settings[api_base_url]' value='{$val}' class='regular-text' />";
		echo '<p class="description">' . esc_html__( 'Leave at the default unless you are using a proxy or sandbox.', 'apprenticeship-connector' ) . '</p>';
	}

	public static function field_rate_limit(): void {
		$val = (int) Settings::get( 'rate_limit_ms', 2000 );
		echo "<input type='number' name='appcon_settings[rate_limit_ms]' value='{$val}' min='500' max='10000' step='100' class='small-text' /> ms";
		echo '<p class="description">' . esc_html__( 'Minimum delay between consecutive API requests. The API allows 150 requests per 5 minutes – 2 000 ms (2 s) keeps you well within that limit.', 'apprenticeship-connector' ) . '</p>';
	}

	public static function field_stage1_page_size(): void {
		$val = (int) Settings::get( 'stage1_page_size', 100 );
		echo "<input type='number' name='appcon_settings[stage1_page_size]' value='{$val}' min='10' max='250' step='10' class='small-text' />";
		echo '<p class="description">' . esc_html__( 'Number of vacancy references fetched per Stage 1 API page. Max 250.', 'apprenticeship-connector' ) . '</p>';
	}

	public static function field_stage1_max_pages(): void {
		$val = (int) Settings::get( 'stage1_max_pages', 100 );
		echo "<input type='number' name='appcon_settings[stage1_max_pages]' value='{$val}' min='1' max='500' step='1' class='small-text' />";
		echo '<p class="description">' . esc_html__( 'Safety cap: stop Stage 1 after this many pages even if more exist. 100 pages × 100/page = up to 10 000 references.', 'apprenticeship-connector' ) . '</p>';
	}

	public static function field_stage2_delay(): void {
		$val = (int) Settings::get( 'stage2_delay_ms', 2000 );
		echo "<input type='number' name='appcon_settings[stage2_delay_ms]' value='{$val}' min='500' max='10000' step='100' class='small-text' /> ms";
		echo '<p class="description">' . esc_html__( 'Per-request delay for Stage 2 (full vacancy details). Persists across Action Scheduler batches via transient. Minimum 2 000 ms recommended.', 'apprenticeship-connector' ) . '</p>';
	}

	public static function field_stage2_batch_size(): void {
		$val = (int) Settings::get( 'stage2_batch_size', 10 );
		echo "<input type='number' name='appcon_settings[stage2_batch_size]' value='{$val}' min='1' max='50' step='1' class='small-text' />";
		echo '<p class="description">' . esc_html__( 'References processed per Action Scheduler action. Lower values are safer for shared hosting (shorter execution time per action).', 'apprenticeship-connector' ) . '</p>';
	}

	public static function field_auto_expiry(): void {
		$val = (bool) Settings::get( 'auto_expiry_enabled', true );
		echo "<label><input type='checkbox' name='appcon_settings[auto_expiry_enabled]' value='1'" . checked( $val, true, false ) . " /> ";
		echo esc_html__( 'Automatically set vacancies to Draft when their closing date passes (runs daily via Action Scheduler).', 'apprenticeship-connector' ) . '</label>';
	}

	public static function field_expiry_notice_days(): void {
		$val = (int) Settings::get( 'expiry_notice_days', 7 );
		echo "<input type='number' name='appcon_settings[expiry_notice_days]' value='{$val}' min='0' max='90' step='1' class='small-text' />";
		echo ' ' . esc_html__( 'days', 'apprenticeship-connector' );
		echo '<p class="description">' . esc_html__( 'Vacancies expiring within this many days are highlighted on the Dashboard.', 'apprenticeship-connector' ) . '</p>';
	}

	public static function field_cache_enabled(): void {
		$val = (bool) Settings::get( 'cache_api_responses', false );
		echo "<label><input type='checkbox' name='appcon_settings[cache_api_responses]' value='1'" . checked( $val, true, false ) . " /> ";
		echo esc_html__( 'Cache API sample responses (used by the FieldMapper UI) in WordPress transients.', 'apprenticeship-connector' ) . '</label>';
	}

	public static function field_cache_ttl(): void {
		$val = (int) Settings::get( 'cache_ttl_minutes', 60 );
		echo "<input type='number' name='appcon_settings[cache_ttl_minutes]' value='{$val}' min='1' max='1440' step='1' class='small-text' />";
		echo ' ' . esc_html__( 'minutes', 'apprenticeship-connector' );
	}

	public static function field_per_page(): void {
		$val = (int) Settings::get( 'vacancies_per_page', 10 );
		echo "<input type='number' name='appcon_settings[vacancies_per_page]' value='{$val}' min='1' max='100' step='1' class='small-text' />";
		echo '<p class="description">' . esc_html__( 'Default number of vacancies shown per page in the block and Elementor widget.', 'apprenticeship-connector' ) . '</p>';
	}

	public static function field_vacancy_slug(): void {
		$val = esc_attr( Settings::get( 'vacancy_slug', 'vacancies' ) );
		echo "<input type='text' name='appcon_settings[vacancy_slug]' value='{$val}' class='regular-text' />";
		echo '<p class="description">' . esc_html__( 'URL slug for the vacancy archive. Changing this requires saving Permalinks settings afterwards.', 'apprenticeship-connector' ) . '</p>';
	}

	// ── Sanitization ───────────────────────────────────────────────────────

	public static function sanitize( array $input ): array {
		$clean = [];

		// API
		$clean['api_key']       = sanitize_text_field( $input['api_key']       ?? '' );
		$clean['api_base_url']  = esc_url_raw( $input['api_base_url']          ?? '' );
		$clean['rate_limit_ms'] = max( 500, (int) ( $input['rate_limit_ms']    ?? 2000 ) );

		// Import defaults
		$clean['stage1_page_size']  = min( 250, max( 10,  (int) ( $input['stage1_page_size']  ?? 100  ) ) );
		$clean['stage1_max_pages']  = min( 500, max( 1,   (int) ( $input['stage1_max_pages']  ?? 100  ) ) );
		$clean['stage2_delay_ms']   = max( 500,           (int) ( $input['stage2_delay_ms']   ?? 2000 ) );
		$clean['stage2_batch_size'] = min( 50,  max( 1,   (int) ( $input['stage2_batch_size'] ?? 10   ) ) );

		// Expiry
		$clean['auto_expiry_enabled'] = ! empty( $input['auto_expiry_enabled'] );
		$clean['expiry_notice_days']  = min( 90, max( 0, (int) ( $input['expiry_notice_days'] ?? 7 ) ) );

		// Cache
		$clean['cache_api_responses'] = ! empty( $input['cache_api_responses'] );
		$clean['cache_ttl_minutes']   = min( 1440, max( 1, (int) ( $input['cache_ttl_minutes'] ?? 60 ) ) );

		// Display
		$clean['vacancies_per_page'] = min( 100, max( 1, (int) ( $input['vacancies_per_page'] ?? 10 ) ) );
		$clean['vacancy_slug']       = sanitize_title( $input['vacancy_slug'] ?? 'vacancies' ) ?: 'vacancies';

		return $clean;
	}
}
