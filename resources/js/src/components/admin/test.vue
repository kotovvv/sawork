<template>
	<v-container>
		<v-card>
			<v-card-text>
				<v-text-field
					v-model="imputCod"
					label="Cod"
					ref="cod"
				></v-text-field>
				Enter: {{ text }}<br />
			</v-card-text>
		</v-card>
		<!-- @keyup.capture="handleKeypress" -->
		<v-dialog
			id="dialogProduct"
			ref="dProduct"
			v-model="dialogProduct"
			transition="dialog-bottom-transition"
			fullscreen
			@paste="eventPaste"
		>
			<v-container>
				<v-card>
					<v-card-title class="mb-5 bg-grey-lighten-3">
						<v-row>
							<v-col> </v-col>
							<v-spacer></v-spacer>

							<v-btn
								icon="mdi-close"
								@click="dialogProduct = false"
							></v-btn
						></v-row>
					</v-card-title>

					<v-card-text>
						<v-text-field label="Cod"></v-text-field>

						Enter: {{ dialog_text }}<br />
						Input: {{ imputCod }}
					</v-card-text>
					<template v-slot:actions>
						<v-spacer></v-spacer>
						<!-- v-if="products.find((e) => e.qty > 0)" -->
						<v-btn
							@click="
								imputCod = '';
								dialog_text = '';
							"
						>
							clear</v-btn
						>
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
			text: '',

			imputCod: '',
		};
	},

	mounted() {},

	methods: {},
};
</script>
<style lang="scss">
</style>
