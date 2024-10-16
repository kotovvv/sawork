<template>
	<div id="top">
		<v-container
			fluid
			v-if="selectedItem"
			:id="selectedItem.IDRuchuMagazynowego"
			style="min-height: 100vh"
			:key="selectedItem"
		>
			<v-row>
				<v-col>
					<h3
						>{{ selectedItem.NrDokumentu }} <small>{{ selectedItem.Data.substring(0, 10) }}</small></h3
					>
				</v-col>
				<v-spacer></v-spacer>
				<v-btn
					icon="mdi-close"
					@click="selectedItem = null"
				></v-btn>
			</v-row>
			<v-row v-if="selectedItem.ID1 == 0">
				<v-btn @click="createPZ">create PZ</v-btn>

				<v-textarea
					label="Uwaga"
					v-model="Uwaga"
				></v-textarea>
			</v-row>
			<template v-else>
				<v-card>
					<v-tabs
						v-model="tab"
						bg-color="primary"
					>
						<v-tab value="doc"> Documents </v-tab>
						<v-tab value="photo"> Photo </v-tab>
					</v-tabs>
					<v-tabs-window
						v-model="tab"
						style="height: 80vh"
						class="mt-3"
					>
						<v-tabs-window-item value="doc">
							<div class="d-flex">
								<v-file-input
									class="v-col-md-3 v-col-sm-12"
									clearable
									v-model="files"
									label="Files input"
									multiple
								></v-file-input>
								<v-btn
									class="mt-4"
									size="x-large"
									@click="uploadFiles('doc')"
									>Save</v-btn
								>
								<v-spacer></v-spacer>
								<v-btn
									@click="openModal"
									icon="mdi-camera"
								></v-btn>
							</div>
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
											style="width: 100%; max-width: 200px"
										/>
										<v-btn
											icon="mdi-delete"
											@click="delPic(index)"
										></v-btn>
									</div>
									<!-- <div v-else-if="result.type === 'qrCode'">
										<p>{{ result.data }}</p>
									</div> -->
								</div>
								<v-btn @click="uploadSnapshots">Save Snapshots</v-btn>
								<div v-if="message">{{ message }}</div>
							</div>
							<v-row v-if="docFiles.length">
								<v-col>
									<v-list>
										<v-list-item-group>
											<v-list-item
												v-for="file in docFiles"
												:key="file.name"
											>
												<v-list-item-action>
													<v-btn @click="downloadFile(file.url, file.name)">
														<img
															:src="file.url"
															:alt="file.name"
															style="height: 36px; width: auto"
															v-if="file.is_image == true"
														/>
														<v-icon v-else>mdi-file</v-icon>

														{{ file.name }}
														<v-icon>mdi-download</v-icon>
													</v-btn>
												</v-list-item-action>
											</v-list-item>
										</v-list-item-group>
									</v-list>
								</v-col>
							</v-row>
						</v-tabs-window-item>
						<v-tabs-window-item value="photo">
							<div> Photo </div>
						</v-tabs-window-item>
					</v-tabs-window>
				</v-card>

				<v-col cols="12">
					<v-btn @click="showProducts">Show products</v-btn>
				</v-col>
			</template>
		</v-container>
		<v-container fluid>
			<v-row>
				<v-col>
					<v-select
						label="Magazyn"
						v-model="IDWarehouse"
						:items="warehouses"
						item-title="Nazwa"
						item-value="IDMagazynu"
						hide-details="auto"
						width="368"
						max-width="400"
					></v-select>
				</v-col>
			</v-row>
		</v-container>

		<v-container fluid>
			<v-row>
				<v-col cols="12">
					<v-progress-linear
						:active="loading"
						indeterminate
						color="purple"
					></v-progress-linear>
				</v-col>
			</v-row>
		</v-container>
		<v-container fluid>
			<v-row>
				<v-col>
					<ComingTable
						:IDWarehouse="IDWarehouse"
						:key="IDWarehouse"
						@item-selected="handleItemSelected"
					/>
				</v-col>
			</v-row>
		</v-container>
		<Modal
			v-if="showModal"
			@close="closeModal"
		>
			<PhotoCapture
				@result="handleResult"
				@close="closeModal"
			/>
			<!-- <QrCodeScanner
				@result="handleResult"
				@close="closeModal"
			/> -->
		</Modal>
	</div>
