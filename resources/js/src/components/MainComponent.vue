<template>
	<div>
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
					vm.test = res.data;
				})
				.catch((error) => console.log(error));
		},
	},
};
</script>
