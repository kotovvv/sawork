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
				const constraints = {
					video: {
						facingMode: 'user', // Default to front camera
					},
				};

				// Check if the device is a smartphone
				const isSmartphone = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
					navigator.userAgent,
				);

				if (isSmartphone) {
					constraints.video.facingMode = { exact: 'environment' }; // Use back camera on smartphones
				}

				const stream = await navigator.mediaDevices.getUserMedia(constraints);
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
			const photoData = canvas.toDataURL('image/jpeg');
			console.log(photoData);
			this.$emit('result', { type: 'photo', data: photoData });
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
	max-width: 300px;
	height: auto;
}
</style>
