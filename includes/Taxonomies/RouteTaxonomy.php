<?php
namespace ApprenticeshipConnector\Taxonomies;

class RouteTaxonomy {
	public static function register(): void {
		register_taxonomy( 'appcon_route', 'appcon_vacancy', [
			'labels'            => [
				'name'          => __( 'Routes', 'apprenticeship-connector' ),
				'singular_name' => __( 'Route',  'apprenticeship-connector' ),
			],
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => [ 'slug' => 'route' ],
		] );

		register_term_meta( 'appcon_route', 'route_description', [
			'type'         => 'string',
			'description'  => 'Route description',
			'single'       => true,
			'show_in_rest' => true,
		] );
	}
}
