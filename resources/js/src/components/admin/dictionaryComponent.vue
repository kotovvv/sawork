<template>
	<v-container class="container align-center px-1">
		<h2 class="font-weight-light mb-2"> Magazyn - email </h2>
		<v-col cols="12">
			<v-btn
				@click="dialog = !dialog"
				color="surface-variant"
				text="Nowość"
				variant="flat"
			></v-btn>
		</v-col>
		<v-card>
			<v-data-table
				:headers="headers"
				:items="magazyns"
				mobile-breakpoint="800"
				class="elevation-0"
			>
				<template v-slot:item.actions="{ item }">
					<div class="text-truncate">
						<v-icon
							small
							class="mr-2"
							@click="showEditDialog(item)"
							color="primary"
						>
							mdi-pencil
						</v-icon>
						<v-icon
							small
							@click="deleteMagEmail(item)"
							color="pink"
						>
							mdi-delete
						</v-icon>
					</div>
				</template>
			</v-data-table>

			<!-- this dialog is used for both create and update -->
			<v-dialog
				max-width="500"
				v-model="dialog"
			>
				<template v-slot:default="{ isActive }">
					<v-card>
						<v-card-title>
							<span v-if="editedItem.id">Edytuj {{ editedItem.name }}</span>
							<span v-else>Create</span>
						</v-card-title>
						<v-card-text>
							<v-row>
								<v-col>
									<v-select
										v-model="editedItem.IDMagazynu"
										:items="warehouses"
										item-title="Nazwa"
										item-value="IDMagazynu"
										label="Nazwa"
										persistent-hint
										single-line
									></v-select>
								</v-col>
								<v-col cols="12">
									<v-text-field
										v-model="editedItem.eMailAddress"
										label="eMailAddress"
									></v-text-field>
								</v-col>

								<v-col cols="12">
									<v-select
										v-model="editedItem.cod"
										:items="cod"
										label="Cod"
										persistent-hint
										single-line
									></v-select>
								</v-col>
							</v-row>
						</v-card-text>

						<v-card-actions>
							<v-spacer></v-spacer>
							<v-btn
								color="blue darken-1"
								text
								@click="
									isActive.value = false;
									editedItem = {};
								"
								>Anuluj</v-btn
							>
							<v-btn
								color="blue darken-1"
								text
								@click="saveMagEmail(editedItem)"
								>Zapisz</v-btn
							>
						</v-card-actions>
					</v-card>
				</template>
			</v-dialog>
		</v-card>
		<v-btn
			class="btn primary"
			@click="sendPDF()"
			>Wyślij PDF</v-btn
		>
		<v-snackbar
			v-model="snackbar"
			vertical
		>
			<p>{{ message }}</p>

			<template v-slot:actions>
				<v-btn
					color="indigo"
					variant="text"
					@click="
						snackbar = false;
						message = '';
					"
				>
					Close
				</v-btn>
			</template>
		</v-snackbar>
	</v-container>
</template>

<script>
import axios from 'axios';
export default {
	name: 'DictionaryComponent',

	data() {
		return {
			magazyns: [],
			headers: [
				{ text: 'IDMagazyn', value: 'IDMagazynu' },
				{ text: 'Magazyn', value: 'Nazwa' },
				{ text: 'Details', value: 'eMailAddress' },
				{ text: 'Dokument Cod', value: 'cod', name: 'cod', width: '180' },
				{ text: 'Action', value: 'actions', sortable: false },
			],
			magEmail: [],
			dialog: false,
			editedItem: {},
			warehouses: [],
			cod: ['WZk'],
			snackbar: false,
			message: '',
		};
	},
	mounted() {
		this.loadMagEmail();
		this.getWarehouse();
	},
	methods: {
		sendPDF() {
			const self = this;
			axios
				.get('/api/sendPDF')
				.then((response) => {
					self.message = response.data;
					self.snackbar = true;
				})
				.catch((error) => {
					console.log(error);
				});
		},
		loadMagEmail() {
			const self = this;
			axios
				.get('/api/loadMagEmail')
				.then((response) => {
					self.magazyns = response.data;
				})
				.catch((error) => {
					console.log(error);
				});
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
		showEditDialog(item) {
			console.log(item.Nazwa);
			this.editedItem = item || {};
			this.dialog = !this.dialog;
		},

		saveMagEmail(item) {
			let data = {};
			data.id = item.ID;
			data.IDMagazynu = item.IDMagazynu;
			data.eMailAddress = item.eMailAddress;
			data.cod = item.cod;

			// save the record
			axios
				.post('api/saveMagEmail', data, {
					// headers: {
					// 	'Authorization': 'Bearer ' + apiToken,
					// 	'Content-Type': 'application/json',
					// },
				})
				.then((response) => {
					if (response.data > 0) {
						console.log(response.data);
						this.magazyns.push(this.editedItem);
					} else {
						this.editedItem.ID = response.data;
					}
					this.editedItem = {};
					this.dialog = !this.dialog;
				});
		},
		deleteMagEmail(item) {
			let data = {};
			data.ID = item.ID;

			let idx = this.magazyns.findIndex((i) => i.ID === item.ID);
			// save the record
			if (confirm('Czy na pewno chcesz to usunąć??')) {
				axios.post('api/deleteMagEmail', data, {}).then((response) => {
					this.magazyns.splice(idx, 1);
				});
			}
		},
	},
};
</script>
