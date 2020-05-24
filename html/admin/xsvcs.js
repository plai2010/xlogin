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
		created() {
			window.addEventListener('keyup', e => {
				if (!this.svc)
					return;
				if (e.key == 'Escape') {
					this.svc = null;
				}
			});
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
		computed: {
			guestUserNotSpecified: function() {
				let xs = this.svc;
				if (!xs || !xs.data || !xs.data.guest)
					return true;
				if (xs.data.guest.trim().length == 0)
					return true;
				return null;
			},
			incompleteSvcConfig: function() {
				let xs = this.svc;
				if (!xs || !xs.data || !xs.data.config)
					return true;
				let cfg = xs.data.config;
				if (!cfg)
					return true;
				switch (xs.model) {
				case 'oauth2':
					if (!cfg.client_id || cfg.client_id.trim() == '')
						return true;
					if (!cfg.client_secret || cfg.client_secret.trim() == '')
						return true;
					break;
				case 'generic':
				default:
					// Generic object as JSON.
					try {
						cfg = JSON.parse(xs.data.config);
					}
					catch (err) {
						return true;
					}
					if (!cfg || typeof cfg != 'object')
						return true;
					break;
				}
				return null;
			}
		},
		methods: {
			/**
			 * Check if a guest user is acceptable.
			 */
			checkGuestUser() {
				if (this.guestUserNotSpecified)
					return;
				let guest = this.svc.data.guest.trim();
				this.svc.data.guest = guest;
				pl2010_XLoginApi.post('/admin', {
					op: 'check-guest',
					params: {
						login: guest
					}
				}).done(resp => {
					if (this.guestUserNotSpecified)
						return;
					if (this.svc.data.guest != guest)
						return;
					let result = resp.result || {};
					if (result.success) {
						if (result.login)
							this.svc.data.guest = result.login;
						alert('Guest user acceptable.');
					}
					else {
						alert('Guest user reject: ' + result.err_msg);
					}
				});
			},

			/**
			 * Configure external login service.
			 */
			configSvc(xs) {
				pl2010_XLoginApi.get('/xsvcs/'+xs.type+'/config').done(resp => {
					xs.data = this.dataMarshalIn(xs, resp.data);
					if (xs.data) {
						this.svc = jQuery.extend({}, xs);
					}
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
