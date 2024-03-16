<template>
	<div class="container">
		<section class="row my-3">
			<div class="col"
				>Magazyny
				<span id="select-magazynu">
					<select
						v-model="IDWarehouse"
						class="form-select"
					>
						<template
							v-for="m in warehouses"
							:key="m.IDMagazynu"
						>
							<option :value="m.IDMagazynu">{{ m.Nazwa }}</option>
						</template>
					</select>
				</span>
			</div>
		</section>
		<!-- Order -->
		<section class="row">
			<div class="col">
				<label
					class="form-check-label mb-2"
					for="getorder"
					><b>Order: </b>
					<span v-if="order.Number">{{
						(order.Number ?? '') + ' [' + (order.Created ?? '') + '] - ' + order.cName ?? ''
					}}</span>
					<span
						v-if="order_mes"
						style="color: red"
						>{{ order_mes }}</span
					></label
				>
				<input
					class="form-control"
					v-model="ordername"
					id="getorder"
					@keyup.enter="getOrder()"
				/>
			</div>
		</section>
		<!-- Products -->
		<section
			class="row"
			v-if="products.length"
		>
			<div class="col">
				<b class="mb-2 mt-3">Produkty</b>
				<input
					class="form-control mb-3"
					v-model="imputProduct"
					ref="imputproduct"
					@keyup.enter="findProduct()"
					placeholder="towar"
				/>
				<div class="row mb-3">
					<div
						class="qty input-group"
						v-if="edit.id > 0"
					>
						<div class="col-12 mb-2">{{ edit.Nazwa }}</div>
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
					v-for="(p, i) in products"
					:key="p.IDOrderLine"
				>
					<div
						class="product"
						@click="editProduct(p, 0)"
					>
						{{ i + 1 }}.<img
							v-if="p.img"
							:src="'data:image/jpeg;base64,' + p.img"
							alt="pic"
							style="height: 3em"
						/>

						{{ p.Nazwa + ' ' + parseInt(p.Quantity)
						}}<span
							v-if="p.qty"
							style="color: green"
							>{{ ' -' + p.qty + ' ' + p.message }}</span
						>
					</div>
				</div>
			</div>
		</section>
		<section
			class="row"
			v-if="products.find((e) => e.qty > 0)"
		>
			<div class="col">
				<button class="btn btn-primary my-3">Zapisz dokument</button>
			</div>
		</section>
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
			order_mes: '',
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
			vm.order_mes = '';
			vm.order = {};
			let data = {};
			data.warehouse = vm.IDWarehouse;
			data.ordername = vm.ordername;
			axios
				.post('/api/getOrder', data)
				.then((res) => {
					if (res.status == 200) {
						if (res.data.info) {
							vm.order = res.data.info;
							vm.products = res.data.products ?? [];
							if (vm.products.length) {
								vm.products.map((e) => {
									e.qty = '';
									e.message = '';
								});
								vm.focusOnProduct();
							} else {
								vm.order_mes = 'Nie ma takiej kolejności';
								vm.products = [];
							}
						} else {
							vm.order_mes = 'Nie ma takiej kolejności';
							vm.products = [];
						}
					} else {
						vm.order_mes = 'Nie ma takiej kolejności';
						vm.products = [];
					}
				})
				.catch((error) => console.log(error));
		},
		findProduct() {
			// KodKreskowy - штрихкод
			// [_TowarTempString1] - артикул
			const product = this.products.find(
				(e) =>
					e.IDOrderLine == this.imputProduct ||
					e.KodKreskowy == this.imputProduct ||
					e['_TowarTempString1'] == this.imputProduct,
			);
			if (product) {
				this.editProduct(product, 1);
			} else {
				this.edit.id = 0;
				alert('Brak produktu!!!');
			}
		},

		changeCounter: function (num) {
			this.edit.qty += +num;
			this.edit.qty = this.edit.qty < this.edit.max ? this.edit.qty : this.edit.max;
			!isNaN(this.edit.qty) && this.edit.qty > 0 ? this.edit.qty : (this.edit.qty = 0);
		},
		saveEdit() {
			const vm = this;
			vm.edit.qty = vm.edit.qty < vm.edit.max ? vm.edit.qty : vm.edit.max;
			vm.edit.qty = vm.edit.qty == 0 ? '' : vm.edit.qty;
			vm.edit.message = vm.edit.qty == 0 ? '' : vm.edit.message;
			this.products = this.products.map((x) =>
				x.IDOrderLine === vm.edit.id ? { ...x, qty: vm.edit.qty, message: vm.edit.message } : x,
			);
			// this.products.sort((a.qty, b.qty) => a.qty - b.qty);
			this.edit.id = 0;
		},
		editProduct(product, add) {
			this.edit.Nazwa = product.Nazwa;
			this.edit.id = product.IDOrderLine;
			this.edit.qty = product.qty != '' ? product.qty + add : 1;
			this.edit.message = product.message;
			this.edit.max = parseInt(product.Quantity);
		},
		focusOnProduct() {
			this.$nextTick(() => {
				this.$refs.imputproduct.focus();
			});
		},
	},
};
</script>
<style lang="scss">
.wrap_product {
	max-height: 70vh;
	overflow-y: auto;
}
.product {
	cursor: pointer;
	border-bottom: 1px solid #ccc;
	display: flex;
	gap: 1rem;
}
.product:hover {
	background: #ccc;
}
</style>
