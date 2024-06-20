<template>
	<v-container class="container align-center px-1">
		<v-row>
			<v-col
				md="6"
				sm="12"
			>
				<v-select
					label="Magazyn"
					v-model="IDWarehouse"
					:items="warehouses"
					item-title="Nazwa"
					item-value="IDMagazynu"
					hide-details="auto"
				></v-select>
			</v-col>
			<v-col
				md="6"
				sm="12"
			>
				<div class="d-flex">
					<v-number-input
						v-model="days"
						controlVariant="default"
						label="liczba dni"
						hide-details="auto"
						:hideInput="false"
						:inset="false"
						:max="20"
						:min="3"
					></v-number-input>
					<v-btn
						size="x-large"
						class="btn primary"
						@click="TowarLocationTipTab()"
						>Odbiór</v-btn
					></div
				>
			</v-col>
		</v-row>
		<v-row>
			<v-col cols="12"
				><v-data-table
					:items="dataTowarLocationTipTab"
					:loading="loading"
					@click:row="clickRow"
				></v-data-table
			></v-col>
		</v-row>
		<v-dialog
			id="dialogLocation"
			ref="dLocation"
			v-model="dialogLocation"
			transition="dialog-bottom-transition"
			fullscreen
			@keyup="handleKeypress"
		>
			<v-container>
				<v-card>
					<v-card-title class="mb-5 bg-grey-lighten-3">
						<v-row>
							<v-col>
								<b>Локация: {{ selected_item.LocationCode }}</b>
							</v-col>
							<v-spacer></v-spacer>

							<v-btn
								icon="mdi-close"
								@click="
									dialogLocation = false;
									text = '';
								"
							></v-btn
						></v-row>
					</v-card-title>

					<v-card-text>
						<!-- step 1 -->
						<h3
							class="text-red"
							v-if="step == 0"
						>
							Подтвердите локацию!
						</h3>
						<div class="d-flex flex-column"> </div>
					</v-card-text>
					<template v-slot:actions>
						<v-spacer></v-spacer>
						<section class="row"> </section>
					</template>
				</v-card>
			</v-container>
		</v-dialog>
	</v-container>
</template>

<script>
import { VNumberInput } from 'vuetify/labs/VNumberInput';
import axios from 'axios';
export default {
	components: {
		VNumberInput,
	},
	name: 'locationComponent',

	data() {
		return {
			headers: [
				{ text: 'IDMagazyn', value: 'IDMagazynu' },
				{ text: 'Magazyn', value: 'Nazwa' },
				{ text: 'Details', value: 'eMailAddress' },
				{ text: 'Dokument Cod', value: 'cod', name: 'cod', width: '180' },
				{ text: 'Action', value: 'actions', sortable: false },
			],
			dataTowarLocationTipTab: [],
			warehouses: [],
			IDWarehouse: null,
			days: 20,
			snackbar: false,
			message: '',
			loading: false,
			dialogLocation: false,
			location: '',
			selected_item: {},
			step: 0,
			imputCod: '',
		};
	},
	mounted() {
		this.getWarehouse();
	},
	methods: {
		steps() {
			if (this.step == 0) {
				if (this.imputCod == this.selected_item.LocationCode) {
					this.step = 1;
          // get product this.selected_item.IDTowaru

					return;
				}
			}
			if (this.step == 1) {
				if (this.imputCod == this.selected_item.KodKreskowy) {
					this.step = 2;

					return;
				}
			}
		},
		handleKeypress(event) {
			// Check if the Enter key was pressed
			if (event.key === 'Enter') {
				// Execute the function with the accumulated input
				this.steps();
				// Clear the input field
				this.imputCod = '';
			} else {
				// Append the current keystroke to the input
				this.imputCod += event.key;
			}
		},
		clickRow(event, row) {
			this.selected_item = row.item;
			this.dialogLocation = true;
		},

		TowarLocationTipTab() {
			const vm = this;
			if (!vm.IDWarehouse) return;
			vm.loading = true;
			let data = {};
			data.stor = vm.IDWarehouse;
			data.days = vm.days;
			axios
				.post('/api/TowarLocationTipTab', data)
				.then((res) => {
					if (res.status == 200) {
						vm.dataTowarLocationTipTab = res.data;
						vm.loading = false;
					}
				})
				.catch((error) => console.log(error));
		},
		getWarehouse() {
			const vm = this;
			axios
				.get('/api/getWarehouse')
				.then((res) => {
					if (res.status == 200) {
						vm.warehouses = res.data;
						// vm.IDWarehouse = vm.warehouses[0].IDMagazynu;
					}
				})
				.catch((error) => console.log(error));
		},
	},
};
</script>
