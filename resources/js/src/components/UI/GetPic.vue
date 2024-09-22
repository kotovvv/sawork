<template>
	<div>
		<div
			id="qr-reader"
			style="max-width: 500px"
		></div>
		<v-row class="mt-2">
			<v-btn
				@click="takePhoto"
				icon="mdi-checkbox-blank-circle"
				color="red"
			>
			</v-btn>
			<v-spacer></v-spacer>
			<v-btn
				@click="$emit('close')"
				icon="mdi-close"
			></v-btn>
		</v-row>
		<canvas
			ref="canvas"
			style="display: none"
		></canvas>
	</div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';

export default {
	name: 'GetPic',
	setup(_, { emit }) {
		const qrCodeReader = ref(null);
		const canvas = ref(null);

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

		const takePhoto = () => {
			const videoElement = document.querySelector('#qr-reader video');
			if (videoElement) {
				const canvasElement = canvas.value;
				const context = canvasElement.getContext('2d');
				canvasElement.width = videoElement.videoWidth;
				canvasElement.height = videoElement.videoHeight;
				context.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);

				const photoData = canvasElement.toDataURL('image/png');
				emit('result', { type: 'photo', data: photoData });
			}
		};

		return { canvas, takePhoto };
	},
};
</script>

<style scoped>
#qr-reader {
	border: 1px solid #ccc;
	margin: 10px 0;
}
</style>
