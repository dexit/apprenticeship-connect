<?php
/**
 * Server-side render for the apprco/jobs-archive block.
 *
 * $attributes is injected by WordPress from block.json attribute definitions.
 * $content    is the inner blocks HTML (unused — this is a leaf block).
 *
 * @var array  $attributes Block attributes.
 * @var string $content    Inner content (unused).
 * @var WP_Block $block    Block instance.
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'Apprco_Archive' ) ) {
	return;
}

echo Apprco_Archive::get_instance()->render( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
