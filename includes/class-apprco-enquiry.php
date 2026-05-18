<?php
/**
 * Enquiry System — form submissions, DB storage, REST API, admin management.
 *
 * Provides:
 *  - `apprco_enquiries` database table for persistent storage.
 *  - [apprco_enquiry_form] shortcode (vacancy-specific or generic).
 *  - REST endpoint POST /apprco/v1/enquiries (public, nonce-protected).
 *  - REST endpoints GET|PATCH|DELETE /apprco/v1/enquiries[/{id}] (admin).
 *  - Admin submenu page under Appr Connect for reviewing submissions.
 *  - Email notification to admin on each new submission.
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Enquiry
 */
class Apprco_Enquiry {

	/** Enquiry status values. */
	public const STATUS_NEW     = 'new';
	public const STATUS_READ    = 'read';
	public const STATUS_REPLIED = 'replied';
	public const STATUS_SPAM    = 'spam';

	/** @var self|null */
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'apprco_enquiry_form', array( $this, 'shortcode' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
			add_action( 'admin_post_apprco_enquiry_action', array( $this, 'handle_admin_action' ) );
		}
	}

	// ── Database ─────────────────────────────────────────────────────────────

	/**
	 * Create the enquiries table via dbDelta().
	 */
	public static function create_table(): void {
		global $wpdb;
		$table           = $wpdb->prefix . 'apprco_enquiries';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			vacancy_ref varchar(100) DEFAULT NULL,
			vacancy_title varchar(500) DEFAULT NULL,
			employer_name varchar(255) DEFAULT NULL,
			name varchar(255) NOT NULL,
			email varchar(255) NOT NULL,
			phone varchar(50) DEFAULT NULL,
			message text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'new',
			source varchar(50) NOT NULL DEFAULT 'frontend',
			ip_address varchar(45) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			admin_notes text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_status (status),
			KEY idx_vacancy_ref (vacancy_ref),
			KEY idx_email (email(50)),
			KEY idx_created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	// ── CRUD ─────────────────────────────────────────────────────────────────

	/**
	 * Validate and insert a new enquiry.
	 *
	 * @param array $data Raw submission data.
	 * @return int|WP_Error Inserted row ID or WP_Error.
	 */
	public function submit( array $data ): int|WP_Error {
		// Validate required fields.
		$name    = sanitize_text_field( $data['name'] ?? '' );
		$email   = sanitize_email( $data['email'] ?? '' );
		$message = sanitize_textarea_field( $data['message'] ?? '' );

		if ( empty( $name ) ) {
			return new WP_Error( 'missing_name', __( 'Name is required.', 'apprenticeship-connect' ) );
		}
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'A valid email address is required.', 'apprenticeship-connect' ) );
		}
		if ( empty( $message ) ) {
			return new WP_Error( 'missing_message', __( 'Message is required.', 'apprenticeship-connect' ) );
		}

		global $wpdb;

		$row = array(
			'vacancy_ref'   => sanitize_text_field( $data['vacancy_ref'] ?? '' ) ?: null,
			'vacancy_title' => sanitize_text_field( $data['vacancy_title'] ?? '' ) ?: null,
			'employer_name' => sanitize_text_field( $data['employer_name'] ?? '' ) ?: null,
			'name'          => $name,
			'email'         => $email,
			'phone'         => sanitize_text_field( $data['phone'] ?? '' ) ?: null,
			'message'       => $message,
			'status'        => self::STATUS_NEW,
			'source'        => sanitize_key( $data['source'] ?? 'frontend' ),
			'ip_address'    => $this->get_ip(),
			'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
				: null,
			'created_at'    => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $wpdb->prefix . 'apprco_enquiries', $row );

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Could not save enquiry. Please try again.', 'apprenticeship-connect' ) );
		}

		$enquiry_id = (int) $wpdb->insert_id;
		$row['id']  = $enquiry_id;

		// Log to import logger if available.
		if ( class_exists( 'Apprco_Import_Logger' ) ) {
			$logger    = Apprco_Import_Logger::get_instance();
			$import_id = $logger->start_import( 'enquiry', 'frontend' );
			$logger->log( $import_id, 'info', 'enquiry', sprintf(
				'New enquiry #%d from %s <%s> for vacancy %s',
				$enquiry_id,
				$name,
				$email,
				$row['vacancy_ref'] ?? 'generic'
			) );
			$logger->end_import( $import_id, 1, 1, 0, 0, 0, 0, 'completed' );
		}

		$this->send_notification( $row );

		/**
		 * Fires after a new enquiry is saved.
		 *
		 * @param int   $enquiry_id Enquiry ID.
		 * @param array $row        Saved enquiry data.
		 */
		do_action( 'apprco_enquiry_submitted', $enquiry_id, $row );

		return $enquiry_id;
	}

	/**
	 * Retrieve a single enquiry by ID.
	 *
	 * @param int $id Enquiry ID.
	 * @return array|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $wpdb->prefix . 'apprco_enquiries', $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Query enquiries with filters.
	 *
	 * @param array $args {
	 *   @type string $status      Filter by status.
	 *   @type string $search      Search name/email/vacancy_title.
	 *   @type string $vacancy_ref Filter by vacancy reference.
	 *   @type int    $per_page    Results per page (default 20).
	 *   @type int    $paged       Page number (default 1).
	 *   @type string $orderby     Column to sort by (default created_at).
	 *   @type string $order       ASC|DESC (default DESC).
	 * }
	 * @return array { items: array, total: int }
	 */
	public function get_all( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'      => '',
			'search'      => '',
			'vacancy_ref' => '',
			'per_page'    => 20,
			'paged'       => 1,
			'orderby'     => 'created_at',
			'order'       => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );

		$table    = $wpdb->prefix . 'apprco_enquiries';
		$where    = array( '1=1' );
		$prepared = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]    = 'status = %s';
			$prepared[] = $args['status'];
		}

		if ( ! empty( $args['vacancy_ref'] ) ) {
			$where[]    = 'vacancy_ref = %s';
			$prepared[] = $args['vacancy_ref'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like       = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]    = '(name LIKE %s OR email LIKE %s OR vacancy_title LIKE %s OR employer_name LIKE %s)';
			$prepared[] = $like;
			$prepared[] = $like;
			$prepared[] = $like;
			$prepared[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$allowed_order   = array( 'id', 'name', 'email', 'status', 'created_at', 'vacancy_ref' );
		$orderby         = in_array( $args['orderby'], $allowed_order, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$per_page        = max( 1, (int) $args['per_page'] );
		$offset          = max( 0, ( (int) $args['paged'] - 1 ) * $per_page );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", ...$prepared )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d",
				...array_merge( $prepared, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		return array(
			'items' => $items ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Update an enquiry's status and optionally add admin notes.
	 *
	 * @param int    $id     Enquiry ID.
	 * @param string $status New status.
	 * @param string $notes  Optional admin notes to append.
	 * @return bool
	 */
	public function update_status( int $id, string $status, string $notes = '' ): bool {
		global $wpdb;

		$allowed = array( self::STATUS_NEW, self::STATUS_READ, self::STATUS_REPLIED, self::STATUS_SPAM );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$data = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);
		if ( '' !== $notes ) {
			$data['admin_notes'] = sanitize_textarea_field( $notes );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $wpdb->update(
			$wpdb->prefix . 'apprco_enquiries',
			$data,
			array( 'id' => $id )
		);
	}

	/**
	 * Hard-delete an enquiry.
	 *
	 * @param int $id Enquiry ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $wpdb->delete( $wpdb->prefix . 'apprco_enquiries', array( 'id' => $id ) );
	}

	// ── Email Notification ────────────────────────────────────────────────────

	/**
	 * Send an admin notification email for a new enquiry.
	 *
	 * @param array $enquiry Enquiry row data.
	 */
	private function send_notification( array $enquiry ): void {
		$to      = get_option( 'admin_email' );
		$subject = sprintf(
			/* translators: %s: enquiry submitter name */
			__( '[Apprenticeship Connect] New Enquiry from %s', 'apprenticeship-connect' ),
			$enquiry['name']
		);

		$vacancy_line = $enquiry['vacancy_ref']
			? sprintf( "\n%s: %s (%s)", __( 'Vacancy', 'apprenticeship-connect' ), $enquiry['vacancy_title'] ?? '', $enquiry['vacancy_ref'] )
			: '';

		$body = sprintf(
			"%s:\n\n%s: %s\n%s: %s\n%s: %s%s\n\n%s:\n%s\n\n— %s\n%s",
			__( 'A new apprenticeship enquiry has been submitted', 'apprenticeship-connect' ),
			__( 'Name', 'apprenticeship-connect' ),
			$enquiry['name'],
			__( 'Email', 'apprenticeship-connect' ),
			$enquiry['email'],
			__( 'Phone', 'apprenticeship-connect' ),
			$enquiry['phone'] ?? __( 'Not provided', 'apprenticeship-connect' ),
			$vacancy_line,
			__( 'Message', 'apprenticeship-connect' ),
			$enquiry['message'],
			get_bloginfo( 'name' ),
			admin_url( 'admin.php?page=apprco-enquiries' )
		);

		/**
		 * Filter the enquiry notification email address.
		 *
		 * @param string $to      Recipient email.
		 * @param array  $enquiry Enquiry data.
		 */
		$to = apply_filters( 'apprco_enquiry_notification_email', $to, $enquiry );

		if ( $to ) {
			wp_mail( $to, $subject, $body );
		}
	}

	// ── Shortcode ─────────────────────────────────────────────────────────────

	/**
	 * Render the enquiry form.
	 *
	 * Usage: [apprco_enquiry_form]
	 *        [apprco_enquiry_form vacancy_ref="VAC001234" vacancy_title="Digital Support Technician"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'vacancy_ref'   => '',
				'vacancy_title' => '',
				'employer_name' => '',
				'title'         => __( 'Enquire About This Vacancy', 'apprenticeship-connect' ),
				'button_label'  => __( 'Send Enquiry', 'apprenticeship-connect' ),
				'success_msg'   => __( 'Thank you! Your enquiry has been sent. We will be in touch shortly.', 'apprenticeship-connect' ),
			),
			(array) $atts,
			'apprco_enquiry_form'
		);

		// Auto-populate from current vacancy CPT post if on a single page.
		if ( is_singular( 'apprco_vacancy' ) && empty( $atts['vacancy_ref'] ) ) {
			global $post;
			$atts['vacancy_ref']   = get_post_meta( $post->ID, '_apprco_vacancy_ref', true );
			$atts['vacancy_title'] = get_the_title( $post );
			$atts['employer_name'] = get_post_meta( $post->ID, '_apprco_employer_name', true );
		}

		$nonce        = wp_create_nonce( 'apprco_enquiry_nonce' );
		$endpoint_url = rest_url( 'apprco/v1/enquiries' );
		$form_id      = 'apprco-enquiry-' . wp_generate_password( 6, false );

		ob_start();
		?>
		<div class="apprco-enquiry-form-wrap" id="<?php echo esc_attr( $form_id ); ?>-wrap">
			<h3 class="apprco-enquiry-title"><?php echo esc_html( $atts['title'] ); ?></h3>

			<form id="<?php echo esc_attr( $form_id ); ?>" class="apprco-enquiry-form" novalidate>
				<?php wp_nonce_field( 'apprco_enquiry_nonce', '_apprco_nonce', true ); ?>
				<input type="hidden" name="vacancy_ref"   value="<?php echo esc_attr( $atts['vacancy_ref'] ); ?>">
				<input type="hidden" name="vacancy_title" value="<?php echo esc_attr( $atts['vacancy_title'] ); ?>">
				<input type="hidden" name="employer_name" value="<?php echo esc_attr( $atts['employer_name'] ); ?>">
				<input type="hidden" name="source"        value="shortcode">

				<div class="apprco-field">
					<label for="<?php echo esc_attr( $form_id ); ?>-name">
						<?php esc_html_e( 'Your Name', 'apprenticeship-connect' ); ?> <span aria-hidden="true">*</span>
					</label>
					<input type="text" id="<?php echo esc_attr( $form_id ); ?>-name"
						name="name" required autocomplete="name">
				</div>

				<div class="apprco-field">
					<label for="<?php echo esc_attr( $form_id ); ?>-email">
						<?php esc_html_e( 'Email Address', 'apprenticeship-connect' ); ?> <span aria-hidden="true">*</span>
					</label>
					<input type="email" id="<?php echo esc_attr( $form_id ); ?>-email"
						name="email" required autocomplete="email">
				</div>

				<div class="apprco-field">
					<label for="<?php echo esc_attr( $form_id ); ?>-phone">
						<?php esc_html_e( 'Phone Number', 'apprenticeship-connect' ); ?>
					</label>
					<input type="tel" id="<?php echo esc_attr( $form_id ); ?>-phone"
						name="phone" autocomplete="tel">
				</div>

				<div class="apprco-field">
					<label for="<?php echo esc_attr( $form_id ); ?>-message">
						<?php esc_html_e( 'Message', 'apprenticeship-connect' ); ?> <span aria-hidden="true">*</span>
					</label>
					<textarea id="<?php echo esc_attr( $form_id ); ?>-message"
						name="message" rows="5" required></textarea>
				</div>

				<div class="apprco-field apprco-field--submit">
					<button type="submit" class="apprco-enquiry-submit">
						<?php echo esc_html( $atts['button_label'] ); ?>
					</button>
					<span class="apprco-enquiry-spinner" aria-hidden="true" style="display:none">
						<?php esc_html_e( 'Sending…', 'apprenticeship-connect' ); ?>
					</span>
				</div>

				<div class="apprco-enquiry-feedback" role="alert" aria-live="polite"></div>
			</form>
		</div>

		<script>
		(function() {
			var formId  = <?php echo wp_json_encode( $form_id ); ?>;
			var apiUrl  = <?php echo wp_json_encode( $endpoint_url ); ?>;
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
			var success = <?php echo wp_json_encode( $atts['success_msg'] ); ?>;

			document.addEventListener( 'DOMContentLoaded', function() {
				var form = document.getElementById( formId );
				if ( ! form ) return;

				form.addEventListener( 'submit', function( e ) {
					e.preventDefault();
					var feedback = form.querySelector( '.apprco-enquiry-feedback' );
					var spinner  = form.querySelector( '.apprco-enquiry-spinner' );
					var btn      = form.querySelector( '.apprco-enquiry-submit' );

					feedback.textContent = '';
					feedback.className   = 'apprco-enquiry-feedback';
					spinner.style.display = 'inline';
					btn.disabled = true;

					var data = {};
					new FormData( form ).forEach( function( v, k ) { data[ k ] = v; } );

					fetch( apiUrl, {
						method : 'POST',
						headers: {
							'Content-Type' : 'application/json',
							'X-WP-Nonce'   : nonce
						},
						body: JSON.stringify( data )
					} )
					.then( function( r ) { return r.json().then( function( j ) { return { ok: r.ok, body: j }; } ); } )
					.then( function( res ) {
						spinner.style.display = 'none';
						btn.disabled = false;
						if ( res.ok ) {
							form.style.display = 'none';
							feedback.className = 'apprco-enquiry-feedback apprco-enquiry-feedback--success';
							feedback.textContent = success;
						} else {
							feedback.className = 'apprco-enquiry-feedback apprco-enquiry-feedback--error';
							feedback.textContent = res.body.message || <?php echo wp_json_encode( __( 'An error occurred. Please try again.', 'apprenticeship-connect' ) ); ?>;
						}
					} )
					.catch( function() {
						spinner.style.display = 'none';
						btn.disabled = false;
						feedback.className = 'apprco-enquiry-feedback apprco-enquiry-feedback--error';
						feedback.textContent = <?php echo wp_json_encode( __( 'Network error. Please try again.', 'apprenticeship-connect' ) ); ?>;
					} );
				} );
			} );
		}());
		</script>
		<?php
		return (string) ob_get_clean();
	}

	// ── REST API ──────────────────────────────────────────────────────────────

	/**
	 * Register REST endpoints for enquiries.
	 */
	public function register_rest_routes(): void {
		// Public: submit enquiry (nonce verified in callback).
		register_rest_route(
			'apprco/v1',
			'/enquiries',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'rest_list' ),
					'permission_callback' => array( $this, 'admin_permission' ),
					'args'                => array(
						'status'      => array( 'type' => 'string', 'default' => '' ),
						'search'      => array( 'type' => 'string', 'default' => '' ),
						'vacancy_ref' => array( 'type' => 'string', 'default' => '' ),
						'per_page'    => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
						'page'        => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_submit' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			'apprco/v1',
			'/enquiries/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'rest_get' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_update' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'rest_delete' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);
	}

	/** Admin-only capability check. */
	public function admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/** REST: submit a new enquiry. */
	public function rest_submit( WP_REST_Request $request ): WP_REST_Response {
		// Verify nonce from X-WP-Nonce header (set by wp.apiFetch) or from body.
		$nonce = $request->get_header( 'X-WP-Nonce' )
			?? $request->get_param( '_wpnonce' )
			?? '';

		if ( ! wp_verify_nonce( $nonce, 'apprco_enquiry_nonce' ) && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Security check failed. Please refresh the page.', 'apprenticeship-connect' ) ),
				403
			);
		}

		$data   = $request->get_json_params() ?: $request->get_params();
		$result = $this->submit( $data );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'message' => $result->get_error_message() ),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'id'      => $result,
				'message' => __( 'Enquiry received. Thank you!', 'apprenticeship-connect' ),
			),
			201
		);
	}

	/** REST: list enquiries (admin). */
	public function rest_list( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->get_all( array(
			'status'      => $request->get_param( 'status' ),
			'search'      => $request->get_param( 'search' ),
			'vacancy_ref' => $request->get_param( 'vacancy_ref' ),
			'per_page'    => $request->get_param( 'per_page' ),
			'paged'       => $request->get_param( 'page' ),
		) );
		return new WP_REST_Response( $result, 200 );
	}

	/** REST: get single enquiry (admin). */
	public function rest_get( WP_REST_Request $request ): WP_REST_Response {
		$enquiry = $this->get( (int) $request['id'] );
		if ( ! $enquiry ) {
			return new WP_REST_Response( array( 'message' => __( 'Not found.', 'apprenticeship-connect' ) ), 404 );
		}
		return new WP_REST_Response( $enquiry, 200 );
	}

	/** REST: update status/notes (admin). */
	public function rest_update( WP_REST_Request $request ): WP_REST_Response {
		$data    = $request->get_json_params();
		$status  = sanitize_key( $data['status'] ?? '' );
		$notes   = sanitize_textarea_field( $data['admin_notes'] ?? '' );
		$success = $this->update_status( (int) $request['id'], $status, $notes );
		return new WP_REST_Response( array( 'success' => $success ), $success ? 200 : 400 );
	}

	/** REST: delete enquiry (admin). */
	public function rest_delete( WP_REST_Request $request ): WP_REST_Response {
		$success = $this->delete( (int) $request['id'] );
		return new WP_REST_Response( array( 'success' => $success ), $success ? 200 : 400 );
	}

	// ── Admin UI ──────────────────────────────────────────────────────────────

	/**
	 * Register admin submenu page.
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'apprco-dashboard',
			__( 'Enquiries', 'apprenticeship-connect' ),
			__( 'Enquiries', 'apprenticeship-connect' ),
			'manage_options',
			'apprco-enquiries',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Handle admin bulk / row actions (POST from admin page).
	 */
	public function handle_admin_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'apprenticeship-connect' ) );
		}

		$action  = sanitize_key( $_POST['apprco_action'] ?? '' );
		$ids     = array_map( 'intval', (array) ( $_POST['enquiry_ids'] ?? array() ) );
		$nonce   = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

		if ( ! wp_verify_nonce( $nonce, 'apprco_enquiry_admin' ) || empty( $ids ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=apprco-enquiries' ) );
			exit;
		}

		foreach ( $ids as $id ) {
			switch ( $action ) {
				case 'mark_read':
					$this->update_status( $id, self::STATUS_READ );
					break;
				case 'mark_replied':
					$this->update_status( $id, self::STATUS_REPLIED );
					break;
				case 'mark_spam':
					$this->update_status( $id, self::STATUS_SPAM );
					break;
				case 'delete':
					$this->delete( $id );
					break;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'apprco-enquiries', 'updated' => count( $ids ) ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the Enquiries admin page.
	 */
	public function admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'apprenticeship-connect' ) );
		}

		// Handle single-row inline status/delete action (GET-based, nonce-protected).
		if ( isset( $_GET['apprco_status'], $_GET['apprco_id'], $_GET['_wpnonce'] ) ) {
			$inline_id    = intval( $_GET['apprco_id'] );
			$inline_nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
			if ( wp_verify_nonce( $inline_nonce, 'apprco_status_' . $inline_id ) ) {
				$inline_action = sanitize_key( $_GET['apprco_status'] );
				if ( 'delete_single' === $inline_action ) {
					$this->delete( $inline_id );
				} else {
					$this->update_status( $inline_id, $inline_action );
				}
			}
			wp_safe_redirect( admin_url( 'admin.php?page=apprco-enquiries' ) );
			exit;
		}

		$filter_status = sanitize_key( $_GET['status'] ?? '' );
		$filter_search = sanitize_text_field( $_GET['s'] ?? '' );
		$paged         = max( 1, intval( $_GET['paged'] ?? 1 ) );
		$per_page      = 20;

		$result = $this->get_all( array(
			'status'   => $filter_status,
			'search'   => $filter_search,
			'per_page' => $per_page,
			'paged'    => $paged,
		) );

		$items      = $result['items'];
		$total      = $result['total'];
		$num_pages  = (int) ceil( $total / $per_page );

		// Status counts for tabs.
		$counts = array();
		foreach ( array( '', self::STATUS_NEW, self::STATUS_READ, self::STATUS_REPLIED, self::STATUS_SPAM ) as $s ) {
			$counts[ $s ] = $this->get_all( array( 'status' => $s, 'per_page' => 1 ) )['total'];
		}

		$page_url     = admin_url( 'admin.php?page=apprco-enquiries' );
		$nonce_bulk   = wp_create_nonce( 'apprco_enquiry_admin' );

		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf(
				/* translators: %d: number of updated enquiries */
				_n( '%d enquiry updated.', '%d enquiries updated.', intval( $_GET['updated'] ), 'apprenticeship-connect' ),
				intval( $_GET['updated'] )
			) ) . '</p></div>';
		}

		echo '<div class="wrap apprco-enquiries-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Enquiries', 'apprenticeship-connect' ) . '</h1>';
		echo '<hr class="wp-header-end">';

		// Status tabs.
		$tab_labels = array(
			''                    => __( 'All', 'apprenticeship-connect' ),
			self::STATUS_NEW      => __( 'New', 'apprenticeship-connect' ),
			self::STATUS_READ     => __( 'Read', 'apprenticeship-connect' ),
			self::STATUS_REPLIED  => __( 'Replied', 'apprenticeship-connect' ),
			self::STATUS_SPAM     => __( 'Spam', 'apprenticeship-connect' ),
		);
		echo '<ul class="subsubsub">';
		$i = 0;
		foreach ( $tab_labels as $s => $label ) {
			$count   = $counts[ $s ] ?? 0;
			$active  = $filter_status === $s ? 'current' : '';
			$tab_url = $s ? add_query_arg( array( 'status' => $s ), $page_url ) : $page_url;
			$sep     = ( ++$i < count( $tab_labels ) ) ? ' |' : '';
			echo '<li><a href="' . esc_url( $tab_url ) . '" class="' . esc_attr( $active ) . '">'
				. esc_html( $label ) . ' <span class="count">(' . esc_html( (string) $count ) . ')</span>'
				. '</a>' . esc_html( $sep ) . '</li>';
		}
		echo '</ul>';

		// Search form.
		echo '<form method="get" style="margin:1em 0">';
		echo '<input type="hidden" name="page" value="apprco-enquiries">';
		if ( $filter_status ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $filter_status ) . '">';
		}
		echo '<input type="search" name="s" value="' . esc_attr( $filter_search ) . '" placeholder="' . esc_attr__( 'Search name, email, vacancy…', 'apprenticeship-connect' ) . '" style="width:300px"> ';
		submit_button( __( 'Search', 'apprenticeship-connect' ), 'button', '', false );
		echo '</form>';

		// Bulk actions + table.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="apprco_enquiry_action">';
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce_bulk ) . '">';

		// Bulk action bar (top).
		$this->render_bulk_bar( 'top' );

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="apprco-cb-all"></td>';
		echo '<th>' . esc_html__( 'ID', 'apprenticeship-connect' ) . '</th>';
		echo '<th>' . esc_html__( 'Name / Email', 'apprenticeship-connect' ) . '</th>';
		echo '<th>' . esc_html__( 'Vacancy', 'apprenticeship-connect' ) . '</th>';
		echo '<th>' . esc_html__( 'Message', 'apprenticeship-connect' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'apprenticeship-connect' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'apprenticeship-connect' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $items ) ) {
			echo '<tr><td colspan="7" style="text-align:center;padding:2em">'
				. esc_html__( 'No enquiries found.', 'apprenticeship-connect' )
				. '</td></tr>';
		}

		$status_labels = array(
			self::STATUS_NEW      => array( 'label' => __( 'New', 'apprenticeship-connect' ),     'color' => '#d63638' ),
			self::STATUS_READ     => array( 'label' => __( 'Read', 'apprenticeship-connect' ),    'color' => '#996800' ),
			self::STATUS_REPLIED  => array( 'label' => __( 'Replied', 'apprenticeship-connect' ), 'color' => '#007017' ),
			self::STATUS_SPAM     => array( 'label' => __( 'Spam', 'apprenticeship-connect' ),    'color' => '#787c82' ),
		);

		foreach ( $items as $enquiry ) {
			$id           = (int) $enquiry['id'];
			$sl           = $status_labels[ $enquiry['status'] ] ?? array( 'label' => $enquiry['status'], 'color' => '#787c82' );
			$mark_read    = wp_create_nonce( 'apprco_status_' . $id );
			$mark_replied = wp_create_nonce( 'apprco_status_' . $id );
			$vacancy_text = $enquiry['vacancy_title'] ?? '';
			if ( $enquiry['vacancy_ref'] ) {
				$vacancy_text .= ' <small>(' . esc_html( $enquiry['vacancy_ref'] ) . ')</small>';
			}

			echo '<tr>';
			echo '<th class="check-column"><input type="checkbox" name="enquiry_ids[]" value="' . esc_attr( (string) $id ) . '"></th>';
			echo '<td>#' . esc_html( (string) $id ) . '</td>';
			echo '<td><strong>' . esc_html( $enquiry['name'] ) . '</strong><br>'
				. '<a href="mailto:' . esc_attr( $enquiry['email'] ) . '">' . esc_html( $enquiry['email'] ) . '</a>';
			if ( $enquiry['phone'] ) {
				echo '<br><small>' . esc_html( $enquiry['phone'] ) . '</small>';
			}
			echo '</td>';
			echo '<td>' . wp_kses( $vacancy_text, array( 'small' => array() ) ) . '</td>';
			echo '<td>' . esc_html( wp_trim_words( $enquiry['message'] ?? '', 15 ) ) . '</td>';
			echo '<td><span style="color:' . esc_attr( $sl['color'] ) . ';font-weight:600">'
				. esc_html( $sl['label'] ) . '</span>';

			// Row actions.
			echo '<div class="row-actions">';
			if ( self::STATUS_READ !== $enquiry['status'] && self::STATUS_REPLIED !== $enquiry['status'] ) {
				$url = add_query_arg( array(
					'page'         => 'apprco-enquiries',
					'apprco_id'    => $id,
					'apprco_status'=> self::STATUS_READ,
					'_wpnonce'     => $mark_read,
				), admin_url( 'admin.php' ) );
				echo '<span class="read"><a href="' . esc_url( $url ) . '">' . esc_html__( 'Mark Read', 'apprenticeship-connect' ) . '</a></span> | ';
			}
			if ( self::STATUS_REPLIED !== $enquiry['status'] ) {
				$url = add_query_arg( array(
					'page'         => 'apprco-enquiries',
					'apprco_id'    => $id,
					'apprco_status'=> self::STATUS_REPLIED,
					'_wpnonce'     => $mark_replied,
				), admin_url( 'admin.php' ) );
				echo '<span class="replied"><a href="' . esc_url( $url ) . '">' . esc_html__( 'Mark Replied', 'apprenticeship-connect' ) . '</a></span> | ';
			}
			$del_nonce = wp_create_nonce( 'apprco_enquiry_admin' );
			echo '<span class="delete" style="color:#a00"><a href="' . esc_url( add_query_arg( array(
				'page'        => 'apprco-enquiries',
				'apprco_id'   => $id,
				'apprco_status'=> 'delete_single',
				'_wpnonce'    => wp_create_nonce( 'apprco_status_' . $id ),
			), admin_url( 'admin.php' ) ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this enquiry?', 'apprenticeship-connect' ) ) . '\')">' . esc_html__( 'Delete', 'apprenticeship-connect' ) . '</a></span>';
			echo '</div>';
			echo '</td>';
			echo '<td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $enquiry['created_at'] ) ) ) . '</td>';
			echo '</tr>';

			// Expandable message row.
			echo '<tr class="apprco-enquiry-detail" style="background:#f9f9f9;display:none" id="apprco-detail-' . esc_attr( (string) $id ) . '">';
			echo '<td></td><td colspan="6" style="padding:1em 0">';
			echo '<strong>' . esc_html__( 'Full Message:', 'apprenticeship-connect' ) . '</strong><br>';
			echo '<p style="white-space:pre-wrap;margin:.5em 0">' . esc_html( $enquiry['message'] ) . '</p>';
			if ( $enquiry['admin_notes'] ) {
				echo '<strong>' . esc_html__( 'Admin Notes:', 'apprenticeship-connect' ) . '</strong><br>';
				echo '<p style="white-space:pre-wrap;margin:.5em 0">' . esc_html( $enquiry['admin_notes'] ) . '</p>';
			}
			echo '</td></tr>';
		}

		echo '</tbody></table>';

		$this->render_bulk_bar( 'bottom' );
		echo '</form>';

		// Pagination.
		if ( $num_pages > 1 ) {
			echo '<div class="tablenav bottom"><div class="tablenav-pages">';
			echo paginate_links( array(
				'base'      => add_query_arg( 'paged', '%#%', $page_url ),
				'format'    => '',
				'total'     => $num_pages,
				'current'   => $paged,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
			) );
			echo '</div></div>';
		}

		// Checkbox select-all JS + row expand.
		?>
		<script>
		document.getElementById('apprco-cb-all').addEventListener('change',function(){
			document.querySelectorAll('input[name="enquiry_ids[]"]').forEach(function(cb){cb.checked=this.checked;},this);
		});
		</script>
		<?php
		echo '</div>';
	}

	// ── Frontend assets ───────────────────────────────────────────────────────

	/**
	 * Enqueue minimal form styles on the frontend.
	 */
	public function enqueue_frontend_assets(): void {
		// Styles are inlined via the main frontend CSS; nothing extra needed.
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Render a bulk-action toolbar row.
	 *
	 * @param string $which 'top' or 'bottom'.
	 */
	private function render_bulk_bar( string $which ): void {
		echo '<div class="tablenav ' . esc_attr( $which ) . '">';
		echo '<div class="alignleft actions bulkactions">';
		echo '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">'
			. esc_html__( 'Select bulk action', 'apprenticeship-connect' ) . '</label>';
		echo '<select name="apprco_action" id="bulk-action-selector-' . esc_attr( $which ) . '">';
		echo '<option value="">' . esc_html__( 'Bulk Actions', 'apprenticeship-connect' ) . '</option>';
		echo '<option value="mark_read">'    . esc_html__( 'Mark as Read', 'apprenticeship-connect' )    . '</option>';
		echo '<option value="mark_replied">' . esc_html__( 'Mark as Replied', 'apprenticeship-connect' ) . '</option>';
		echo '<option value="mark_spam">'    . esc_html__( 'Mark as Spam', 'apprenticeship-connect' )    . '</option>';
		echo '<option value="delete">'       . esc_html__( 'Delete', 'apprenticeship-connect' )          . '</option>';
		echo '</select>';
		submit_button( __( 'Apply', 'apprenticeship-connect' ), 'action', '', false );
		echo '</div></div>';
	}

	/**
	 * Get the visitor's real IP address (respects common proxy headers).
	 */
	private function get_ip(): string {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// X-Forwarded-For can be a comma-separated list; take the first.
				$ip = trim( explode( ',', $ip )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}
}
