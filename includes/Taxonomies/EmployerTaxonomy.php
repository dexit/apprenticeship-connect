<?php
namespace ApprenticeshipConnector\Taxonomies;

class EmployerTaxonomy {
	public static function register(): void {
		register_taxonomy( 'appcon_employer', 'appcon_vacancy', [
			'labels'            => [
				'name'          => __( 'Employers', 'apprenticeship-connector' ),
				'singular_name' => __( 'Employer',  'apprenticeship-connector' ),
			],
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => [ 'slug' => 'employer' ],
		] );

		foreach ( [
			'employer_description'   => 'string',
			'employer_website'       => 'string',
			'employer_contact_name'  => 'string',
			'employer_contact_phone' => 'string',
			'employer_contact_email' => 'string',
		] as $key => $type ) {
			register_term_meta( 'appcon_employer', $key, [
				'type'         => $type,
				'single'       => true,
				'show_in_rest' => true,
			] );
		}
	}
}
