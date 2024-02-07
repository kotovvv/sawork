<template>
	<div class="container">
		<div class="row">
			<div class="col">
				<div>Magazyny</div>
				<div
					v-for="m in warehouses"
					:key="m.IDMagazynu"
					class="form-check form-check-inline mb-4"
				>
					<input
						type="radio"
						id="i{{m.IDMagazynu}}"
						name="wh"
						:value="m.IDMagazynu"
						v-model="IDWarehouse"
					/>
					<label for="m{{ m.IDMagazynu }}">{{ m.Symbol }}</label>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col">
				<label
					class="form-label"
					for="getorder"
					>Order {{ (order.Number ?? '') + ' ' + (order.Created ?? '') }}</label
				>
				<input
					class="form-control"
					v-model="ordername"
					id="getorder"
					@keyup.enter="getOrder()"
				/>
			</div>
		</div>
		<div
			class="row"
			v-if="order.Number"
		>
			<div class="col">
				<div class="my-3">Products</div>
				<div
					class="products"
					v-for="p in products"
					:key="p.IDOrderLine"
				>
					<div class="product">
						{{ p.IDItem + ' ' + p.Quantity }}
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
<script>
import axios from 'axios';
export default {
	name: 'MainComponent',

	data() {
		return {
			ordername: '',
			order: {},
			warehouses: [],
			IDWarehouse: null,
			products: [],
		};
	},

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
		getOrder() {
			const vm = this;
			vm.order = {};
			axios
				.get('/api/getOrder' + '/' + vm.IDWarehouse + '/' + vm.ordername)
				.then((res) => {
					if (res.status == 200) {
						vm.order = res.data.info[0];
						vm.products = res.data.products ?? [];
					} else {
						vm.order = {};
					}
				})
				.catch((error) => console.log(error));
		},
	},
};
</script>
