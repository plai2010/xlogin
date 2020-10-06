<!--
 * XLogin customization component.
 * Author: Patrick Lai
 *
 * @todo Localization.
 * @copyright Copyright (c) 2020 Patrick Lai
-->
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
