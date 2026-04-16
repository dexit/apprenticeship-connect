<?php
/**
 * WordPress Plugin Boilerplate – Loader class.
 * Registers all hooks for the plugin.
 *
 * @package ApprenticeshipConnector\Core
 */

namespace ApprenticeshipConnector\Core;

/**
 * Maintains lists of actions and filters to register.
 */
class Loader {

	/** @var array<int, array{hook:string, component:object|string, callback:string, priority:int, accepted_args:int}> */
	private array $actions = [];

	/** @var array<int, array{hook:string, component:object|string, callback:string, priority:int, accepted_args:int}> */
	private array $filters = [];

	/**
	 * Add an action hook.
	 *
	 * @param string          $hook          Hook name.
	 * @param object|string   $component     Class instance or class name.
	 * @param string          $callback      Method name.
	 * @param int             $priority      Priority.
	 * @param int             $accepted_args Number of accepted arguments.
	 */
	public function add_action(
		string $hook,
		object|string $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a filter hook.
	 */
	public function add_filter(
		string $hook,
		object|string $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Register all collected hooks with WordPress.
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				[ $hook['component'], $hook['callback'] ],
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			// Support static methods stored as "ClassName::method"
			if ( is_string( $hook['component'] ) && str_contains( $hook['component'], '::' ) ) {
				add_action( $hook['hook'], $hook['component'], $hook['priority'], $hook['accepted_args'] );
			} else {
				add_action(
					$hook['hook'],
					[ $hook['component'], $hook['callback'] ],
					$hook['priority'],
					$hook['accepted_args']
				);
			}
		}
	}

	// ── Private helpers ───────────────────────────────────────────────────

	private function add(
		array $hooks,
		string $hook,
		object|string $component,
		string $callback,
		int $priority,
		int $accepted_args
	): array {
		$hooks[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
		return $hooks;
	}
}
