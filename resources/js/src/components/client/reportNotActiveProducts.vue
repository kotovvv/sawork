<template>
	<div>
		<v-container>
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
				<v-col>
					<v-text-field
						label="Days"
						v-model.number="days"
						hide-details
						single-line
						min="1"
						type="number"
						width="300"
						max-width="400"
						@keypress="filter()"
				/></v-col>
				<v-col>
					<v-btn
						@click="getDataNotActivProduct()"
						size="x-large"
						>uzyskać dane</v-btn
					>
					<v-btn
						v-if="dataforxsls.length"
						@click="prepareXLSX()"
						size="x-large"
						>pobieranie XLSX</v-btn
					></v-col
				>
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
		<v-container
			fluid
			v-if="dataforxsls.length"
		>
			<v-row>
				<v-col cols="12">
					<!-- :headers="headers" -->
					<v-data-table
						:items="dataforxsls"
						item-value="IDTowaru"
						:search="searchInTable"
						@click:row="handleClick"
						select-strategy="single"
						:row-props="colorRowItem"
					>
						<template v-slot:top="{}">
							<v-row class="align-center">
								<v-col cols="2">
									<v-text-field
										label="odzyskiwanie"
										v-model="searchInTable"
										clearable
									></v-text-field>
								</v-col>
								<productHistory
									:product_id="selected[0]"
									:product="getProduct()"
								/>
							</v-row>
						</template>
					</v-data-table>
				</v-col>
			</v-row>
		</v-container>
	</div>
</template>
<script>
import axios from 'axios';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
import productHistory from './productHistory.vue';
export default {
	components: {
		productHistory,
	},
	data: () => ({
		selected: [],
		searchInTable: '',
		loading: false,
		days: 30,
		dataforxsls: [],
		warehouses: [],
		IDWarehouse: null,
	}),
	mounted() {
		this.getWarehouse();
	},
	methods: {
		getProduct() {
			return this.dataforxsls.filter((el) => {
				return el.IDTowaru == this.selected[0];
			});
		},
		colorRowItem(item) {
			if (item.item.IDTowaru != undefined && item.item.IDTowaru == this.selected[0]) {
				return { class: 'bg-red-darken-4' };
			}
		},
		handleClick(event, row) {
			this.selected = [row.item.IDTowaru];
		},
		getWarehouse() {
			const vm = this;
			axios
				.get('/api/getWarehouse')
				.then((res) => {
					if (res.status == 200) {
						vm.warehouses = res.data;
						vm.IDWarehouse = vm.warehouses[0].IDMagazynu;
					}
				})
				.catch((error) => console.log(error));
		},

		getDataNotActivProduct() {
			const vm = this;
			vm.loading = true;
			vm.dataforxsls = [];
			axios
				.get('/api/getDataNotActivProduct/' + vm.days + '/' + vm.IDWarehouse)
				.then((res) => {
					if (res.status == 401) {
						document.location.reload();
					}
					if (res.status == 200) {
						vm.dataforxsls = res.data;
						vm.loading = false;
					}
				})
				.catch((error) => {
					console.log(error);
					vm.loading = false;
				});
		},
		prepareXLSX() {
			// Создание новой книги
			const wb = XLSX.utils.book_new();
			const ws = XLSX.utils.json_to_sheet(this.dataforxsls);
			XLSX.utils.book_append_sheet(wb, ws, '');

			// Генерация файла и его сохранение
			const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
			saveAs(new Blob([wbout], { type: 'application/octet-stream' }), 'noActive' + this.IDWarehouse + '.xlsx');
		},
		filter: function (evt) {
			evt = evt ? evt : window.event;
			let expect = evt.target.value.toString() + evt.key.toString();

			if (!/^[-+]?[0-9]*\.?[0-9]*$/.test(expect)) {
				evt.preventDefault();
			} else {
				return true;
			}
		},
	},
};
</script>
