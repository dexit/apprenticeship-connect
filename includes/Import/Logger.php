<?php
/**
 * Import-run logger – writes to wp_appcon_import_logs.
 *
 * @package ApprenticeshipConnector\Import
 */

namespace ApprenticeshipConnector\Import;

use ApprenticeshipConnector\Core\Database;

class Logger {

	public function __construct( private readonly string $run_id ) {}

	public function trace( string $message, array $meta = [] ): void    { $this->log( 'trace',    $message, null, $meta ); }
	public function debug( string $message, array $meta = [] ): void    { $this->log( 'debug',    $message, null, $meta ); }
	public function info( string $message, array $meta = [] ): void     { $this->log( 'info',     $message, null, $meta ); }
	public function warning( string $message, array $meta = [] ): void  { $this->log( 'warning',  $message, null, $meta ); }
	public function error( string $message, array $meta = [] ): void    { $this->log( 'error',    $message, null, $meta ); }
	public function critical( string $message, array $meta = [] ): void { $this->log( 'critical', $message, null, $meta ); }

	public function log( string $level, string $message, ?string $context = null, array $meta = [] ): void {
		global $wpdb;

		$wpdb->insert(
			Database::get_logs_table(),
			[
				'run_id'    => $this->run_id,
				'log_level' => $level,
				'message'   => $message,
				'context'   => $context,
				'meta_data' => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
			],
			[ '%s', '%s', '%s', '%s', '%s' ]
		);
	}
}
