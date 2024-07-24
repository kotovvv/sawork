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
					<v-data-table-virtual :items="dataforxsls"></v-data-table-virtual>
				</v-col>
			</v-row>
		</v-container>
	</div>
</template>
<script>
import axios from 'axios';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
export default {
	data: () => ({
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
			axios
				.get('/api/getDataNotActivProduct/' + vm.days + '/' + vm.IDWarehouse)
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
		prepareXLSX() {
			let all = [];
			let sum = 0;
			// Создание новой книги
			const wb = XLSX.utils.book_new();

			// get sum
			this.dataforxsls.forEach((sheet) => {
				let m3 = sheet[1].reduce((acc, o) => acc + parseFloat(o.m3xstan), 0);
				sum += parseFloat(m3 * 2.1);
				all.push({ day: sheet[0], m3: m3, zl: m3 * 2.1 });
			});
			all.push({ day: 'Итого', m3: '', zl: sum });

			const ws = XLSX.utils.json_to_sheet(all);
			XLSX.utils.book_append_sheet(wb, ws, 'Итого');

			this.dataforxsls.forEach((sheet) => {
				sheet[1].forEach((item) => {
					item.stan = parseFloat(item.stan);
					item.Wartosc = parseFloat(item.Wartosc);
					item.m3xstan = parseFloat(item.m3xstan);
				});
				const ws = XLSX.utils.json_to_sheet(sheet[1]);
				XLSX.utils.book_append_sheet(wb, ws, sheet[0]);
			});

			// Генерация файла и его сохранение
			const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
			saveAs(
				new Blob([wbout], { type: 'application/octet-stream' }),
				'Зберігання ' + this.date.toLocaleString().substring(0, 10).replaceAll('.', '_') + '.xlsx',
			);
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
