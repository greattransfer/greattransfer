const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' ); // eslint-disable-line import/no-extraneous-dependencies
const { basename, resolve } = require( 'path' );
const { sync: glob } = require( 'fast-glob' ); // eslint-disable-line import/no-extraneous-dependencies

const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const postcssPlugins = require( '@wordpress/postcss-plugins-preset' );

const {
	hasCssnanoConfig,
	hasPostCSSConfig,
	fromProjectRoot,
} = require( '@wordpress/scripts/utils' );

const isProduction = process.env.NODE_ENV === 'production';

const cssLoaders = [
	{
		loader: require.resolve( 'postcss-loader' ),
		options: {
			// Provide a fallback configuration if there's not
			// one explicitly available in the project.
			...( ! hasPostCSSConfig() && {
				postcssOptions: {
					ident: 'postcss',
					sourceMap: ! isProduction,
					plugins: isProduction
						? [
								...postcssPlugins,
								// eslint-disable-next-line import/no-extraneous-dependencies
								require( 'cssnano' )( {
									// Provide a fallback configuration if there's not
									// one explicitly available in the project.
									...( ! hasCssnanoConfig() && {
										preset: [
											'default',
											{
												discardComments: {
													removeAll: true,
												},
											},
										],
									} ),
								} ),
						  ]
						: postcssPlugins,
				},
			} ),
		},
	},
];

const entry = glob( '**/*.{js,scss}', {
	absolute: true,
	cwd: fromProjectRoot( 'assets' ),
} )
	.filter( ( filePath ) => {
		const inJs = filePath.replace(
			new RegExp(
				`^${ fromProjectRoot( 'assets/js/' ).replace( /\//g, '\\/' ) }`
			),
			''
		);
		return (
			! filePath.endsWith( '.min.js' ) &&
			! filePath.endsWith( '.min.js.map' ) &&
			! basename( filePath ).startsWith( '_' ) &&
			! inJs.startsWith( 'selectWoo' )
		);
	} )
	.reduce( ( acc, filePath ) => {
		const entryName = filePath
			.replace( /\.(js|scss)$/, '' )
			.replace(
				new RegExp(
					`^${ fromProjectRoot( 'assets/' ).replace( /\//g, '\\/' ) }`
				),
				''
			);
		acc[ entryName ] = resolve( process.cwd(), filePath );
		return acc;
	}, {} );

module.exports = {
	...defaultConfig,
	entry,
	output: {
		filename: '[name].min.js',
		path: resolve( process.cwd(), 'assets' ),
	},
	module: {
		rules: [
			...defaultConfig.module.rules.filter(
				( rule ) =>
					! (
						rule.test instanceof RegExp &&
						rule.test.toString() === /\.(sc|sa)ss$/.toString()
					)
			),
			{
				test: /\.s[ac]ss$/i,
				type: 'asset/resource',
				generator: {
					filename: 'css/[name].css',
				},
				use: [
					...cssLoaders,
					{
						loader: require.resolve( 'sass-loader' ),
						options: {
							sourceMap: ! isProduction,
						},
					},
				],
			},
		],
	},
	plugins: [
		...defaultConfig.plugins.filter( ( plugin ) => {
			return ! (
				plugin instanceof DependencyExtractionWebpackPlugin ||
				plugin instanceof CleanWebpackPlugin
			);
		} ),
		new CleanWebpackPlugin( {
			cleanOnceBeforeBuildPatterns: [
				'**/*.css',
				'**/*.min.js',
				'!**/selectWoo.full.min.js',
			],
			cleanStaleWebpackAssets: false,
		} ),
	],
};
