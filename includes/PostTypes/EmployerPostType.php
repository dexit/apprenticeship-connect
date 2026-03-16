<?php
/**
 * Employer custom post type (optional – stores richer employer content).
 *
 * @package ApprenticeshipConnector\PostTypes
 */

namespace ApprenticeshipConnector\PostTypes;

class EmployerPostType {

	public static function register(): void {
		register_post_type( 'appcon_employer', [
			'labels' => [
				'name'          => __( 'Employers',       'apprenticeship-connector' ),
				'singular_name' => __( 'Employer',        'apprenticeship-connector' ),
				'add_new_item'  => __( 'Add New Employer','apprenticeship-connector' ),
				'edit_item'     => __( 'Edit Employer',   'apprenticeship-connector' ),
				'view_item'     => __( 'View Employer',   'apprenticeship-connector' ),
			],
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'apprenticeship-connector',
			'show_in_rest'    => true,
			'supports'        => [ 'title', 'editor', 'thumbnail' ],
			'capability_type' => 'post',
		] );

		foreach ( [
			'_appcon_emp_website'       => [ 'type' => 'string', 'sanitize' => 'esc_url_raw' ],
			'_appcon_emp_contact_name'  => [ 'type' => 'string', 'sanitize' => 'sanitize_text_field' ],
			'_appcon_emp_contact_phone' => [ 'type' => 'string', 'sanitize' => 'sanitize_text_field' ],
			'_appcon_emp_contact_email' => [ 'type' => 'string', 'sanitize' => 'sanitize_email' ],
			'_appcon_emp_ukprn'         => [ 'type' => 'integer' ],
		] as $key => $args ) {
			register_post_meta( 'appcon_employer', $key, [
				'type'              => $args['type'],
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $args['sanitize'] ?? null,
			] );
		}
	}
}
