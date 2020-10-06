/**
 * Javascript for external users admin.
 * Author: Patrick Lai
 *
 * @todo Localization of text.
 * @copyright Copyright (c) 2020 Patrick Lai
 */
var pl2010_XLoginApi;

jQuery(document).ready(function() {
	const idXloginUsers = 'pl2010-xlogin-xusers';

	new Vue({
		el: '#' + idXloginUsers,
		data: {
			filter: {
				user: null,
				hint: null,
				alias: {
					type: 'email',
					name: null
				}
			},
			modal: {
				add: false,
				upload: false
			},
			newxu: {
				user: null,
				hint: true,
				alias: {
					type: 'email',
					name: null
				}
			},
			pg: {
				offset: 0,
				limit: 10,
				total: null
			},
			upload: {
				file: null,
				hint: true,
				incr: true,
				user: null
			},
			xusers: []
		},
		created() {
			window.addEventListener('keyup', e => {
				if (!this.modal.add && !this.modal.upload)
					return;
				if (e.key == 'Escape') {
					this.modal.add = false;
					this.modal.upload = false;
				}
			});
		},
		mounted() {
			this.loadXUsers();
		},
		computed: {
			atPgFirst: function() {
				return this.pg.offset == 0 ? true : null;
			},
			incompleteNewXUser: function() {
				let alias = this.newxu.alias;
				if (!alias.type || alias.type.trim() == '')
					return true;
				switch (alias.type) {
				case 'email':
					// TODO: better email address checking
					if (!alias.name || alias.name.trim() == '')
						return true;
					break;
				default:
					break;
				}

				if (!this.newxu.user || this.newxu.user.trim() == '')
					return true;

				return null;
			},
			incompleteXUsersUpload: function() {
				if (!this.upload.file)
					return true;
				return null;
			},
			noAliasFilter: function() {
				let alias = this.filter.alias;
				if (!alias.type || alias.type.trim() == '')
					return true;
				switch (alias.type) {
				case 'email':
					// TODO: better email address checking
					if (!alias.name || alias.name.trim() == '')
						return true;
					break;
				default:
					break;
				}
				return null;
			},
			noPgLast: function() {
				// Can't go to last page if total unknown.
				if (this.pg.total === null)
					return true;
				// Already at last page?
				return this.pg.offset + this.pg.limit >= this.pg.total
					? true
					: null;
			},
			noPgPrev: function() {
				return this.pg.offset == 0 ? true : null;
			},
			noPgNext: function() {
				// Allow next page if total unknown.
				if (this.pg.total === null)
					return null;
				// Already at last page?
				return this.pg.offset + this.pg.limit >= this.pg.total
					? true
					: null;
			},
			noHintFilter: function() {
				if (!this.filter.hint || this.filter.hint.trim() == '')
					return true;
				return null;
			},
			noUserFilter: function() {
				if (!this.filter.user || this.filter.user.trim() == '')
					return true;
				return null;
			},
			pageNum: function() {
				return Math.floor(this.pg.offset / this.pg.limit) + 1;
			}
		},
		methods: {
			/**
			 * Add a new external user.
			 */
			addXUser() {
				let type = this.newxu.alias.type = this.newxu.alias.type.trim();
				let name = this.newxu.alias.name = this.newxu.alias.name.trim();
				let hint = this.newxu.hint;
				let user = this.newxu.user = this.newxu.user.trim();

				pl2010_XLoginApi.post('/xusers', {
					data: {
						alias: type + ':' + name,
						hint: hint,
						login: user,
					}
				}).done(resp => {
					if (!resp.data)
						return;

					alert("External name '" + name + "' (" + type + ")"
						+ " added for user '" + resp.data.user + "'.");
					this.loadXUsers();
				//	this.modal.add = false;
				});
			},

			/**
			 * Clear all filters and reload.
			 */
			clearFilterAndReload() {
				this.clearXUserBuf(this.filter);
				this.setPgFirst();
				this.loadXUsers();
			},

			/**
			 * Clear external user buffer.
			 */
			clearXUserBuf(xu) {
				xu.user = null;
				xu.hint = null;
				xu.alias.type = 'email';
				xu.alias.name = null;
			},

			/**
			 * Delete an external user.
			 */
			delXUser(xu) {
				if (!xu || !xu.id)
					return;
				let prompt = 'Delete external alias '
					+ (xu.hint && xu.hint != ''
						? "'" + xu.hint + "'"
						: '#' + xu.id
					)
					+ " of user '" + xu.user + "'?";
				if (!confirm(prompt))
					return;
				pl2010_XLoginApi.delete('/xusers/'+xu.id).done(resp => {
					if (!resp.success) {
						alert('Failed to delete!');
						return;
					}
					this.loadXUsers();
				});
			},

			/**
			 * Filter external users by alias.
			 */
			filterByAlias() {
				this.filter.user = null;
				this.filter.hint = null;
				this.filter.alias.type = this.filter.alias.type.trim();
				this.filter.alias.name = this.filter.alias.name.trim();
				this.setPgFirst();
				this.loadXUsers();
			},

			/**
			 * Filter external users by WordPress user.
			 */
			filterByUser() {
				this.filter.alias.name = null;
				this.filter.user = this.filter.user.trim();
				this.setPgFirst();
				this.loadXUsers();
			},

			/**
			 * Filter external aliases by hint.
			 */
			filterByHint() {
				this.filter.alias.name = null;
				this.filter.hint = this.filter.hint.trim();
				this.setPgFirst();
				this.loadXUsers();
			},

			/**
			 * Goto first page.
			 */
			goPgFirst() {
				this.setPgFirst();
				this.loadXUsers();
			},

			/**
			 * Goto last page.
			 */
			goPgLast() {
				this.setPgLast();
				this.loadXUsers();
			},

			/**
			 * Goto next page.
			 */
			goPgNext() {
				this.setPgNext();
				this.loadXUsers();
			},

			/**
			 * Goto previous page.
			 */
			goPgPrev() {
				this.setPgPrev();
				this.loadXUsers();
			},

			/**
			 * Handle API failure.
			 */
			handleApiFailure(xhr, status, thrown) {
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
			},

			/**
			 * Load external users, given current filter, pagination, etc.
			 */
			loadXUsers() {
				if (this.filter.alias.type && this.filter.alias.name) {
					this.setPgFirst();

					let alias = this.filter.alias.type
						+ ':'
						+ this.filter.alias.name;
					pl2010_XLoginApi.get('/xusers/alias/'+alias).done(resp => {
						this.xusers = [];
						this.pg.total = 0;
						if (resp.data) {
							this.xusers.push(resp.data);
							this.pg.total = 1;
						}
					});
				}
				else {
					let params = {
						offset: this.pg.offset,
						limit: this.pg.limit
					};
					if (this.filter.user)
						params.login = this.filter.user;
					if (this.filter.hint)
						params.alias = this.filter.hint;

					pl2010_XLoginApi.get('/xusers', params).done(resp => {
						this.xusers = [];
						(resp.data || []).forEach(xu => {
							this.xusers.push(xu);
						});
						if (resp.total)
							this.pg.total = resp.total;
						if (resp.offset)
							this.pg.offset = resp.offset;
					});
				}
			},

			/**
			 * Reload current page.
			 */
			pgReload() {
				this.loadXUsers();
			},

			/**
			 * Set pagination to first page.
			 */
			setPgFirst() {
				this.pg.offset = 0;
				this.pg.total = null;
			},

			/**
			 * Set pagination to last page.
			 */
			setPgLast() {
				if (this.pg.total === null)
					return;

				if (this.pg.total == 0)
					this.pg.offset = 0;
				else {
					let npages = Math.ceil(this.pg.total / this.pg.limit);
					this.pg.offset = this.pg.limit * (npages - 1);
				}
			},

			/**
			 * Set pagination to next page.
			 */
			setPgNext() {
				this.pg.offset += this.pg.limit;
			},

			/**
			 * Set pagination to previous page.
			 */
			setPgPrev() {
				this.pg.offset -= this.pg.limit;
				if (this.pg.offset < 0)
					this.pg.offset = 0;
			},

			/**
			 * Upload external users from file.
			 */
			uploadXUsers(modalId) {
				let file = this.upload.file;
				let hint = this.upload.hint;
				let incr = this.upload.incr;
				let user = (this.upload.user || '').trim();

				if (!file)
					return;

				if (!incr && !confirm(
					'Reset all external aliases with upload?'
				)) {
					return;
				}

				let formData = new FormData();
				formData.append('file', file, file.name);
				formData.append('hint', hint);
				formData.append('incr', incr);
				formData.append('user', user);

				pl2010_XLoginApi.ajaxWithFormData(
					'POST',
					'/xusers/upload',
					formData,
					(resp, status, xhr) => {
						if (!resp.data)
							return;
						let data = resp.data;
						let msg = "Upload finished:"
							+ "\n    Success: " + data.success;
						if (data.failure)
							msg += "\n    Failure: " + data.failure;
						if (data.skipped)
							msg += "\n    Skipped: " + data.skipped;
						alert(msg);
						if (!incr)
							this.setPgFirst();
						this.loadXUsers();
					//	this.modal.upload = false;
					}
				);
			}
		}
	});
});

//----------------------------------------------------------------------
// vim: set ts=4 noexpandtab fdm=marker syntax=javascript: ('zR' to unfold all)
