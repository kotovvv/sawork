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
			</v-col>
			<v-col cols="6"
				><p>Select</p>
				<v-data-table-virtual
					:items="priceCondition"
					height="400"
					selected
				></v-data-table-virtual>
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
		};
	},

	mounted() {
		this.getWarehouse();
		this.getPriceCondition();
	},

	methods: {
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
