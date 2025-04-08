/**
 * Webpack configuration for One Patch Security plugin assets.
 *
 * Handles the processing and optimization of all JavaScript and SCSS/CSS assets.
 * Compiles, minifies, and outputs production-ready assets to the 'client' directory.
 *
 * @file
 *
 * Configuration Overview:
 * - Entry Points:
 *   - script.js: Main JavaScript functionality
 *   - settings-page.scss: Admin settings page styles
 * - Output:
 *   - JavaScript: /client/js/[name].min.js
 *   - CSS: /client/css/styles.min.css
 * - Processing:
 *   - SCSS → CSS (with autoprefixing)
 *   - ES6+ → Browser-compatible JS
 *   - Minification for both JS and CSS
 * - Optimization:
 *   - CSS minimization via css-minimizer-webpack-plugin
 *   - JS minification via terser-webpack-plugin
 *
 * @package OnePatch
 * @since 1.0.0
 * @version 1.0.0
 */

const path                 = require( 'path' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const CssMinimizerPlugin   = require( 'css-minimizer-webpack-plugin' );
const TerserPlugin         = require( 'terser-webpack-plugin' );

module.exports = {
	entry: {

		script: './assets/js/script.js',

		styles: './assets/scss/settings-page.scss',
	},
	output: {
		path: path.resolve( __dirname, 'client' ),
		filename: 'js/[name].min.js',
	},
	module: {
		rules: [
			{
				test: /\.scss$/,
				use: [
					MiniCssExtractPlugin.loader,
					'css-loader',
					'sass-loader',
				],
		},
			{
				test: /\.js$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: ['@babel/preset-env'],
					},
				},
		},
		],
	},
	plugins: [
		new MiniCssExtractPlugin(
			{
				filename: 'css/styles.min.css',
			}
		),
	],
optimization: {
	minimizer: [
		new CssMinimizerPlugin(),
		new TerserPlugin(),
	],
	},
	mode: 'production',
};
