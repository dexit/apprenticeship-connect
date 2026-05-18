/**
 * WordPress Dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

/**
 * Custom webpack configuration extending @wordpress/scripts defaults with
 * multiple entry points for admin pages and frontend components.
 */
module.exports = {
	...defaultConfig,
	entry: {
		// Admin scripts
		'admin': path.resolve(process.cwd(), 'src/admin', 'index.js'),
		'dashboard': path.resolve(process.cwd(), 'src/admin', 'dashboard.js'),
		'settings': path.resolve(process.cwd(), 'src/admin', 'settings.js'),
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
