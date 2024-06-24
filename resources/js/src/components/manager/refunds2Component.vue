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
				><v-text-field
					label="Dokument"
					v-model="ordername"
					id="getorder"
					@keyup.enter="getOrder()"
					hide-details="auto"
				></v-text-field
			></v-col>
		</v-row>

		<!-- Order -->
		<v-row>
			<v-col cols="12">
				<label for="getorder"
					><b>Order: </b>
					<span v-if="order.Number">{{
						(order.Number ?? '') + ' - ' + order.pk + ' (' + (order.Created ?? '') + ') - ' + order.cName ??
						''
					}}</span>
					<span
						v-if="order_mes"
						style="color: red"
						>{{ order_mes }}</span
					></label
				></v-col
			>
		</v-row>

		<!-- Products -->
		<ConfirmDlg ref="confirm" />
		<v-dialog
			v-model="dialogMessageQty"
			width="auto"
		>
			<v-card
				width="600"
				prepend-icon="mdi-pencil"
			>
				<v-card-text>
					<v-text-field
						label="Wiadomość"
						v-model="edit.message"
					></v-text-field>
					<v-text-field
						label="Ilość"
						v-model="edit.qty"
					></v-text-field>
				</v-card-text>
				<template v-slot:actions>
					<v-btn
						class="ms-auto"
						text="Ok"
						@click="dialogMessageQty = false"
					></v-btn>
				</template>
			</v-card>
		</v-dialog>
		<v-dialog
			v-model="dialog"
			width="auto"
		>
			<v-card
				max-width="600"
				prepend-icon="mdi-alert-outline"
				:text="dialog_text"
				:title="dialog_title"
			>
				<template v-slot:actions>
					<v-btn
						class="ms-auto"
						text="Ok"
						@click="
							dialog = false;
							$refs.dProduct.focus;
						"
					></v-btn>
				</template>
			</v-card>
		</v-dialog>
		<v-dialog
			id="dialogProduct"
			ref="dProduct"
			v-model="dialogProduct"
			transition="dialog-bottom-transition"
			fullscreen
			@keyup="handleKeypress"
		>
			<v-container>
				<v-card>
					<v-card-title class="mb-5 bg-grey-lighten-3">
						<v-row>
							<v-col>
								<b>Order: </b>
								<span v-if="order.Number">{{
									(order.Number ?? '') +
										' - ' +
										order.pk +
										' (' +
										(order.Created ?? '') +
										') - ' +
										order.cName ?? ''
								}}</span>
								<span
									v-if="order_mes"
									style="color: red"
									>{{ order_mes }}</span
								></v-col
							>
							<v-spacer></v-spacer>

							<v-btn
								icon="mdi-close"
								@click="
									dialogProduct = false;
									text = '';
								"
							></v-btn
						></v-row>
					</v-card-title>

					<v-card-text class="vscroll">
						<div class="d-flex flex-column">
							<v-row
								class="product_line border my-0"
								v-for="p in products"
								:key="p.IDTowaru"
								:class="{
									'active': p.IDTowaru == edit.IDTowaru,
									'error': p.qty > p.Quantity,
									'green-lighten-4': p.qty == p.Quantity,
									'order-1': p.IDTowaru == edit.IDTowaru,
									'order-4': p.qty == p.Quantity,
									'order-0': p.qty > p.Quantity,
									'order-3': p.qty == 0,
								}"
							>
								<v-col>
									<div class="d-flex">
										<img
											v-if="p.img"
											:src="'data:image/jpeg;base64,' + p.img"
											alt="pic"
											style="height: 3em"
										/>
										<span
											><h5
												>{{ p.Nazwa }}<br />cod: {{ p.KodKreskowy }}, sku:
												{{ p._TowarTempString1 }}</h5
											>
										</span>
										<v-btn
											@click="
												edit = p;
												dialogMessageQty = true;
											"
											><v-icon>mdi-pencil</v-icon></v-btn
										>
									</div>
								</v-col>

								<v-col>
									<div class="d-flex justify-end">
										<v-btn @click="changeCounter(p, -1)">-</v-btn>
										<div
											:id="p.IDTowaru"
											class="border qty text-h5 text-center"
										>
											{{ p.qty }} z {{ parseInt(p.Quantity) }}</div
										>
										<v-btn @click="changeCounter(p, 1)">+</v-btn>
									</div>
								</v-col>
							</v-row>
						</div>
					</v-card-text>
					<template v-slot:actions>
						<v-spacer></v-spacer>
						<!-- v-if="products.find((e) => e.qty > 0)" -->
						<section class="row">
							<div class="col">
								<p>Niepełnowartościowe</p>
								<label
									><input
										type="radio"
										v-model="full"
										value="0"
									/>Nie</label
								><br />
								<label
									><input
										type="radio"
										v-model="full"
										value="1"
									/>Tak</label
								>
							</div>
						</section>
						<button
							class="btn btn-primary my-3"
							:class="{
								disabled:
									products.find((e) => e.qty > e.Quantity) ||
									products.reduce((ak, el) => ak + el.qty, 0) == 0,
							}"
							@click="checkFullOrder()"
							>Tworzenie dokumentu zwrotu</button
						>
					</template>
				</v-card>
			</v-container>
		</v-dialog>
	</v-container>
</template>

