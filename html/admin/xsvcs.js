/**
 * Javascript for external login services admin.
 * Author: Patrick Lai
 *
 * @todo Localization of text.
 * @copyright Copyright (c) 2020 Patrick Lai
 */
var pl2010_XLoginApi;

jQuery(document).ready(function() {
	const idXloginSvcs = 'pl2010-xlogin-xsvcs';

	new Vue({
		el: '#' + idXloginSvcs,
		data: {
			svc: null,
			xsvcs: []
		},
		mounted() {
			pl2010_XLoginApi.get('/xsvcs').done(resp => {
				let xslist = resp.data;
				if (!xslist || typeof xslist != 'object')
					return;
				let n;
				for (n in xslist) {
					this.xsvcs.push(xslist[n]);
				}
			});
		},
		methods: {
			/**
			 * Configure external login service.
			 */
			configSvc(xs) {
				pl2010_XLoginApi.get('/xsvcs/'+xs.type+'/config').done(resp => {
					xs.data = this.dataMarshalIn(xs, resp.data);
					if (xs.data)
						this.svc = xs;
				});
			},

			/**
			 * Marshal configuration data in.
			 */
			dataMarshalIn(xs, data) {
				if (!data || typeof data != 'object') {
					data = {
						config: {}
					};
				}

				let cfg = data.config;

				let marshalled = data;

				// Expect an object for config. Handle JSON string
				// as fallback.
				if (typeof cfg != 'object') {
					try {
						cfg = cfg ? JSON.parse(cfg) : {};
					}
					catch (err) {
						cfg = null;
					}
				}

				switch (xs.model) {
				case 'oauth2':
					cfg = cfg || {};
					break;
				case 'generic':
				default:
					cfg = cfg || '';
					if (typeof cfg == 'object')
						cfg = JSON.stringify(cfg, null, '  ');
					break;
				}

				marshalled = jQuery.extend({}, data);
				marshalled.config = cfg;
				return marshalled;
			},

			/**
			 * Marshal configuration data out.
			 */
			dataMarshalOut(xs, data) {
				if (!data || typeof data != 'object')
					return null;

				let cfg = data.config;
				if (cfg === undefined || cfg === null || typeof cfg == 'object')
					return data;

				try {
					cfg = JSON.parse(cfg);
					if (typeof cfg != 'object')
						cfg = null;
				}
				catch (err) {
					cfg = null;
				}

				let marshalled = jQuery.extend({}, data);
				marshalled.config = cfg;
				return marshalled;
			},

			/**
			 * Update service configuration.
			 */
			updateSvcConfig(xs) {
				if (!xs)
					return;

				pl2010_XLoginApi.post('/xsvcs/'+xs.type+'/config', {
					data: this.dataMarshalOut(xs, xs.data)
				}).done(resp => {
					alert(xs.name + ' updated successfully.');
					xs.data = this.dataMarshalIn(xs, resp.data);
				});
			}
		}
	});
});

//----------------------------------------------------------------------
// vim: set ts=4 noexpandtab fdm=marker syntax=javascript: ('zR' to unfold all)