<template>
	<div>
		<h1>Received Data</h1>
		<div v-if="qrCodeMessage">
			<h2>QR Code Result:</h2>
			<p>{{ qrCodeMessage }}</p>
		</div>
		<div v-if="photo">
			<h2>Photo:</h2>
			<img
				:src="photo"
				alt="Received Photo"
				style="width: 100%; max-width: 600px"
			/>
		</div>
	</div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import eventBus from '@/eventBus';

export default {
	setup() {
		const qrCodeMessage = ref(null);
		const photo = ref(null);

		const handleQrCodeScanned = (data) => {
			qrCodeMessage.value = data;
		};

		const handlePhotoTaken = (data) => {
			photo.value = data;
		};

		onMounted(() => {
			eventBus.on('qrCodeScanned', handleQrCodeScanned);
			eventBus.on('photoTaken', handlePhotoTaken);
		});

		onUnmounted(() => {
			eventBus.off('qrCodeScanned', handleQrCodeScanned);
			eventBus.off('photoTaken', handlePhotoTaken);
		});

		return { qrCodeMessage, photo };
	},
};
</script>

<style scoped>
img {
	border: 1px solid #ccc;
	margin: 10px 0;
}
</style>
