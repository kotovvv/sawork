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
						class="form-check-input"
					/>
					<label
						for="m{{ m.IDMagazynu }}"
						class="form-check-label"
						>{{ m.Nazwa }}</label
					>
				</div>
			</div>
		</div>
		<!-- Order -->
		<div class="row">
			<div class="col">
				<label
					class="form-check-label"
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
		<!-- Products -->
		<div
			class="row"
			v-if="order.Number"
		>
			<div class="col">
				<div class="my-3">Products</div>
				<input
					class="form-control mb-3"
					v-model="imputProduct"
					id="imputproduct"
					@keyup.enter="changeProduct()"
					placeholder="towar"
				/>
				<div class="row mb-3">
					<div
						class="qty input-group"
						v-if="edit.id > 0"
					>
						<div class="pname">{{ edit.Nazwa }}</div>
						<span class="input-group-btn">
							<button
								class="btn btn-light"
								@click="changeCounter('-1')"
								type="button"
								name="button"
							>
								<span>-</span>
							</button>
						</span>
						<input
							class="text-center"
							v-model.number="edit.qty"
							min="0"
							:max="edit.max"
							style="max-width: 3rem"
						/>
						<span class="input-group-btn">
							<button
								class="btn btn-light"
								@click="changeCounter('1')"
								type="button"
								name="button"
							>
								<span>+</span>
							</button>
						</span>
						<input
							class="col-7 form-control"
							v-model="edit.message"
							id="message"
							placeholder="message"
						/>
						<span class="input-group-btn">
							<button
								class="btn btn-primary"
								@click="saveEdit()"
								type="button"
							>
								<span>Save</span>
							</button>
						</span>
					</div>
				</div>

				<div
					class="products"
					v-for="p in products"
					:key="p.IDOrderLine"
				>
					<div
						class="product"
						@click="editProduct(p)"
					>
						{{ p.Nazwa + ' ' + parseInt(p.Quantity) + ' -' + p.qty + ' ' + p.message }}
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
			changeProducts: [],
			edit: {
				id: 0,
				Nazwa: '',
				qty: 0,
				message: '',
				max: 0,
			},
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
						if (vm.products.length) {
							vm.products.map((e) => {
								e.qty = '';
								e.message = '';
							});
						}
					} else {
						vm.order = {};
					}
				})
				.catch((error) => console.log(error));
		},
		changeCounter: function (num) {
			this.edit.qty += +num;
			this.edit.qty = this.edit.qty < this.edit.max ? this.edit.qty : this.edit.max;
			!isNaN(this.edit.qty) && this.edit.qty > 0 ? this.edit.qty : (this.edit.qty = 0);
		},
		saveEdit() {
			const vm = this;
			vm.edit.qty = vm.edit.qty < vm.edit.max ? vm.edit.qty : vm.edit.max;
			vm.edit.qty = vm.edit.qty == 0 ? vm.edit.qty : '';
			vm.edit.message = vm.edit.qty == 0 ? vm.edit.message : '';
			this.products = this.products.map((x) =>
				x.IDOrderLine === vm.edit.id ? { ...x, qty: vm.edit.qty, message: vm.edit.message } : x,
			);
			// this.products.sort((a.qty, b.qty) => a.qty - b.qty);
			this.edit.id = 0;
		},
		editProduct(product) {
			console.log(product);
			this.edit.id = product.IDOrderLine;
			this.edit.qty = product.qty;
			this.edit.message = product.message;
			this.edit.max = parseInt(product.Quantity);
		},
	},
};
</script>
