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

<!-- vim: set ts=4 noexpandtab fdm=marker syntax=html: ('zR' to unfold all) -->
