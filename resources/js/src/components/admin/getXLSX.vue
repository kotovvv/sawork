<template>
	<v-container>
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
					@change="clear()"
					hide-details="auto"
				></v-select>
			</v-col>
			<v-col
				md="6"
				sm="12"
			>
				<div class="d-flex">
					<v-select
						v-model="selectedMonth"
						:items="month"
						item-title="name"
						item-value="id"
						label="miesiąc"
						persistent-hint
						single-line
					></v-select>
					<v-btn @click="getDataForXLS()">uzyskać XLSX</v-btn></div
				></v-col
			>
		</v-row>
		<button @click="prepareXLSX">Экспорт в Excel</button>
		<v-progress-linear
			:active="loading"
			indeterminate
			color="purple"
		></v-progress-linear>
	</v-container>
</template>

<script>
import moment from 'moment';
import axios from 'axios';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';

export default {
	name: 'GetXLSX',

	data() {
		return {
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
			IDWarehouse: null,
			warehouses: [],
			curyear: moment().year(),
			curmonth: moment().month(),
			curdate: moment().date(),
			dataforxsls: [],
		};
	},

	mounted() {
		this.getWarehouse();
		this.month = this.month.filter((m) => {
			return m.id <= this.curmonth;
		});
		if (this.curmonth == 0) {
			this.month.unshift({ id: 11, name: '12 grudzień' });
		}
	},

	methods: {
		prepareXLSX() {
			// Пример данных с сервера
			const data = [
				{
					name: 'Sheet1',
					data: [
						['Name', 'Age'],
						['Alice', 30],
						['Bob', 25],
					],
				},
				{
					name: 'Sheet2',
					data: [
						['Product', 'Price'],
						['Apple', 1.2],
						['Banana', 0.8],
					],
				},
			];

			// Создание новой книги
			const wb = XLSX.utils.book_new();

			// Добавление данных на отдельные листы
			data.forEach((sheet) => {
				const ws = XLSX.utils.aoa_to_sheet(sheet.data);
				XLSX.utils.book_append_sheet(wb, ws, sheet.name);
			});

			// Генерация файла и его сохранение
			const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
			saveAs(new Blob([wbout], { type: 'application/octet-stream' }), 'data.xlsx');
		},
		getDataForXLS() {
			const vm = this;
			let data = {};
			vm.loading = true;
			data.IDWarehouse = vm.IDWarehouse;
			data.month = vm.selectedMonth;
			data.year = vm.curyear;
			if (vm.curmonth == 0 && vm.selectedMonth == 11) {
				data.year = vm.curyear - 1;
			}
			axios
				.post('/api/getDataForXLS', data)
				.then((res) => {
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
		getWarehouse() {
			const vm = this;
			axios
				.get('/api/getWarehouse')
				.then((res) => {
					if (res.status == 200) {
						vm.warehouses = res.data;
					}
				})
				.catch((error) => console.log(error));
		},
	},
};
</script>
