<?php
/**
 * Provider Registry - For extensibility
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Apprco_Provider_Registry {

	private static $instance = null;
	private $providers       = array();

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Register internal UK Gov provider by default
		$this->register( 'uk-gov-apprenticeships', 'UK Government Apprenticeships API v2' );
		do_action( 'apprco_register_providers', $this );
	}

	public function register( string $id, string $name ): void {
		$this->providers[ $id ] = array(
			'id'   => $id,
			'name' => $name,
		);
	}

	public function get_all(): array {
		return $this->providers;
	}
}
