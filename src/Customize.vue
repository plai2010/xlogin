<!--
 * XLogin customization component.
 * Author: Patrick Lai
 *
 * @todo Localization.
 * @copyright Copyright (c) 2020 Patrick Lai
-->
<template>
<div>
	<!-- Modal dialog to customize. {{{-->
	<pl2010-modal v-if="cust !== null" @close="cust=null">
		<span slot="title">Customization</span>
		<div slot="body">
			<hr>
			<table>
			<tr>
				<td>Info after login buttons:</td>
				<td>
					<input type="text" v-model="cust.login_buttons_info">
				</td>
			</tr>
			<tr>
				<td>Facebook Graph API version:</td>
				<td>
					<input type="text" v-model="cust.facebook_graph_api"
						placeholder="E.g. v3.3"
					>
				</td>
			</tr>
			</table>
		</div>
		<div slot="footer">
			<button type="button" @click="cust=null">Cancel</button>
			&nbsp;
			<button type="button" @click="saveCust()">Update</button>
		</div>
	</pl2010-modal> <!--}}}-->
	<p>
	<button type="button" @click="loadCust()">Configure</button>
	</p>
</div>
</template>

<!--=================================================================-->
<script>
import xloginApi from './XLoginApi.js';

export default {
	data: () => ({
		cust: null
	}),
	created() {
		window.addEventListener('keyup', e => {
			if (!this.cust)
				return;
			if (e.key == 'Escape') {
				this.cust = null;
			}
		});
	},
	methods: {
		/**
		 * Load customization configuration for editing.
		 */
		loadCust() {
			xloginApi.get('/customize').done(resp => {
				this.cust = resp.data || {}
			});
		},

		saveCust() {
			xloginApi.post('/customize', {
				data: this.cust
			}).done(resp => {
				if (resp.data) {
					alert('Customization updated.');
					this.cust = resp.data || {};
				}
			});
		}
	}
}
</script>

<!-- vim: set ts=4 noexpandtab fdm=marker syntax=html: ('zR' to unfold all) -->
