/**
 * Webpack configuration for One Patch Security plugin assets.
 *
 * Compiles and optimizes JavaScript and SCSS for production.
 *
 * @package OnePatch
 *
 * @since 1.0.0
 */

const path                   = require( 'path' );
const MiniCssExtractPlugin   = require( 'mini-css-extract-plugin' );
const CssMinimizerPlugin     = require( 'css-minimizer-webpack-plugin' );
const TerserPlugin           = require( 'terser-webpack-plugin' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );

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
					{
						loader: 'postcss-loader',
						options: {
							postcssOptions: {
								plugins: ['autoprefixer'],
							},
						},
				},
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
		new CleanWebpackPlugin(),

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

	devtool: 'source-map',

	resolve: {
		extensions: ['.js', '.scss'],
	},

	mode: 'production',
};
