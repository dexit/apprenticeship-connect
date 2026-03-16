<?php
namespace ApprenticeshipConnector\Taxonomies;

class LARSTaxonomy {
	public static function register(): void {
		register_taxonomy( 'appcon_lars_code', 'appcon_vacancy', [
			'labels'            => [
				'name'          => __( 'LARS Codes', 'apprenticeship-connector' ),
				'singular_name' => __( 'LARS Code',  'apprenticeship-connector' ),
			],
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => [ 'slug' => 'lars-code' ],
		] );
	}
}
