<?php
namespace ApprenticeshipConnector\Taxonomies;

class LevelTaxonomy {
	public static function register(): void {
		register_taxonomy( 'appcon_level', 'appcon_vacancy', [
			'labels'            => [
				'name'          => __( 'Levels', 'apprenticeship-connector' ),
				'singular_name' => __( 'Level',  'apprenticeship-connector' ),
			],
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => [ 'slug' => 'level' ],
		] );

		register_term_meta( 'appcon_level', 'level_code', [
			'type'         => 'integer',
			'description'  => 'Level code (2-7)',
			'single'       => true,
			'show_in_rest' => true,
		] );
	}
}
