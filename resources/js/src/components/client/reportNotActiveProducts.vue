<template>
	<div>
		<div class="d-flex justify-center">
			<v-text-field
				v-model.number="days"
				hide-details
				single-line
				min="1"
				type="number"
				width="300"
				max-width="400"
				@keypress="filter()"
			/>
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
			>
		</div>
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
	}),
	methods: {
		getDataNotActivProduct() {
			const vm = this;
			vm.loading = true;
			axios
				.get('/api/getDataNotActivProduct/' + vm.days)
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
