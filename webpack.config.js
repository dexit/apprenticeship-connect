/**
 * Minimal webpack configuration for Apprenticeship Connect
 *
 * This plugin uses traditional PHP admin pages with inline JavaScript,
 * so webpack doesn't need to build anything.
 */
const path = require('path');

module.exports = {
	mode: 'production',
	entry: {},
	output: {
		path: path.resolve(process.cwd(), 'assets/build'),
		filename: '[name].js',
	},
	module: {
		rules: [
			{
				test: /\.scss$/,
				use: [
					'style-loader',
					'css-loader',
					'sass-loader',
				],
			},
		],
	},
};
