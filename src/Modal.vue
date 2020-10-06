<!--
 * Modal dialog component.
 * Author: Patrick Lai
 *
 * @todo Localization.
 * @copyright Copyright (c) 2020 Patrick Lai
-->
<template>
<div class="pl2010-modal-mask">
	<div class="pl2010-modal-wrapper">
		<div class="pl2010-modal-container">
			<div class="pl2010-modal-header">
				<div class="title">
					<h3><slot name="title">Default Title</slot></h3>
				</div>
				<div class="ctrl-btns">
					<slot name="ctrl-btns"></slot>
					<button type="button" @click="$emit('close')">&cross;</button>
				</div>
				<div>&nbsp;</div>
			</div>
			<div class="pl2010-modal-body">
				<slot name="body">default body</slot>
			</div>
			<div class="pl2010-modal-footer">
				<slot name="footer">
					<button type="button" @click="$emit('close')">OK</button>
				</slot>
			</div>
		</div>
	</div>
</div>
</template>

<!-- vim: set ts=2 noexpandtab fdm=marker syntax=html: ('zR' to unfold all) -->
