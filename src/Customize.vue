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

<!-- vim: set ts=4 noexpandtab fdm=marker syntax=html: ('zR' to unfold all) -->
