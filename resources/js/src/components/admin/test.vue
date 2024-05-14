<template>
	<v-container>
		<v-dialog
			id="dialogProduct"
			ref="dProduct"
			v-model="dialogProduct"
			transition="dialog-bottom-transition"
			fullscreen
			@keyup.capture="handleKeypress"
		>
			<v-container>
				<v-card>
					<v-card-title class="mb-5 bg-grey-lighten-3">
						<v-row>
							<v-col>
								Enter: {{ dialog_text }}<br />
								Input: {{ imputCod }}
							</v-col>
							<v-spacer></v-spacer>

							<v-btn
								icon="mdi-close"
								@click="
									imputCod = '';
									dialog_text = '';
								"
							></v-btn
						></v-row>
					</v-card-title>

					<v-card-text> </v-card-text>
					<template v-slot:actions>
						<v-spacer></v-spacer>
						<!-- v-if="products.find((e) => e.qty > 0)" -->
					</template>
				</v-card>
			</v-container>
		</v-dialog>
	</v-container>
</template>

<script>
export default {
	name: 'Test',

	data() {
		return {
			dialogProduct: true,
			dialog_text: '',
			dialog_title: '',
			imputCod: '',
		};
	},

	mounted() {},

	methods: {
		handleKeypress(event) {
			// Check if the Enter key was pressed
			if (event.key === 'Enter') {
				// Execute the function with the accumulated input
				this.dialog_text = this.imputCod;
				// Clear the input field
				this.imputCod = '';
			} else {
				// Append the current keystroke to the input
				this.imputCod += event.key;
			}
		},

		clear() {
			this.imputCod = '';
		},
	},
};
</script>
<style lang="scss">
</style>
