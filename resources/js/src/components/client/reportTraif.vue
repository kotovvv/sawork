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
					<v-select
						v-model="selectedMonth"
						:items="month"
						item-title="name"
						item-value="id"
						label="miesiąc"
						persistent-hint
						single-line
					></v-select
				></v-col>
				<v-col>
					<v-btn
						@click="getReportTarif()"
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
					<v-data-table-virtual :items="dataforxsls"></v-data-table-virtual>
				</v-col>
			</v-row>
		</v-container>
	</div>
</template>

<script>
import moment from 'moment';

import axios from 'axios';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
export default {
	name: 'reportTarif',
	data: () => ({
		loading: false,
		selectedMonth: moment().month(),
		month: [
			{ id: 0, name: '01 styczeń' },
			{ id: 1, name: '02 luty' },
			{ id: 2, name: '03 marzec' },
			{ id: 3, name: '04 kwiecień' },
			{ id: 4, name: '05 maj' },
			{ id: 5, name: '06 czerwiec' },
			{ id: 6, name: '07 lipiec' },
			{ id: 7, name: '08 sierpień' },
			{ id: 8, name: '09 wrzesień' },
			{ id: 9, name: '10 październik' },
			{ id: 10, name: '11 listopad' },
			{ id: 11, name: '12 grudzień' },
		],
		dataforxsls: [],
		warehouses: [],
		IDWarehouse: null,
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
						vm.IDWarehouse = vm.warehouses[0].IDMagazynu;
					}
				})
				.catch((error) => console.log(error));
		},

		getReportTarif() {
			const vm = this;
			vm.loading = true;
			axios
				.get('/api/getReportTarif/' + vm.selectedMonth + '/' + vm.IDWarehouse)
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
			saveAs(new Blob([wbout], { type: 'application/octet-stream' }), 'Tarif ' + this.selectedMonth + '.xlsx');
		},
	},
};
</script>
