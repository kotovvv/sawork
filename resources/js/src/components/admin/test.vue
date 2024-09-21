<template>
	<div>
		<button @click="openModal">Open Camera</button>
		<Modal
			v-if="showModal"
			@close="closeModal"
		>
			<GetPic
				@result="handleResult"
				@close="closeModal"
			/>
		</Modal>
		<div v-if="result">
			<h2>Result:</h2>
			<div v-if="result.type === 'photo'">
				<img
					:src="result.data"
					alt="Captured Photo"
					style="width: 100%; max-width: 600px"
				/>
			</div>
			<div v-else-if="result.type === 'qrCode'">
				<p>{{ result.data }}</p>
			</div>
		</div>
	</div>
</template>

<script>
import { ref } from 'vue';
import Modal from '../UI/Modal.vue';
import GetPic from '../UI/GetPic.vue';

export default {
	components: {
		Modal,
		GetPic,
	},
	setup() {
		const showModal = ref(false);
		const result = ref(null);

		const openModal = () => {
			showModal.value = true;
		};

		const closeModal = () => {
			showModal.value = false;
		};

		const handleResult = (data) => {
			result.value = data;
			closeModal();
		};

		return {
			showModal,
			result,
			openModal,
			closeModal,
			handleResult,
		};
	},
};
</script>

<style scoped>
img {
	border: 1px solid #ccc;
	margin: 10px 0;
}
</style>
