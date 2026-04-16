<?php
/**
 * Plugin settings manager.
 *
 * @package ApprenticeshipConnector\Core
 */

namespace ApprenticeshipConnector\Core;

class Settings {

	private const OPTION_KEY = 'appcon_settings';

	private static array $cache = [];

	/**
	 * Get a setting value.
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		if ( empty( self::$cache ) ) {
			self::$cache = (array) get_option( self::OPTION_KEY, [] );
		}
		return self::$cache[ $key ] ?? $default;
	}

	/**
	 * Update a single setting.
	 */
	public static function set( string $key, mixed $value ): void {
		if ( empty( self::$cache ) ) {
			self::$cache = (array) get_option( self::OPTION_KEY, [] );
		}
		self::$cache[ $key ] = $value;
		update_option( self::OPTION_KEY, self::$cache );
	}

	/**
	 * Replace all settings at once.
	 */
	public static function update_all( array $settings ): void {
		self::$cache = $settings;
		update_option( self::OPTION_KEY, $settings );
	}

	/**
	 * Return all settings.
	 */
	public static function all(): array {
		if ( empty( self::$cache ) ) {
			self::$cache = (array) get_option( self::OPTION_KEY, [] );
		}
		return self::$cache;
	}
}
