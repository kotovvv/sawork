<template>
	<div
		ref="focusTarget"
		tabindex="0"
		@keypress="handleKeyDown"
	>
		<v-card height="90vh">
			<v-card-text> You enter: {{ text }}<br /> </v-card-text>
		</v-card>
	</div>
</template>

<script>
export default {
	name: 'Test',
	data() {
		return {
			text: '',
			inputCod: '',
		};
	},
	mounted() {
		this.setFocus();
	},

	methods: {
		setFocus() {
			this.text = '';
			this.inputCod = '';
			this.$refs.focusTarget.focus(); // Focus the target element
		},
		handleKeyDown(event) {
			event.preventDefault();

			if (event.key === 'Enter') {
				this.text = this.imputCod.replace('undefined', '');
				this.imputCod = '';
			} else {
				this.imputCod += event.key;
			}
		},
	},
};
</script>
<style lang="scss">
</style>
