// Copyright (c) 2020 Patrick Lai. All rights reserved.

const path = require('path');
const VueLoaderPlugin = require('vue-loader/lib/plugin');

module.exports = (env, argv) => ({
	mode: argv.mode || 'production',
	entry: './admin.js',
	output: {
		path: path.resolve(__dirname, '../js'),
		filename: 'settings.js',
	},
	module: {
		rules: [
			{
				test: /\.vue$/,
				loader: 'vue-loader',
			},
		],
	},
	plugins: [
		new VueLoaderPlugin(),
	],
	resolve: {
		alias: {
			'vue': argv.mode!='development'
				? 'vue/dist/vue.min.js'
				: 'vue/dist/vue.js',
		},
	},
});

// vim: set ts=4 noexpandtab fdm=marker:
