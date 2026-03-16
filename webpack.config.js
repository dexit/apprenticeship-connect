/**
 * Apprenticeship Connector – Webpack config.
 *
 * Extends @wordpress/scripts defaults with a single admin entry point
 * that outputs to build/admin/ (consumed by AdminLoader.php).
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path          = require( 'path' );

module.exports = {
	...defaultConfig,

	entry: {
		// Single admin bundle (App.jsx mounts the React tree)
		'admin/index': path.resolve( process.cwd(), 'src/admin', 'App.jsx' ),
	},

	output: {
		...defaultConfig.output,
		path:     path.resolve( process.cwd(), 'build' ),
		filename: '[name].js',
	},
};