<script>
import axios from 'axios';
import ConfirmDlg from '../UI/ConfirmDlg.vue';
export default {
	name: 'Refund',
	components: {
		ConfirmDlg,
	},
	data() {
		return {
			dialog: false,
			dialogProduct: false,
			dialogMessageQty: false,
			dialog_text: '',
			dialog_title: '',

			full: 0,
			ordername: '',
			order: {},
			wz: {},
			warehouses: [],
			IDWarehouse: null,
			products: [],
			changeProducts: [],
			edit: {
				id: 0,
				Nazwa: '',
				qty: 0,
				message: '',
				max: 1,
			},
			order_mes: '',
			imputCod: '',
			text: '',
		};
	},

	mounted() {
		this.getWarehouse();
	},

	methods: {
		handleKeypress(event) {
			// Check if the Enter key was pressed
			if (event.key === 'Enter') {
				// Execute the function with the accumulated input
				this.findProduct();
				// Clear the input field
				this.imputCod = '';
			} else {
				// Append the current keystroke to the input
				this.imputCod += event.key;
			}
		},

		clear() {
			this.order = {};
			this.wz = {};
			this.products = [];
			this.ordername = '';
			this.imputCod = '';
		},
		async ConfirmFullOrder() {
			if (await this.$refs.confirm.open('Zwrot jest niekompletny!', 'Czy zwrot jest na pewno niekompletny?')) {
				this.doWz();
			} else {
				this.dialogProduct = true;
			}
		},
		checkFullOrder() {
			let sQty = this.products.reduce((acc, el) => acc + parseInt(el.qty), 0);
			let sQua = this.products.reduce((acc, el) => acc + parseInt(el.Quantity), 0);
			if (sQty != sQua) {
				this.dialogProduct = false;
				this.ConfirmFullOrder();
			} else {
				this.doWz();
			}
		},
		doWz() {
			const vm = this;
			let data = {};
			let ps = vm.products.filter((e) => e.qty > 0);
			ps = ps.map((t) => {
				return ['IDTowaru', 'CenaJednostkowa', 'message', 'qty'].reduce((a, e) => ((a[e] = t[e]), a), {});
			});
			data.magazin = vm.warehouses.filter((m) => m.IDMagazynu == vm.IDWarehouse)[0];
			data.wz = vm.wz;
			data.products = ps;
			data.order_id = vm.order.IDOrder;
			data.full = vm.full;
			axios
				.post('/api/doWz', data)
				.then((res) => {
					if (res.status == 200) {
						vm.clear();
						vm.order_mes = res.data;
						vm.dialogProduct = false;
					} else {
						vm.order_mes = res.data;
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
						vm.IDWarehouse = vm.warehouses[0].IDMagazynu;
					}
				})
				.catch((error) => console.log(error));
		},
		getOrder() {
			const vm = this;
			if (vm.ordername == '') {
				vm.clear();
				return;
			}
			vm.order_mes = '';
			vm.order = {};
			let data = {};
			data.warehouse = vm.IDWarehouse;
			data.ordername = vm.ordername;
			axios
				.post('/api/getOrder', data)
				.then((res) => {
					if (res.status == 200) {
						if (res.data.wz) {
							vm.order = res.data.order ?? {};
							vm.wz = res.data.wz ?? {};
							vm.products = Object.values(res.data.products) ?? [];
							if (vm.products.length) {
								vm.products.map((e) => {
									e.qty = 0;
									e.message = '';
								});
								vm.dialogProduct = true;

								// vm.focusOnProduct();
							} else {
								vm.order_mes = 'Nie ma takiej kolejności';
								vm.clear();
							}
						} else {
							vm.order_mes = 'Nie WZ';
							vm.clear();
						}
					} else if (res.status == 202) {
						vm.order_mes = res.data;
						vm.clear();
					} else {
						vm.order_mes = 'Error getOrder()';
						vm.clear();
					}
				})
				.catch((error) => console.log(error));
		},
		findProduct() {
			// KodKreskowy - штрихкод
			// [_TowarTempString1] - артикул, sku
			const product = this.products.find(
				(e) =>
					e.IDTowaru == this.imputCod ||
					e.KodKreskowy == this.imputCod ||
					e['_TowarTempString1'] == this.imputCod,
			);
			if (product) {
				this.edit = product;
				this.changeCounter(product, 1);
			} else {
				this.dialog_text = 'Brak produktu!!!';
				this.dialog = true;
			}
		},

		changeCounter: function (item, num) {
			item.qty += +num;
			if (item.qty < 0) item.qty = 0;
			this.$refs.dProduct.focus;
		},
		saveEdit() {
			const vm = this;
			vm.edit.qty = vm.edit.qty < vm.edit.max ? vm.edit.qty : vm.edit.max;
			vm.edit.qty = vm.edit.qty == 0 ? '' : vm.edit.qty;
			vm.edit.message = vm.edit.qty == 0 ? '' : vm.edit.message;
			this.products = this.products.map((x) =>
				x.IDTowaru === vm.edit.id ? { ...x, qty: vm.edit.qty, message: vm.edit.message } : x,
			);
			// this.products.sort((a.qty, b.qty) => a.qty - b.qty);
			this.edit.id = 0;
			this.imputCod = '';
		},
	},
};
</script>

<style lang="scss">
.qty {
	width: 150px;
}
.active .qty {
	background: #e0e0e0;
}
.error .qty {
	background: #ffcdd2;
}
.vscroll {
	max-height: 70vh;
	overflow-y: auto;
}
</style>
