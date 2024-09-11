<template>
	<div style="height: 100vh">
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
				<v-col>
					<datepicker
						v-model="dateMin"
						format="yyyy-MM-dd"
						monday-first
					></datepicker>

					<datepicker
						v-model="dateMax"
						format="yyyy-MM-dd"
						monday-first
					></datepicker>
				</v-col>
				<v-col>
					<v-btn
						@click="getOborot()"
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
						height="55vh"
					>
						<template v-slot:top="{}">
							<v-row class="align-center">
								<v-col class="v-col-sm-6 v-col-md-2">
									<v-text-field
										label="odzyskiwanie"
										v-model="searchInTable"
										clearable
									></v-text-field>
								</v-col>
								<productHistory :product_id="selected[0]" />
							</v-row>
						</template>
					</v-data-table>
				</v-col>
			</v-row>
		</v-container>
	</div>
</template>

<script>
import Datepicker from 'vuejs3-datepicker';

import axios from 'axios';
import moment from 'moment';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
import productHistory from './productHistory.vue';

export default {
	name: 'reportOborot',
	components: {
		productHistory,
		Datepicker,
	},
	data: () => ({
		loading: false,
		dateMin: moment().format('YYYY-MM-01'),
		dateMax: moment().format('YYYY-MM-DD'),
		dataforxsls: [],
		warehouses: [],
		IDWarehouse: null,
		searchInTable: '',
	}),
	mounted() {
		this.getWarehouse();
	},
	methods: {
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

		getOborot() {
			const vm = this;
			vm.loading = true;
			let data = {};
			data.dataMin = vm.dateMin;
			data.dataMax = vm.dateMax;
			data.IDMagazynu = vm.IDWarehouse;
			data.IDKontrahenta = null;
			axios
				.post('/api/getOborot', data)
				.then((res) => {
					if (res.status == 200) {
						vm.dataforxsls = res.data;
						vm.dataforxsls.forEach((el) => {
							el.price = parseFloat(el.price);
						});
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
			saveAs(new Blob([wbout], { type: 'application/octet-stream' }), 'oborot ' + this.selectedMonth + '.xlsx');
		},
	},
};
</script>
