<template>
	<v-container class="container align-center px-1">
		<v-row>
			<v-col
				md="6"
				cols="12"
			>
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
			</v-col>
			<v-col
				md="6"
				cols="12"
			>
				<div class="d-flex">
					<v-select
						label="Magazyn"
						v-model="IDWarehouse"
						:items="warehouses"
						item-title="Nazwa"
						item-value="IDMagazynu"
						hide-details="auto"
					></v-select>
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
			<!--  -->
			<v-col cols="12"
				><v-data-table
					:items="dataTowarLocationTipTab.filter((l) => l.peremestit > 0)"
					:loading="loading"
					@click:row="clickRow"
				></v-data-table
			></v-col>
		</v-row>
		<!-- contenteditable="true" -->
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
								<b
									>Z lokalizacji: {{ selected_item.LocationCode }}
									<span v-if="step >= 1"
										><v-icon
											icon="mdi-checkbox-marked-circle-outline"
											color="green"
										></v-icon></span
								></b>
							</v-col>
							<v-spacer></v-spacer>

							<div
								class="btn border"
								@click="
									dialogLocation = false;
									clear();
									text = '';
								"
								><v-icon icon="mdi-close"></v-icon></div
						></v-row>
					</v-card-title>

					<v-card-text>
						<h5
							class="text-red"
							v-if="message"
							>{{ message }}</h5
						>
						<!-- step 1 -->
						<h3
							class="text-red"
							v-if="step == 0"
						>
							Potwierdź lokalizację!
						</h3>
						<v-row
							class="product_line border my-0"
							v-if="product"
						>
							<v-col>
								<div class="d-flex">
									<img
										v-if="product.Zdjecie"
										:src="'data:image/jpeg;base64,' + product.Zdjecie"
										alt="pic"
										style="height: 3em"
									/>
									<span
										><h5
											>{{ product.Nazwa }}<br />cod: {{ product.KodKreskowy }}, sku:
											{{ product._TowarTempString1 }}</h5
										>
									</span>
									<!-- <v-btn
										class="d-none"
										@click="
											edit = product;
											dialogMessageQty = true;
										"
										><v-icon>mdi-pencil</v-icon></v-btn
									> -->
								</div>
							</v-col>

							<v-col>
								<div class="d-flex justify-end">
									<div
										class="btn border"
										@click="changeCounter(product, -1)"
										>-</div
									>
									<div
										:id="product.IDTowaru"
										class="border qty text-h5 text-center"
									>
										{{ product.qty }} z {{ parseInt(selected_item.Quantity) }}</div
									>
									<div
										class="btn border"
										@click="changeCounter(product, 1)"
										>+</div
									>
								</div>
							</v-col>
						</v-row>
					</v-card-text>
					<template v-slot:actions>
						<v-row v-if="product">
							<v-col cols="12">
								<b>Do lokalizacji</b>
								<v-table density="compact">
									<thead>
										<tr>
											<th class="text-left"> LocationCode </th>
											<th class="text-left"> Quantity </th>
										</tr>
									</thead>
									<tbody>
										<tr
											v-for="l in toLocations"
											:key="l.idLocationCode"
											:class="{
												'bg-green-lighten-4': l.LocationCode == toLocation,
											}"
										>
											<td>{{ l.LocationCode }}</td>
											<td>{{ l.Quantity }}</td>
										</tr>
									</tbody>
								</v-table>
							</v-col>
							<v-col>
								<v-btn
									v-if="step == 3"
									class="btn primary"
									variant="tonal"
									>Relokacja</v-btn
								>
							</v-col>
						</v-row>
					</template>
					{{ test }}
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
			message: '',
			product: null,
			toLocations: [],
			toLocation: null,
			test: '',
		};
	},
	mounted() {
		this.getWarehouse();
	},
	methods: {
		clear() {
			this.step = 0;
			this.loading = false;
			this.imputCod = '';
			this.selected_item = {};
			this.product = null;
			this.message = '';
		},
		changeCounter: function (item, num) {
			item.qty = parseInt(item.qty) + parseInt(+num);
			if (item.qty < 0) item.qty = 0;
			//this.setFocus();
		},
		steps() {
			const vm = this;
			this.message = '';

			console.log(this.imputCod);
			this.imputCod = this.imputCod.replaceAll(/Shift(.)/g, (_, p1) => p1.toUpperCase());

			if (this.step == 0) {
				if (this.imputCod == this.selected_item.LocationCode) {
					this.step = 1;
					this.toLocations = this.dataTowarLocationTipTab.filter(
						(l) =>
							l.KodKreskowy == this.selected_item.KodKreskowy &&
							//l.LocationCode != this.selected_item.LocationCode &&
							l.TypLocations != 2,
					);
					vm.loading = true;
					// get product this.selected_item.IDTowaru
					axios
						.get('/api/getProduct/' + this.selected_item.IDTowaru)
						.then((res) => {
							if (res.status == 200) {
								vm.product = res.data;
								vm.loading = false;
							}
						})
						.catch((error) => console.log(error));
					return;
				} else {
					this.message = 'Błąd lokalizacji (';
				}
			}
			if (this.step == 1) {
				if (this.toLocations.find((f) => f.LocationCode == this.imputCod)) {
					this.step = 3;
					this.toLocation = this.imputCod;
					return;
				}
				if (this.imputCod == this.selected_item.KodKreskowy) {
					this.changeCounter(this.product, 1);
				} else {
					if (/[a-zA-Z]+/.test(this.imputCod)) {
						this.message = 'Błąd lokalizacji (';
					} else {
						this.message = 'Brak produktu!!!';
					}
				}
			}
		},
		handleKeypress(event) {
			if (event.key === 'Shift') {
				return;
			}
			if (event.key === 'Enter') {
				this.steps();
				this.imputCod = '';
			} else {
				let key = event.key;
				console.log(event);
				this.imputCod += key;
				this.test = this.imputCod;
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
