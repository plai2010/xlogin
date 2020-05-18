/**
 * Javascript for external login customization.
 * Author: Patrick Lai
 *
 * @todo Localization of text.
 * @copyright Copyright (c) 2020 Patrick Lai
 */
var pl2010_XLoginApi;

jQuery(document).ready(function() {
	const idXloginCustomize = 'pl2010-xlogin-customize';

	new Vue({
		el: '#' + idXloginCustomize,
		data: {
			cust: null
		},
		methods: {
			/**
			 * Load customization configuration for editing.
			 */
			loadCust() {
				pl2010_XLoginApi.get('/customize').done(resp => {
					this.cust = resp.data || {}
				});
			},
			saveCust() {
				pl2010_XLoginApi.post('/customize', {
					data: this.cust
				}).done(resp => {
					if (resp.data) {
						alert('Customization updated.');
						this.cust = resp.data || {};
					}
				});
			}
		}
	});
});

//----------------------------------------------------------------------
// vim: set ts=4 noexpandtab fdm=marker syntax=javascript: ('zR' to unfold all)
