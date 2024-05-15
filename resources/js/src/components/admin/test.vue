<template>
	<div
		ref="focusTarget"
		tabindex="0"
		@keypress="handleKeyDown"
	>
		<v-card height="100vh">
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
		makeAny() {
			this.text = this.inputCod.replace('undefined', '');
		},
		setFocus() {
			this.text = '';
			this.inputCod = '';
			this.$refs.focusTarget.focus(); // Focus the target element
		},
		handleKeyDown(event) {
			// Prevent default behavior (e.g., scrolling) if necessary
			event.preventDefault();

			if (event.key === 'Enter') {
				// Execute the function with the accumulated input
				this.text = this.imputCod.replace('undefined', '');

				// Clear the input field
				this.imputCod = '';
			} else {
				// Append the current keystroke to the input

				this.imputCod += event.key;
			}
		},
	},
};
</script>
<style lang="scss">
</style>
