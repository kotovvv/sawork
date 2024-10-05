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
						label="Дней на поставку"
						type="number"
					></v-text-field>
				</v-col>
				<v-col>
					<v-btn
						@click="getQuantity()"
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
						height="55vh"
						fixed-header
					>
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
		};
	},

	mounted() {
		this.getWarehouse();
	},

	methods: {
		getQuantity() {
			const vm = this;
			vm.loading = true;
			let data = {};
			vm.dataforxsls = [];
			data.dataMin = vm.dateMin;
			data.dataMax = vm.dateMax;
			data.IDMagazynu = vm.IDWarehouse;

			axios
				.post('/api/getOborot', data)
				.then((res) => {
					if (res.status == 200) {
						vm.dataforxsls = res.data;
						vm.dataforxsls.forEach((el) => {
							el.StanPoczatkowy = parseInt(el.StanPoczatkowy);
							el.IlośćWchodząca = parseInt(el.IlośćWchodząca);
							el.IlośćWychodząca = parseInt(el.IlośćWychodząca);
							el.StanKoncowy = parseInt(el.StanKoncowy);
							el.StanKoncowy = parseInt(el.StanKoncowy);

							if (vm.$attrs.user.IDRoli != 1) {
								delete el.WartośćPoczątkowa;
								delete el.WartośćWchodząca;
								delete el.WartośćWychodząca;
								delete el.WartośćKoncowa;
							}
						});
						vm.selected[0] = vm.dataforxsls[0].IDTowaru;
						vm.loading = false;
						if (vm.$attrs.user.IDRoli == 1) {
							vm.headers.push(
								{ title: 'Wartość Początkowa', key: 'WartośćPoczątkowa', align: 'end' },
								{ title: 'Wartość Wychodząca', key: 'WartośćWychodząca', align: 'end' },
								{ title: 'Wartość Wchodząca', key: 'WartośćWchodząca', align: 'end' },
								{ title: 'Wartość Koncowa', key: 'WartośćKoncowa', align: 'end' },
							);
						}
					}
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
			this.dataforxsls.forEach((el) => {
				el.StanPoczatkowy = parseInt(el.StanPoczatkowy);
				el.IlośćWchodząca = parseInt(el.IlośćWchodząca);
				el.IlośćWychodząca = parseInt(el.IlośćWychodząca);
				el.StanKoncowy = parseInt(el.StanKoncowy);
				el.StanKoncowy = parseInt(el.StanKoncowy);
				// el.WartośćPoczątkowa = parseFloat(el.WartośćPoczątkowa);
				// el.WartośćWchodząca = parseFloat(el.WartośćWchodząca);
				// el.WartośćWychodząca = parseFloat(el.WartośćWychodząca);
				// el.WartośćKoncowa = parseFloat(el.WartośćKoncowa);
			});
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
