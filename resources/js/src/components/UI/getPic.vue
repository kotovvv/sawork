<!-- <template>
	<div>
		<div
			id="qr-reader"
			style="width: 500px"
		></div>
		<p v-if="qrCodeMessage">Результат QR-кода: {{ qrCodeMessage }}</p>
	</div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';

export default {
	setup() {
		const qrCodeMessage = ref(null);
		const qrCodeReader = ref(null);

		onMounted(() => {
			const qrReader = new Html5Qrcode('qr-reader');
			qrCodeReader.value = qrReader;

			qrReader
				.start(
					{ facingMode: 'environment' }, // Камера по умолчанию (можно поменять на "user" для фронтальной камеры)
					{
						fps: 10, // Частота кадров
						qrbox: 250, // Размер области для сканирования
					},
					(decodedText) => {
						qrCodeMessage.value = decodedText;
					},
					(errorMessage) => {
						console.error(`QR Error: ${errorMessage}`);
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

		return { qrCodeMessage };
	},
};
</script>

<style scoped>
#qr-reader {
	border: 1px solid #ccc;
	margin: 10px 0;
}
</style> -->

<template>
	<div>
		<div
			id="qr-reader"
			style="width: 500px"
		></div>
		<button @click="takePhoto">Сделать фото</button>
		<canvas
			ref="canvas"
			style="display: none"
		></canvas>
		<div v-if="photo">
			<h3>Ваше фото:</h3>
			<img
				:src="photo"
				alt="Сделанное фото"
			/>
		</div>
	</div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';

export default {
	setup() {
		const qrCodeReader = ref(null);
		const canvas = ref(null);
		const photo = ref(null);
		const videoElement = ref(null);

		onMounted(() => {
			const qrReader = new Html5Qrcode('qr-reader');
			qrCodeReader.value = qrReader;

			qrReader
				.start(
					{ facingMode: 'environment' }, // Камера по умолчанию
					{
						fps: 10, // Частота кадров
						// qrbox: 250, // Размер области для сканирования
					},
					(decodedText) => {
						console.log(`QR код прочитан: ${decodedText}`);
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

				// Получаем изображение в виде data URL
				photo.value = canvasElement.toDataURL('image/png');
			}
		};

		return { canvas, photo, takePhoto };
	},
};
</script>

<style scoped>
#qr-reader {
	border: 1px solid #ccc;
	margin: 10px 0;
}
</style>

