<!--
 * Vue component external login services admin.
 * Author: Patrick Lai
 *
 * @todo Localization of text.
 * @copyright Copyright (c) 2020 Patrick Lai
-->
<template>
<div>
	<!-- Modal dialog to configure external service. {{{-->
	<pl2010-modal v-if="svc" @close="svc=null">
		<span slot="title">Configure Login Service: {{svc.name}}</span>
		<div slot="body">
			<hr>
			<table>
			<tr>
				<td>Enabled:</td>
				<td>
					<input type="checkbox" v-model="svc.data.enabled">
				</td>
			</tr>
			<tr>
				<td>Restricted:</td>
				<td>
					<input type="checkbox" v-model="svc.data.restricted">
					<span v-if="svc.data.restricted" class="description">
						Only for users with external aliases.
					</span>
					<span v-if="!svc.data.restricted" class="description">
						For all users, not just those with external aliases.
					</span>
				</td>
			</tr>
			<tr>
				<td>Import profile:</td>
				<td>
					<input type="checkbox" v-model="svc.data.override">
					<span v-if="svc.data.override" class="description">
						Import name, email, etc. into session.
					</span>
					<span v-if="!svc.data.override" class="description">
						No import from external profile.
					</span>
				</td>
			</tr>
			<tr>
				<td>Guest user:</td>
				<td>
					<input type="text" v-model="svc.data.guest"
						placeholder="Guest user for unknown aliases"
					>
					<button type="button"
						@click="checkGuestUser()"
						:disabled="guestUserNotSpecified"
					>Check</button>
				</td>
			</tr>
			<template v-if="svc.model=='oauth2'">
			<tr>
				<td>Client ID:</td>
				<td>
					<input type="text" v-model="svc.data.config.client_id"
						placeholder="OAuth2 client ID"
					>
				</td>
			</tr>
			<tr>
				<td>Client secret:</td>
				<td>
					<input type="text" v-model="svc.data.config.client_secret"
						placeholder="OAuth2 client secret"
					>
				</td>
			</tr>
			<tr>
				<td>Custom scope:</td>
				<td>
					<input type="text" v-model="svc.data.config.scope"
						placeholder="scope1 scope2 ..."
					>
				</td>
			</tr>
			</template>
			<template v-else>
			<tr>
				<td>Configuration:</td>
				<td>
					<textarea v-model="svc.data.config" rows="5"></textarea>
				</td>
			</tr>
			</template>
			</table>
			<p v-if="svc.redir">
			Redirect URI: {{svc.redir}}
			</p>
		</div>
		<div slot="footer">
			<button type="button" @click="svc=null">Cancel</button>
			&nbsp;
			<button type="button"
				@click="updateSvcConfig(svc)"
				:disabled="incompleteSvcConfig"
			>Update</button>
		</div>
	</pl2010-modal> <!--}}}-->
	<p>
	Configure:
	<template v-for="x in xsvcs">
		&nbsp;
		<button type="button" v-bind:data-xtype="x.type" @click="configSvc(x)">
			<img v-if="x.icon" v-bind:src="x.icon">
			{{x.name}}
		</button>
	</template>
	</p>
</div>
</template>

<!--=================================================================-->
<script>
import xloginApi from './XLoginApi.js';

export default {
	data: () => ({
		svc: null,
		xsvcs: []
	}),
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
		xloginApi.get('/xsvcs').done(resp => {
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
			xloginApi.post('/admin', {
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
			xloginApi.get('/xsvcs/'+xs.type+'/config').done(resp => {
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

			xloginApi.post('/xsvcs/'+xs.type+'/config', {
				data: this.dataMarshalOut(xs, xs.data)
			}).done(resp => {
				alert(xs.name + ' updated successfully.');
				xs.data = this.dataMarshalIn(xs, resp.data);
			});
		}
	}
}
</script>

<!-- vim: set ts=4 noexpandtab fdm=marker syntax=html: ('zR' to unfold all) -->
