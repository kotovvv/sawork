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
				><v-select
					v-model="selectedMonth"
					:items="month"
					item-title="name"
					item-value="id"
					label="miesiąc"
					persistent-hint
					single-line
				></v-select>
				<v-btn @click="getXLSX()">uzyskać XLSX</v-btn></v-col
			>
		</v-row>
	</v-container>
</template>
    <script>
import moment from 'moment';
import axios from 'axios';
export default {
	name: 'GetXLSX',

	data() {
		return {
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
		};
	},

	mounted() {
		this.getWarehouse();
		this.month = this.month.filter((m) => {
			return m.id <= this.curmonth;
		});
	},

	methods: {
		getXLSX() {
			const vm = this;
			let data = {};
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
						console.log(res.data);
					}
				})
				.catch((error) => console.log(error));
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