</template>

<script>
import { ref } from 'vue';
import axios from 'axios';
import Modal from '../UI/Modal.vue';
import PhotoCapture from '../UI/PhotoCapture.vue';
// import QrCodeScanner from '../UI/QrCodeScanner.vue';
import ComingTable from './coming/ComingTable.vue';

export default {
	name: 'Coming',

	components: {
		ComingTable,
		Modal,
		PhotoCapture,
		// QrCodeScanner,
	},
	data: () => ({
		tab: null,
		Uwaga: '',
		loading: false,
		files: null,
		docFiles: [],
		IDWarehouse: null,
		warehouses: [],
		selectedItem: null,
	}),
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

		return {
			showModal,
			results,
			message,
			openModal,
			closeModal,
			handleResult,
			// uploadFiles,
		};
	},
	mounted() {
		this.getWarehouse();
		this.getFiles();
	},
	methods: {
		uploadSnapshots() {
			this.uploadFiles('doc', this.results);
		},
		delPic(index) {
			this.results.splice(index, 1);
		},
		downloadFile(url, name) {
			axios
				.get(url, {
					responseType: 'blob',
				})
				.then((res) => {
					const blobUrl = window.URL.createObjectURL(new Blob([res.data]));
					const link = document.createElement('a');
					link.href = blobUrl;
					link.setAttribute('download', name);
					document.body.appendChild(link);
					link.click();
					document.body.removeChild(link);
				})
				.catch((error) => console.log(error));
		},
		uploadFiles(folder, snapshots) {
			const vm = this;
			let formData = new FormData();
			if (snapshots && snapshots.length) {
				for (let i = 0; i < snapshots.length; i++) {
					formData.append('snapshots[]', snapshots[i].data);
				}
			}
			if (vm.files && vm.files.length) {
				for (let i = 0; i < vm.files.length; i++) {
					formData.append('files[]', vm.files[i]);
				}
			}
			formData.append('IDRuchuMagazynowego', vm.selectedItem.IDRuchuMagazynowego);
			formData.append('dir', folder);
			axios
				.post('/api/uploadFiles', formData, {
					headers: {
						'Content-Type': 'multipart/form-data',
					},
				})
				.then((res) => {
					if (res.status == 200) {
						const a_files = res.data.files;
						if (a_files.length > 0) {
							a_files.forEach((file) => {
								vm.docFiles.unshift(file);
							});
						}
						vm.files = null;
						vm.results = [];
					}
				})
				.catch((error) => console.log(error));
		},
		getFiles() {
			const vm = this;
			if (!vm.selectedItem) return;
			vm.docFiles = [];
			axios
				.get('/api/getFiles/' + vm.selectedItem.IDRuchuMagazynowego)
				// .get('/api/getFiles', {
				// 	params: {
				// 		IDRuchuMagazynowego: vm.selectedItem.IDRuchuMagazynowego,
				// 	},
				// })
				.then((res) => {
					if (res.status == 200) {
						vm.docFiles = res.data.files;
					}
				})
				.catch((error) => console.log(error));
		},
		showProducts() {},
		createPZ() {
			const vm = this;
			let data = {};
			data.IDMagazynu = vm.IDWarehouse;
			data.IDRuchuMagazynowego = vm.selectedItem.IDRuchuMagazynowego;
			data.Uwagi = vm.Uwaga;
			axios
				.post('/api/createPZ', data)
				.then((res) => {
					if (res.status == 200) {
						console.log(res.data);
					}
				})
				.catch((error) => console.log(error));
		},
		handleItemSelected(item) {
			this.selectedItem = item;
			this.getFiles();
		},
		getWarehouse() {
			const vm = this;
			vm.loading = true;
			axios
				.get('/api/getWarehouse')
				.then((res) => {
					if (res.status == 200) {
						vm.warehouses = res.data;
						if (vm.warehouses.length > 0) {
							vm.IDWarehouse = vm.warehouses[0].IDMagazynu;
							vm.loading = false;
						}
					}
				})
				.catch((error) => console.log(error));
		},
	},
};
</script>
