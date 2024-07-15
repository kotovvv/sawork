<template>
	<div>
		<v-btn
			class="btn primary"
			@click="sendPDF()"
			>Wyślij PDF</v-btn
		>
		<v-snackbar
			v-model="snackbar"
			vertical
		>
			<p>{{ message }}</p>

			<template v-slot:actions>
				<v-btn
					color="indigo"
					variant="text"
					@click="
						snackbar = false;
						message = '';
					"
				>
					Close
				</v-btn>
			</template>
		</v-snackbar>
	</div>
</template>
<script>
export default {
	name: 'sendPDF',
	methods: {
		data() {
			return {
				snackbar: false,
				message: '',
			};
		},
		sendPDF() {
			const self = this;
			axios
				.get('/api/sendPDF')
				.then((response) => {
					self.message = response.data;
					self.snackbar = true;
				})
				.catch((error) => {
					console.log(error);
				});
		},
	},
};
</script>
