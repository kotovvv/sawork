<template>
	<div class="container">
		<div class="row">
			<div class="col">
				<h2>Magazyny</h2>
			</div>
		</div>

		{{ test }}
	</div>
</template>
<script>
import axios from 'axios';
export default {
	name: 'MainComponent',

	data() {
		return {
			test: 'test 111111111111111',
			warehouses: [],
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
					vm.warehouses = res.data;
				})
				.catch((error) => console.log(error));
		},
	},
};
</script>
