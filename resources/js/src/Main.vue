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
			if (this.user.role.name === 'manager') return 'ManagerComponent';
			if (this.user.role.name === 'admin') return 'AdminComponent';
			return 'LoginComponent';
		},
	},
	methods: {
		async handleLogin(credentials) {
			try {
				const response = await axios.post('/api/login', credentials);
				this.token = response.data.token;
				this.user = response.data.user;
				localStorage.setItem('token', this.token);
				axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
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
