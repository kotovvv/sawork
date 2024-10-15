<template>
	<div>
		<v-row class="mt-2">
			<v-btn
				@click="$emit('close')"
				icon="mdi-close"
			></v-btn>
			<v-spacer></v-spacer>
			<v-btn
				@click="takePhoto"
				icon="mdi-checkbox-blank-circle"
				color="red"
			>
			</v-btn>
		</v-row>
		<canvas
			ref="canvas"
			style="display: none"
		></canvas>
	</div>
</template>

<script>
import { ref } from 'vue';

export default {
	name: 'PhotoCapture',
	setup(_, { emit }) {
		const canvas = ref(null);

		const takePhoto = () => {
			const videoElement = document.querySelector('#qr-reader video');
			if (videoElement) {
				const canvasElement = canvas.value;
				const context = canvasElement.getContext('2d');
				canvasElement.width = videoElement.videoWidth;
				canvasElement.height = videoElement.videoHeight;
				context.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);

				const photoData = canvasElement.toDataURL('image/jpeg');
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
