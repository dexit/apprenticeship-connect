/**
 * WordPress Dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

/**
 * Custom webpack configuration
 *
 * This extends @wordpress/scripts default config to support multiple entry points
 * for different admin pages and frontend components.
 */
module.exports = {
	...defaultConfig,
	entry: {
		// Admin scripts
		'admin': path.resolve(process.cwd(), 'src/admin', 'index.js'),
		'dashboard': path.resolve(process.cwd(), 'src/admin', 'dashboard.js'),
		'settings': path.resolve(process.cwd(), 'src/admin', 'settings.js'),
		'import-wizard': path.resolve(process.cwd(), 'src/admin/import-wizard', 'index.js'),
		'meta-box': path.resolve(process.cwd(), 'src/admin/meta-box', 'index.js'),

		// Frontend scripts
		'frontend': path.resolve(process.cwd(), 'src/frontend', 'index.js'),

		// Styles
		'admin-style': path.resolve(process.cwd(), 'src/admin', 'style.scss'),
		'frontend-style': path.resolve(process.cwd(), 'src/frontend', 'style.scss'),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(process.cwd(), 'assets/build'),
		filename: '[name].js',
	},
};
