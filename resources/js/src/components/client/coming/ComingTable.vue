<template>
	<div>
		<v-progress-linear
			:active="loading"
			indeterminate
			color="purple"
		></v-progress-linear>

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
						></v-text-field>
					</v-col>
				</v-row>
			</template>
		</v-data-table>
	</div>
</template>

<script>
import axios from 'axios';

export default {
	name: 'ComingTable',
	props: ['IDWarehouse'],
	data: () => ({
		docsDM: [],
		selected: {},
		headers: [
			{ title: 'Data', key: 'Data' },
			{ title: 'Nr Dokumentu', key: 'NrDokumentu', sortable: false },
			{ title: 'Wartość Dokumentu', key: 'WartoscDokumentu', sortable: false, align: 'end' },
			{ title: 'Status' },
			{ title: 'Uwaga' },
		],
		searchInTable: '',
		loading: false,
	}),
	mounted() {
		this.getDM();
	},
	methods: {
		handleClick(e, row) {
			this.selected = row.item;
			this.$emit('item-selected', this.selected); // Emit event with selected item
		},

		colorRowItem(item) {
			if (
				item.item.IDRuchuMagazynowego != undefined &&
				item.item.IDRuchuMagazynowego == this.selected.IDRuchuMagazynowego
			) {
				return { class: 'bg-red-darken-4' };
			}
		},
		getDM() {
			const vm = this;
			let data = {};
			vm.loading = true;
			data.IDMagazynu = vm.IDWarehouse;
			axios
				.post('/api/getDM', data)
				.then((res) => {
					if (res.status == 200) {
						vm.docsDM = res.data;
						vm.docsDM.forEach((el) => {
							el.WartoscDokumentu = parseFloat(el.WartoscDokumentu).toFixed(2);
						});
					}
					vm.loading = false;
				})
				.catch((error) => console.log(error));
		},
	},
};
</script>
