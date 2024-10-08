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
					<v-text-field
						v-model="DaysOn"
						label="Dni na dostawę"
						type="number"
					></v-text-field>
				</v-col>
				<v-col>
					<v-btn
						@click="getQuantity()"
						:size="x - large"
						:disabled="loading"
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
						height="55vh"
						fixed-header
					>
					</v-data-table>
				</v-col>
			</v-row>
		</v-container>
		<v-snackbar
			v-model="snackbar"
			:timeout="4000"
			location="top"
		>
			{{ message }}

			<template v-slot:actions>
				<v-btn
					color="pink"
					variant="text"
					@click="snackbar = false"
				>
					<v-icon icon="mdi-close"></v-icon>
				</v-btn>
			</template>
		</v-snackbar>
	</div>
</template>

<script>
import Datepicker from 'vuejs3-datepicker';

import axios from 'axios';
import moment from 'moment';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';

export default {
	name: 'FulstorReportQuantity',
	components: {
		Datepicker,
	},
	data() {
		return {
			loading: false,
			dateMin: moment().format('YYYY-MM-01'),
			dateMax: moment().format('YYYY-MM-DD'),
			dataforxsls: [],
			warehouses: [],
			IDWarehouse: null,
			searchInTable: '',
			DaysOn: 21,
			headers: [
				{ title: 'Nazwa', key: 'Nazwa', nowrap: true },
				{ title: 'Kod Kreskowy', key: 'KodKreskowy' },
				{ title: 'SKU', key: 'sku' },
				{ title: 'Stan', key: 'stan', align: 'end' },
				{ title: 'Dni na dostawę', key: 'DaysOn', align: 'end' },
				{ title: 'Trend w %', key: 'tendent', align: 'end' },
				{ title: 'Days in stock', key: 'qtyNew', align: 'end' },
				{ title: 'Liczba sprzedaży w okresie', key: 'oborotNew', align: 'end' },
				{ title: 'Sprzedaż w dniu magazynowania', key: 'selonday', align: 'end' },
				{ title: 'Zamówienie', key: 'zamov', align: 'end' },
				{ title: 'Dni do końca', key: 'haveDay', align: 'end' },
			],
			message: '',
			snackbar: false,
		};
	},

	mounted() {
		this.getWarehouse();
	},

	methods: {
		getQuantity() {
			const vm = this;
			vm.loading = true;
			vm.message = '';
			let data = {};
			vm.dataforxsls = [];
			data.dataMin = vm.dateMin;
			data.dataMax = vm.dateMax;
			data.DaysOn = vm.DaysOn;
			data.IDMagazynu = vm.IDWarehouse;

			axios
				.post('/api/getQuantity', data)
				.then((res) => {
					if (res.status == 200) {
						vm.dataforxsls = res.data;
					} else {
						vm.message = res.data.message;
						vm.snackbar = true;
					}
					vm.loading = false;
				})
				.catch((error) => {
					console.log(error);
					vm.loading = false;
				});
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
		prepareXLSX() {
			// Создание новой книги
			// this.dataforxsls.forEach((el) => {
			// 	el.StanPoczatkowy = parseInt(el.StanPoczatkowy);
			// 	el.IlośćWchodząca = parseInt(el.IlośćWchodząca);
			// 	el.IlośćWychodząca = parseInt(el.IlośćWychodząca);
			// 	el.StanKoncowy = parseInt(el.StanKoncowy);
			// 	el.StanKoncowy = parseInt(el.StanKoncowy);

			// });
			const wb = XLSX.utils.book_new();
			const ws = XLSX.utils.json_to_sheet(this.dataforxsls);
			XLSX.utils.book_append_sheet(wb, ws, '');

			// Генерация файла и его сохранение
			const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
			saveAs(
				new Blob([wbout], { type: 'application/octet-stream' }),
				'quantity ' +
					moment(this.dateMin).format('YYYY-MM-DD') +
					'_' +
					moment(this.dateMax).format('YYYY-MM-DD') +
					'.xlsx',
			);
		},
	},
};
</script>
