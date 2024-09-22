<template>
	<div>
		<v-btn
			@click="openModal"
			icon="mdi-camera"
		></v-btn>

		<Modal
			v-if="showModal"
			@close="closeModal"
		>
			<GetPic
				@result="handleResult"
				@close="closeModal"
			/>
		</Modal>
		<div v-if="results.length">
			<h2>Results:</h2>
			<div
				v-for="(result, index) in results"
				:key="index"
			>
				<div v-if="result.type === 'photo'">
					<img
						:src="result.data"
						alt="Captured Photo"
						style="width: 100%; max-width: 600px"
					/>
					<v-btn
						icon="mdi-delete"
						@click="delPic(index)"
					></v-btn>
				</div>
				<div v-else-if="result.type === 'qrCode'">
					<p>{{ result.data }}</p>
				</div>
			</div>
			<v-btn @click="saveSnapshots">Save Snapshots</v-btn>
			<div v-if="message">{{ message }}</div>
		</div>
	</div>
</template>

<script>
import { ref } from 'vue';
import axios from 'axios';
import Modal from '../UI/Modal.vue';
import GetPic from '../UI/GetPic.vue';

export default {
	components: {
		Modal,
		GetPic,
	},
	data() {
		return {};
	},
	methods: {
		delPic(index) {
			this.results.splice(index, 1);
		},
	},
	setup() {
		const showModal = ref(false);
		const results = ref([]);
		const message = ref('');

		const openModal = () => {
			showModal.value = true;
			message.value = '';
		};

		const closeModal = () => {
			showModal.value = false;
		};

		const handleResult = (data) => {
			results.value.push(data);
		};

		const saveSnapshots = async () => {
			message.value = '';
			try {
				const response = await axios.post('/api/savePic', { snapshots: results.value });
				message.value = 'Snapshots saved successfully';
				console.log('Snapshots saved successfully:', response.data);
			} catch (error) {
				message.value = 'Error saving snapshots';
				console.error('Error saving snapshots:', error);
			}
		};

		return {
			showModal,
			results,
			message,
			openModal,
			closeModal,
			handleResult,
			saveSnapshots,
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
