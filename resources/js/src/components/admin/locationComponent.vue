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
				></v-data-table
			></v-col>
		</v-row>
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
		};
	},
	mounted() {
		this.getWarehouse();
	},
	methods: {
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
