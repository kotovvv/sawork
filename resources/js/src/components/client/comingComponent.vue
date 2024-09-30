<template>
	<div style="min-height: 100vh">
		<v-container fluid>
			<v-row>
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
			</v-row>
		</v-container>

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
	</div>
</template>

<script>
import moment from 'moment';
import axios from 'axios';

export default {
	name: 'Coming',

	components: {},
	data: () => ({
		loading: false,

		IDWarehouse: null,
		warehouses: [],
	}),
	mounted() {
		this.getWarehouse();
	},
	methods: {
		getWarehouse() {
			const vm = this;
			axios
				.get('/api/getWarehouse')
				.then((res) => {
					if (res.status == 200) {
						vm.warehouses = res.data;
						if (vm.warehouses.length > 0) {
							vm.IDWarehouse = vm.warehouses[0].IDMagazynu;
						}
					}
				})
				.catch((error) => console.log(error));
		},
	},
};
</script>
