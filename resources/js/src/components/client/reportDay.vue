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
					<v-date-input
						v-model="date"
						label="Select a date"
						width="368"
						max-width="400"
						first-day-of-week="1"
						keyboardDate
						location="pl-PL"
					></v-date-input
				></v-col>
				<v-col>
					<v-btn
						@click="getDataForXLSDay()"
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
					<v-data-table-virtual
						:items="dataforxsls[0][1]"
						height="400"
					></v-data-table-virtual>
				</v-col>
			</v-row>
		</v-container>
	</div>
</template>

<script>
import { VDateInput } from 'vuetify/labs/VDateInput';
import axios from 'axios';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
export default {
	name: 'reportDay',
	components: {
		VDateInput,
	},
	data: () => ({
		loading: false,
		date: new Date(),
		dataforxsls: [],
		IDWarehouse: null,
		warehouses: [],
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
		getDataForXLSDay() {
			const vm = this;
			vm.loading = true;
			axios
				.get('/api/getDataForXLSDay/' + vm.date.toDateString() + '/' + vm.IDWarehouse)
				.then((res) => {
					if (res.status == 200) {
						vm.dataforxsls = Object.entries(res.data);
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

			if (this.$attrs.user.IDRoli == '1') {
				// get sum
				this.dataforxsls.forEach((sheet) => {
					let m3 = sheet[1].reduce((acc, o) => acc + parseFloat(o.m3xstan), 0);
					sum += parseFloat(m3 * 2.1);
					all.push({ day: sheet[0], m3: m3, zl: m3 * 2.1 });
				});
				all.push({ day: 'Итого', m3: '', zl: sum });

				const ws = XLSX.utils.json_to_sheet(all);
				XLSX.utils.book_append_sheet(wb, ws, 'Итого');
			}

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
	},
};
</script>
