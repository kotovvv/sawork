<template>
	<div>
		<div
			id="qr-reader"
			style="max-width: 500px"
		></div>
		<v-row class="mt-2">
			<v-btn
				@click="$emit('close')"
				icon="mdi-close"
			></v-btn>
		</v-row>
	</div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';

export default {
	name: 'QrCodeScanner',
	setup(_, { emit }) {
		const qrCodeReader = ref(null);

		onMounted(() => {
			const qrReader = new Html5Qrcode('qr-reader');
			qrCodeReader.value = qrReader;

			qrReader
				.start(
					{ facingMode: 'environment' },
					{
						fps: 10,
					},
					(decodedText) => {
						console.log(`QR код прочитан: ${decodedText}`);
						emit('result', { type: 'qrCode', data: decodedText });
						emit('close');
					},
					(errorMessage) => {
						console.error(`Ошибка QR: ${errorMessage}`);
					},
				)
				.catch((err) => {
					console.error('Ошибка при запуске камеры: ', err);
				});
		});

		onUnmounted(() => {
			if (qrCodeReader.value) {
				qrCodeReader.value.stop().catch((err) => {
					console.error('Ошибка при остановке камеры: ', err);
				});
			}
		});

		return {};
	},
};
</script>

<style scoped>
#qr-reader {
	border: 1px solid #ccc;
	margin: 10px 0;
}
</style>
