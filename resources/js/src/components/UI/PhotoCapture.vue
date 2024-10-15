<template>
	<div>
		<video
			ref="video"
			autoplay
			playsinline
		></video>
		<v-btn @click="capturePhoto">Capture Photo</v-btn>
		<canvas
			ref="canvas"
			style="display: none"
		></canvas>
	</div>
</template>

<script>
export default {
	name: 'PhotoCapture',
	methods: {
		async startCamera() {
			try {
				const stream = await navigator.mediaDevices.getUserMedia({
					video: true,
				});
				this.$refs.video.srcObject = stream;
			} catch (error) {
				console.error('Error accessing camera:', error);
			}
		},
		capturePhoto() {
			const video = this.$refs.video;
			const canvas = this.$refs.canvas;
			const context = canvas.getContext('2d');
			canvas.width = video.videoWidth;
			canvas.height = video.videoHeight;
			context.drawImage(video, 0, 0, canvas.width, canvas.height);
			const dataUrl = canvas.toDataURL('image/png');
			console.log('Captured photo data URL:', dataUrl);
		},
	},
	mounted() {
		this.startCamera();
	},
	beforeDestroy() {
		const video = this.$refs.video;
		const stream = video.srcObject;
		const tracks = stream.getTracks();
		tracks.forEach((track) => track.stop());
	},
};
</script>

<style scoped>
video {
	width: 100%;
	height: auto;
}
</style>
