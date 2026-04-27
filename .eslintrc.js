module.exports = {
	root: true,
	extends: [
		'plugin:@wordpress/eslint-plugin/recommended',
		'plugin:@wordpress/eslint-plugin/i18n',
	],
	env: {
		browser: true,
		es2021: true,
		node: true,
	},
	parserOptions: {
		ecmaVersion: 2021,
		sourceType: 'module',
		ecmaFeatures: {
			jsx: true,
		},
	},
	rules: {
		// Relaxed rules for development
		'@wordpress/no-unsafe-wp-apis': 'warn',
		'jsdoc/require-param': 'off',
		'jsdoc/check-param-names': 'off',
		'jsdoc/require-returns': 'off',
		'no-console': 'warn',
		'import/no-extraneous-dependencies': 'off',

		// Enforce consistency
		'arrow-parens': ['error', 'always'],
		'comma-dangle': ['error', 'always-multiline'],
		'object-curly-spacing': ['error', 'always'],
	},
	settings: {
		'import/resolver': {
			node: {
				extensions: ['.js', '.jsx', '.json'],
			},
		},
	},
	globals: {
		wp: 'readonly',
		apprcoAjax: 'readonly',
		apprcoData: 'readonly',
	},
};
