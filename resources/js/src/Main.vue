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
import ManagerComponent from './components/manager/ManagerComponent.vue';
import AdminComponent from './components/admin/AdminComponent.vue';

export default {
	components: {
		LoginComponent,
		ManagerComponent,
		AdminComponent,
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
			if (this.user.name === 'manager') return 'ManagerComponent';
			if (this.user.name === 'admin') return 'AdminComponent';
			return 'LoginComponent';
		},
	},
	methods: {
		async handleLogin(credentials) {
			try {
				const response = await axios.post('/api/login', credentials);
				console.log(response);
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
