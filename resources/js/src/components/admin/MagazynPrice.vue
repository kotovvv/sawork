<template>
	<v-container>
		<v-row>
			<v-col cols="12">
				<v-col>
					<v-select
						label="Magazyn"
						v-model="IDWarehouse"
						:items="warehouses"
						item-title="Nazwa"
						item-value="IDMagazynu"
						hide-details="auto"
						width="368"
						max-width="400"
					></v-select>
				</v-col>
			</v-col>
			<v-col cols="6">
				<p>For magazyn</p>
				<v-data-table
					:headers="headers"
					:items="showClientPriceCondition"
					item-value="condition_id"
					height="400"
					:hide-default-footer="true"
				></v-data-table>
			</v-col>
			<v-col cols="6"
				><p>Select</p>

				<v-text-field
					v-model="search"
					label="Search"
					prepend-inner-icon="mdi-magnify"
					variant="outlined"
					hide-details
					single-line
					clearable
				></v-text-field>

				<v-data-table
					v-model="selected"
					show-select
					:headers="headers"
					:items="priceCondition"
					item-value="condition_id"
					height="400"
					:search="search"
					:hide-default-footer="true"
				></v-data-table>
				<v-btn @click="setClientPriceCondition()">Set</v-btn>
			</v-col>
		</v-row>
	</v-container>
</template>

<script>
import axios from 'axios';
export default {
	name: 'MagazynPrice',

	data() {
		return {
			warehouses: [],
			IDWarehouse: null,
			priceCondition: [],
			clientPriceCondition: [],
			showClientPriceCondition: [],
			selected: [],
			search: '',
			headers: [
				{ title: 'min', key: 'min_value', sortable: false },
				{ title: 'max', key: 'max_value', sortable: false },
				{ title: 'price', key: 'price', sortable: false },
				{ title: 'Warehouse', key: 'magazyn', sortable: false },
			],
			loading: false,
		};
	},
	watch: {
		IDWarehouse(idmag, idmagold) {
			this.getClientPriceCondition(idmag);
		},
	},
	mounted() {
		this.getWarehouse();
		this.getPriceCondition();
	},

	methods: {
		setClientPriceCondition() {
			if (this.IDWarehouse == null) return;
			const vm = this;
			let data = {};
			data.id_condition = vm.selected;
			data.IDWarehouse = vm.IDWarehouse;
			axios
				.post('/api/setClientPriceCondition', data)
				.then((res) => {
					if (res.status == 200) {
						vm.getClientPriceCondition(vm.IDWarehouse);
					}
				})
				.catch((error) => console.log(error));
		},
		getClientPriceCondition(idmag) {
			const vm = this;
			vm.selected = [];
			vm.showClientPriceCondition = [];
			axios
				.get('/api/getClientPriceCondition/' + idmag)
				.then((res) => {
					if (res.status == 200) {
						vm.clientPriceCondition = res.data;
						vm.selected = vm.clientPriceCondition.map(({ condition_id }) => condition_id);
						vm.showClientPriceCondition = vm.priceCondition.filter((f) =>
							vm.selected.includes(f.condition_id),
						);
					}
				})
				.catch((error) => console.log(error));
		},
		getPriceCondition() {
			const vm = this;
			axios
				.get('/api/getPriceCondition')
				.then((res) => {
					if (res.status == 200) {
						vm.priceCondition = res.data;
						vm.priceCondition.map((p) => {
							p.max_value = parseFloat(p.max_value);
							p.min_value = parseFloat(p.min_value);
							p.price = parseFloat(p.price);
						});
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
					}
				})
				.catch((error) => console.log(error));
		},
	},
};
</script>
