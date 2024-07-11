<template>
	<div>
		<component
			:is="currentComponent"
			@login="handleLogin"
			:user="user"
		/>
	</div>
</template>

<script>
import axios from 'axios';

import LoginComponent from './components/LoginComponent.vue';
import managerComponent from './components/manager/managerComponent.vue';
import adminComponent from './components/admin/adminComponent.vue';

export default {
	components: {
		LoginComponent,
	},
	data() {
		return {
			user: null,
			token: localStorage.getItem('token') || '',
		};
	},
	computed: {
		currentComponent() {
			if (!this.user) return 'LoginComponent';
			if (this.user.IDRoli === '3') return managerComponent;
			if (this.user.IDRoli === '1') return adminComponent;
			return 'LoginComponent';
		},
	},
	methods: {
		async handleLogin(credentials) {
			try {
				const response = await axios.post('/api/login', credentials);
				localStorage.setItem('token', response.data.token);
				axios.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;
				this.user = response.data.user;
			} catch (error) {
				console.error('Login failed:', error);
			}
		},
	},
	created() {
		if (this.token) {
			axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
			// Fetch user data from API or use stored user data if already fetched
			// this.fetchUserData();
		}
	},
};
</script>
