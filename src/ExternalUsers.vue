<!--
 * Vue component external users admin.
 * Author: Patrick Lai
 *
 * @todo Localization of text.
 * @copyright Copyright (c) 2020 Patrick Lai
-->
<template>
<div>
	<!-- Modal dialog to add/update external user. {{{-->
	<pl2010-modal v-if="modal.add" @close="modal.add=false">
		<span slot="title">Add/Update External Alias</span>
		<div slot="body">
			<hr>
			<table>
			<tr>
				<td>WordPress user:</td>
				<td>
					<input type="text" v-model="newxu.user">
				</td>
			</tr>
			<tr>
				<td>Alias type:</td>
				<td>
					<select v-model="newxu.alias.type" disabled>
						<option value="email" selected>E-mail</option>
					</select>
				</td>
			</tr>
			<tr>
				<td>Alias name:</td>
				<td><input type="text" v-model="newxu.alias.name"></td>
			</tr>
			<tr>
				<td>Save obscured:</td>
				<td>
					<input type="checkbox" v-model="newxu.hint">
					<span v-if="newxu.hint" class="description">
						Save partially obscured alias.
					</span>
					<span v-if="!newxu.hint" class="description">
						Only save generated hash of alias.
					</span>
				</td>
			</tr>
			</table>
		</div>
		<div slot="footer">
			<button type="button" @click="modal.add=false">Cancel</button>
			&nbsp;
			<button type="button"
				@click="addXUser()"
				:disabled="incompleteNewXUser"
			>Submit</button>
		</div>
	</pl2010-modal> <!--}}}-->

	<!-- Modal dialog to upload external users file. {{{-->
	<pl2010-modal v-if="modal.upload" @close="modal.upload=false">
		<span slot="title">Upload External Aliases</span>
		<div slot="body">
			<hr>
			<p>Upload a CSV file with these fields:</p>
			<ol>
				<li>Email address as alias.</li>
				<li>WordPress user name.</li>
			</ol>
			<p>For example,</p>
			<pre>    john.doe@example.com,jdoe
    john.doe@yahoo.com,jdoe
    mary@gmail.com,mjane</pre>
			<p>
			Incremental upload adds new email address to user mappings
			or updates existing ones; non-incremental wipes out existing
			mappings first.
			</p>
			<p>
			When a CSV entry contains only email address, the default
			user will be used if provided.
			</p>
			<hr>
			<table>
			<tr>
				<td>CSV file:</td>
				<td>
					<input type="file"
						@change="upload.file=$event.target.files[0]"
					>
				</td>
			</tr>
			<tr>
				<td>Incremental:</td>
				<td>
					<input type="checkbox" v-model="upload.incr">
					<span v-if="upload.incr" class="description">
						Keep existing aliases.
					</span>
					<span v-if="!upload.incr" class="description">
						Existing aliases will be deleted!
					</span>
				</td>
			</tr>
			<tr>
				<td>Save obscured:</td>
				<td>
					<input type="checkbox" v-model="upload.hint">
					<span v-if="upload.hint" class="description">
						Save partially obscured alias.
					</span>
					<span v-if="!upload.hint" class="description">
						Only save generated hash of alias.
					</span>
				</td>
			</tr>
			<tr>
				<td>Default user:</td>
				<td><input type="text" v-model="upload.user"></td>
			</tr>
			</table>
		</div>
		<div slot="footer">
			<button type="button" @click="modal.upload=false">Cancel</button>
			&nbsp;
			<button type="button"
				@click="uploadXUsers()"
				:disabled="incompleteXUsersUpload"
			>Upload</button>
		</div>
	</pl2010-modal> <!--}}}-->

	<!-- List of exteranl users. {{{-->
	<table class="pl2010-xlogin-xusers">
		<tr>
			<th>ID</th>
			<th>User</th>
			<th>Alias (obscured)</th>
			<th>External Alias Hash</th>
		</tr>
		<template v-if="!xusers || xusers.length == 0">
		<tr><td colspan="3">(no result)</td></tr>
		</template>
		<tr v-for="xu in xusers">
			<td>
				<button type="button" @click="delXUser(xu)">x</button>
				{{xu.id}}
			</td>
			<td>{{xu.user}}</td>
			<td>{{xu.hint}}</td>
			<td>{{xu.hash}}</td>
		</tr>
		<tr><td colspan="4"><hr></td></tr>
		<tr>
			<td>
				<button type="button" @click="clearFilterAndReload">
					Clear
				</button>
			</td>
			<td>
				<input v-model="filter.user" type="text" size="8">
				<button type="button"
					@click="filterByUser"
					:disabled="noUserFilter"
				>Filter</button>
			</td>
			<td>
				<input v-model="filter.hint" type="text" size="10">
				<button type="button"
					@click="filterByHint"
					:disabled="noHintFilter"
				>Filter</button>
			</td>
			<td>
				<select v-model="filter.alias.type" disabled>
					<option value="email">E-mail</option>
				</select>
				<input v-model="filter.alias.name" type="text">
				<button type="button"
					@click="filterByAlias"
					:disabled="noAliasFilter"
				>Find</button>
			</td>
		</tr>
		<tr><td colspan="4"><hr></td></tr>
		<tr>
			<td colspan="2">
				<button type="button"
					@click="goPgFirst"
					:disabled="atPgFirst"
				>|&laquo;</button>
				<button type="button"
					@click="goPgPrev"
					:disabled="noPgPrev"
				>&lsaquo;</button>
				<button type="button"@click="pgReload">{{pageNum}}</button>
				<button type="button"
					@click="goPgNext"
					:disabled="noPgNext"
				>&rsaquo;</button>
				<button type="button"
					@click="goPgLast"
					:disabled="noPgLast"
				>&raquo;|</button>
			</td>
			<td>&nbsp;</td>
			<td>
				<div class="ctrl-btns">
					<button type="button"
						@click="modal.add=true"
						:disabled="modal.add"
					>Add/Update</button>
					&nbsp;
					<button type="button"
						@click="modal.upload=true"
						:disabled="modal.upload"
					>Upload</button>
				</div>
			</td>
		</tr>
	</table>
	<!--}}}-->
</div>
</template>

<!--=================================================================-->
<script>
import xloginApi from './XLoginApi.js';

export default {
	data: () => ({
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
	}),
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

			xloginApi.post('/xusers', {
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
			xloginApi.delete('/xusers/'+xu.id).done(resp => {
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
				xloginApi.get('/xusers/alias/'+alias).done(resp => {
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

				xloginApi.get('/xusers', params).done(resp => {
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

			xloginApi.ajaxWithFormData(
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
}
</script>

<!-- vim: set ts=4 noexpandtab fdm=marker syntax=html: ('zR' to unfold all) -->
