<?php
/**
 * Internationalisation helper (Boilerplate pattern).
 *
 * @package ApprenticeshipConnector\Core
 */

namespace ApprenticeshipConnector\Core;

class I18n {

	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			'apprenticeship-connector',
			false,
			dirname( APPCON_BASENAME ) . '/languages/'
		);
	}
}
