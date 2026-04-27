<?php
namespace ApprenticeshipConnector\Taxonomies;

class SkillTaxonomy {
	public static function register(): void {
		register_taxonomy( 'appcon_skill', 'appcon_vacancy', [
			'labels'            => [
				'name'          => __( 'Skills', 'apprenticeship-connector' ),
				'singular_name' => __( 'Skill',  'apprenticeship-connector' ),
			],
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => false,
			'rewrite'           => [ 'slug' => 'skill' ],
		] );
	}
}
