// File: resources/js/src/components/admin/LogViewer.vue

<template>
	<div>
		<h1>Log Viewer</h1>
		<div>
			<h2>Users.log</h2>
			<pre>{{ usersLog }}</pre>
		</div>
		<div>
			<h2>useReport.log</h2>
			<pre>{{ useReportLog }}</pre>
		</div>
	</div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';

export default {
	name: 'LogViewer',
	setup() {
		const useReportLog = ref('');
		const usersLog = ref('');

		const fetchLog = async (url, logRef) => {
			try {
				const response = await axios.get(url);
				if (response.data.content) {
					logRef.value = response.data.content;
				} else {
					console.error(`Error fetching ${url}:`, response.data.error);
				}
			} catch (error) {
				console.error(`Error fetching ${url}:`, error);
			}
		};

		onMounted(() => {
			fetchLog('/api/logs/useReport', useReportLog);
			fetchLog('/api/logs/users', usersLog);
		});

		return {
			useReportLog,
			usersLog,
		};
	},
};
</script>

<style scoped>
pre {
	background-color: #f5f5f5;
	padding: 10px;
	border-radius: 5px;
	max-height: 400px;
	overflow-y: auto;
}
</style>
