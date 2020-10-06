/**
 * Javascript helper for XLogin admin.
 * Author: Patrick Lai
 *
 * @todo Localization of text.
 * @copyright Copyright (c) 2020 Patrick Lai
 */

export default new (function($, wpApiSettings) {
	const baseUrl = wpApiSettings.root + 'pl2010/xlogin/v1';

	/**
 	* Make DELETE API call.
 	*/
	this.delete = (url, params) => {
		return this.ajaxWithJsonBody('DELETE', url, params);
	};

	/**
	 * Make GET API call.
	 */
	this.get = (url, params) => {
		url = baseUrl + url;
		return $.ajax({
			url: url,
			method: 'GET',
			data: params || {},
			beforeSend: function(xhr) {
				xhr.setRequestHeader(
					'X-WP-Nonce', wpApiSettings.nonce);
			}
		}).fail((xhr, status, thrown) => {
			this.handleAjaxFailure(xhr, status, thrown);
		});
	};

	/**
	 * Make POST API call.
	 */
	this.post = (url, params) => {
		return this.ajaxWithJsonBody('POST', url, params);
	};

	/**
	 * Make PUT API call.
	 */
	this.put = (url, params) => {
		return this.ajaxWithJsonBody('PUT', url, params);
	};

	/**
	 * Make AJAX call with FormData.
	 */
	this.ajaxWithFormData = (method, url, form, success, error) => {
		let req = {
			url: baseUrl + url,
			method: method,
			data: form,
			processData: false,
			contentType: false,
			dataType: 'json',
		//	contentType: 'multipart/form-data',
			beforeSend: function(xhr) {
				xhr.setRequestHeader(
					'X-WP-Nonce', wpApiSettings.nonce);
			},
			success: success || function() {},
			error: error || function() {},
		};
		return $.ajax(req).fail((xhr, status, thrown) => {
			this.handleAjaxFailure(xhr, status, thrown);
		});
	};

	/**
	 * Make AJAX call with JSON body.
	 */
	this.ajaxWithJsonBody = (method, url, params) => {
		let req = {
			url: baseUrl + url,
			method: method,
			beforeSend: function(xhr) {
				xhr.setRequestHeader(
					'X-WP-Nonce', wpApiSettings.nonce);
			}
		};
		if (params) {
			req.contentType = 'application/json';
			req.data = JSON.stringify(params);
		}
		return $.ajax(req).fail((xhr, status, thrown) => {
			this.handleAjaxFailure(xhr, status, thrown);
		});
	};

	/**
	 * Handle AJAX failure.
	 */
	this.handleAjaxFailure = (xhr, status, thrown) => {
		let resp = xhr.responseJSON
			? xhr.responseJSON
			: (xhr.responseType == 'json' ? xhr.response : null);
		if (resp) {
			if (resp.error) {
				if (resp.error_description)
					alert(resp.error_description+' ['+resp.error+']');
				else
					alert('Error: ' + resp.error);
				return;
			}
			if (resp.code) {
				if (resp.message)
					alert(resp.message + ' [' + resp.code + ']');
				else
					alert('Error: ' + resp.code);
				return;
			}
		}

		alert('Error: ' + status);
	};
})(jQuery, wpApiSettings);

//----------------------------------------------------------------------
// vim: set ts=4 noexpandtab fdm=marker syntax=javascript: ('zR' to unfold all)
