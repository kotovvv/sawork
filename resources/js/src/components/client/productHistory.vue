<template>
	<div class="text-center pa-4">
		<v-dialog
			v-model="dialog"
			transition="dialog-bottom-transition"
			fullscreen
		>
			<template v-slot:activator="{ props: activatorProps }">
				<v-btn
					size="small"
					text="Historia"
					v-bind="activatorProps"
				></v-btn>
				<v-container fluid>
					<v-row>
						<v-col cols="12">
							<v-progress-linear
								:active="loading"
								indeterminate
								color="purple"
							></v-progress-linear>
						</v-col>
					</v-row>
				</v-container>
			</template>

			<v-card>
				<v-toolbar>
					<v-toolbar-title>Historia</v-toolbar-title>

					<v-spacer></v-spacer>

					<v-toolbar-items>
						<v-btn
							icon="mdi-close"
							@click="dialog = false"
						></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-text>
					<v-data-table
						:items="dataHistory"
						:headers="headers"
					>
						<template v-slot:top="{}">
							<v-row class="align-center">
								<img
									v-if="product.Zdjecie"
									:src="'data:image/jpeg;base64,' + product.Zdjecie"
									alt="pic"
									style="height: 4em"
								/>

								<v-col>
									<h3> {{ product.Nazwa }}</h3>
								</v-col>
								<v-col>
									Kod kreskowy: <b>{{ product.KodKreskowy }}</b>
								</v-col>
								<v-col>
									sku: <b> {{ product.sku }}</b>
								</v-col>
							</v-row>
						</template>
					</v-data-table>
				</v-card-text>
			</v-card>
		</v-dialog>
	</div>
</template>
<script>
import axios from 'axios';
export default {
	name: 'productHistory',
	props: ['product_id'],
	data() {
		return {
			loading: false,
			dialog: false,
			dataHistory: [],
			headers: [
				{ title: 'Numer Dokumentu', key: 'document_number', sortable: false },
				{ title: 'Rodzaj Dokumentu', key: 'movement_name', sortable: false },
				{ title: 'Ilość', key: 'stock_level', sortable: false },
				// { title: 'Cena jednostkowa', key: 'unit_price', sortable: false },
				{ title: 'Stan magazynu', key: 'quantity', sortable: false },
				// { title: 'Wartość', key: 'wartosc', sortable: false },
				{ title: 'Data', key: 'date', sortable: false },
				{ title: 'Nazwa kontrahenta', key: 'contractor_name', sortable: false },
				{ title: 'Uwagi', key: 'remarks', sortable: false },
				// { title: 'Twórca dokumentu', key: 'min_value', sortable: false },
			],
			product: {},
		};
	},
	watch: {
		dialog(visible) {
			if (visible) {
				this.getProductHistory();
				// console.log(this.dataHistory);
			} else {
				this.dataHistory = [];
			}
		},
	},
	methods: {
		getProductHistory() {
			const vm = this;
			vm.loading = true;
			vm.product = {};
			axios
				.get('/api/getProductHistory/' + this.product_id)
				.then((res) => {
					if (res.status == 200) {
						vm.dataHistory = res.data;
						axios
							.get('api/getProduct/' + this.product_id)
							.then((pr) => {
								if (pr.status == 200) {
									vm.product = pr.data;
								}
							})
							.catch((error) => console.log(error));
					}

					vm.loading = false;
				})
				.catch((error) => console.log(error));
		},
	},
};
</script>
