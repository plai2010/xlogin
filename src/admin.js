/**
 * XLogin admin application.
 * copyright (c) 2020 Patrick Lai
 */
import Vue from 'vue';

// Vue.js components.
const Modal = Vue.component(
	'pl2010-modal',
	require('./Modal.vue').default
);
const XLoginCustomize = Vue.component(
	'pl2010-xlogin-customize',
	require('./Customize.vue').default
);
const XLoginXSvcs = Vue.component(
	'pl2010-xlogin-xsvcs',
	require('./ExternalSvcs.vue').default
);
const XLoginXUsers = Vue.component(
	'pl2010-xlogin-xusers',
	require('./ExternalUsers.vue').default
);

jQuery(document).ready(() => {
	new Vue({ el: '#pl2010-xlogin-customize' });
	new Vue({ el: '#pl2010-xlogin-xsvcs' });
	new Vue({ el: '#pl2010-xlogin-xusers' });
});

// vim: set ts=4 noexpandtab fdm=marker:
