<template>
	<div
		ref="focusTarget"
		tabindex="0"
		@keydown="handleKeyDown"
	>
		<v-card height="100vh">
			<v-card-text>
				<v-text-field
					v-model="inputCod"
					ref="cod"
					@change="makeAny"
					clearable
					class="d-none"
				></v-text-field>
				You enter: {{ text }}<br />
			</v-card-text>
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
			this.text = this.inputCod;
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
