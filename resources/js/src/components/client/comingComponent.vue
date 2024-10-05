<template>
	<div>
		<v-container
			fluid
			v-if="selectedItem"
			:id="selectedItem.IDRuchuMagazynowego"
			style="min-height: 100vh"
			:key="selectedItem"
		>
			<v-row>
				<v-col>
					<h3
						>{{ selectedItem.NrDokumentu }} <small>{{ selectedItem.Data.substring(0, 10) }}</small></h3
					>
				</v-col>
				<v-spacer></v-spacer>
				<v-btn
					icon="mdi-close"
					@click="selectedItem = null"
				></v-btn>
			</v-row>
			<v-row>
				<v-btn @click="createPZ">create PZ</v-btn>
				<v-btn @click="showProducts">Show products</v-btn>
			</v-row>
			<v-row>
				<v-textarea
					label="Uwaga"
					v-model="Uwaga"
				></v-textarea>
			</v-row>
		</v-container>
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
		<v-container fluid>
			<v-row>
				<v-col>
					<ComingTable
						:IDWarehouse="IDWarehouse"
						:key="IDWarehouse"
						@item-selected="handleItemSelected"
					/>
				</v-col>
			</v-row>
		</v-container>
	</div>
</template>

<script>
import axios from 'axios';
import ComingTable from './coming/ComingTable.vue';

export default {
	name: 'Coming',

	components: { ComingTable },
	data: () => ({
		Uwaga: '',
		loading: false,

		IDWarehouse: null,
		warehouses: [],
		selectedItem: null,
	}),
	mounted() {
		this.getWarehouse();
	},
	methods: {
		createPZ() {
			const vm = this;
			let data = {};
			data.IDMagazynu = vm.IDWarehouse;
			data.IDRuchuMagazynowego = vm.selectedItem.IDRuchuMagazynowego;
			data.Uwagi = vm.Uwaga;
			axios
				.post('/api/createPZ', data)
				.then((res) => {
					if (res.status == 200) {
						console.log(res.data);
					}
				})
				.catch((error) => console.log(error));
		},

		handleItemSelected(item) {
			this.selectedItem = item;
		},
		getWarehouse() {
			const vm = this;
			vm.loading = true;
			axios
				.get('/api/getWarehouse')
				.then((res) => {
					if (res.status == 200) {
						vm.warehouses = res.data;
						if (vm.warehouses.length > 0) {
							vm.IDWarehouse = vm.warehouses[0].IDMagazynu;
							vm.loading = false;
						}
					}
				})
				.catch((error) => console.log(error));
		},
	},
};
</script>
