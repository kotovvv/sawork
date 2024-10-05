<template>
	<div>
		<v-progress-linear
			:active="loading"
			indeterminate
			color="purple"
		></v-progress-linear>
		<v-container>
			<v-row>
				<v-col>
					<!-- return-object -->
					<v-data-table
						:items="docsDM"
						:headers="headers"
						item-value="IDRuchuMagazynowego"
						:search="searchInTable"
						@click:row="handleClick"
						select-strategy="single"
						:row-props="colorRowItem"
						fixed-header
						return-object
					>
						<template
							v-slot:top="{}"
							v-if="docsDM.length"
						>
							<v-row class="align-center">
								<v-col class="v-col-sm-6 v-col-md-2">
									<v-text-field
										label="odzyskiwanie"
										v-model="searchInTable"
										clearable
										hide-details
									></v-text-field>
								</v-col>
								<v-btn
									@click="getDM"
									icon="mdi-refresh"
								></v-btn>
							</v-row>
						</template>
					</v-data-table>
				</v-col>
				<v-col>
					<v-data-table
						:items="docsPZ"
						item-value="IDRuchuMagazynowego"
						:search="searchInTablePZ"
						@click:row="handleClickPz"
						select-strategy="single"
						:row-props="colorRowItemPZ"
						fixed-header
						return-object
					>
						<template
							v-slot:top="{}"
							v-if="docsPZ.length"
						>
							<v-row class="align-center">
								<v-col class="v-col-sm-6 v-col-md-2">
									<v-text-field
										label="odzyskiwanie"
										v-model="searchInTablePZ"
										clearable
										hide-details
									></v-text-field>
								</v-col>
								<v-btn
									@click="getPZ"
									icon="mdi-refresh"
								></v-btn>
								<v-btn
									class="ml-3"
									@click="connectDMPZ"
									v-if="selected.IDRuchuMagazynowego && selectedPZ.IDRuchuMagazynowego"
									>DM <-> PZ</v-btn
								>
							</v-row>
						</template>
					</v-data-table>
					<!-- return-object -->
				</v-col>
			</v-row>
		</v-container>
	</div>
</template>

<script>
import axios from 'axios';

export default {
	name: 'ComingTable',
	props: ['IDWarehouse'],
	data: () => ({
		docsDM: [],
		docsPZ: [],
		selected: {},
		selectedPZ: {},
		headers: [
			{ title: 'Data', key: 'Data' },
			{ title: 'Nr Dokumentu', key: 'NrDokumentu', sortable: false },
			{ title: 'Wartość Dokumentu', key: 'WartoscDokumentu', sortable: false, align: 'end' },
			{ title: 'Stan', key: 'status', sortable: false },
			{ title: 'Uwaga' },
		],
		searchInTable: '',
		searchInTablePZ: '',
		loading: false,
	}),
	mounted() {
		this.getDM();
	},
	methods: {
		handleClick(e, row) {
			this.selected = row.item;
			// this.$emit('item-selected', this.selected); // Emit event with selected item
		},
		handleClickPz(e, row) {
			console.log(row.item);
			this.selectedPZ = row.item;
			// this.$emit('item-selected', this.selectedPz); // Emit event with selected item
		},

		colorRowItem(item) {
			if (
				item.item.IDRuchuMagazynowego != undefined &&
				item.item.IDRuchuMagazynowego == this.selected.IDRuchuMagazynowego
			) {
				return { class: 'bg-red-darken-4' };
			}
		},
		colorRowItemPZ(item) {
			if (
				item.item.IDRuchuMagazynowego != undefined &&
				item.item.IDRuchuMagazynowego == this.selectedPZ.IDRuchuMagazynowego
			) {
				return { class: 'bg-red-darken-4' };
			}
		},
		getDM() {
			const vm = this;
			let data = {};
			vm.loading = true;
			vm.selected = {};
			vm.selectedPZ = {};
			data.IDMagazynu = vm.IDWarehouse;
			axios
				.post('/api/getDM', data)
				.then((res) => {
					if (res.status == 200) {
						vm.docsDM = res.data;
						vm.docsDM.forEach((el) => {
							el.WartoscDokumentu = parseFloat(el.WartoscDokumentu).toFixed(2);
							if (el.ID1) {
								el.status = 'Towary przyjęte na magazyn (' + el.RelatedNrDokumentu + ')';
							} else {
								el.status = 'Oczekiwanie na dostawę';
							}
						});
						vm.getPZ();
					}
				})
				.catch((error) => console.log(error));
		},
		getPZ() {
			const vm = this;
			let data = {};
			vm.loading = true;
			vm.selected = {};
			vm.selectedPZ = {};
			data.IDMagazynu = vm.IDWarehouse;

			axios
				.post('/api/getPZ', data)
				.then((res) => {
					if (res.status == 200) {
						vm.docsPZ = res.data;
						vm.docsPZ.forEach((el) => {
							el.WartoscDokumentu = parseFloat(el.WartoscDokumentu).toFixed(2);
						});
					}
					vm.loading = false;
				})
				.catch((error) => console.log(error));
		},
		connectDMPZ() {
			const vm = this;
			let data = {};
			data.ID1 = vm.selectedPZ.IDRuchuMagazynowego;
			data.ID2 = vm.selected.IDRuchuMagazynowego;
			axios
				.post('/api/connectDMPZ', data)
				.then((res) => {
					if (res.status == 200) {
						vm.getDM();
					}
				})
				.catch((error) => console.log(error));
		},
	},
};
</script>
