<template>
	<router-view></router-view>
</template>

<script>
import axios from 'axios';

export default {
	data() {
		return {
			user: null,
			token: localStorage.getItem('token') || '',
		};
	},
	methods: {
		async handleLogin(credentials) {
			try {
				await axios.get('/sanctum/csrf-cookie');
				const response = await axios.post('/api/login', credentials);
				this.token = response.data.token;
				this.user = response.data.user;
				localStorage.setItem('token', this.token);
				localStorage.setItem('user', JSON.stringify(this.user));
				axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
				this.redirectUser();
			} catch (error) {
				console.error('Login failed:', error);
			}
		},
		redirectUser() {
			if (this.user.role.name === 'manager') {
				this.$router.push({ name: 'Manager' });
			} else if (this.user.role.name === 'admin') {
				this.$router.push({ name: 'Admin' });
			} else {
				this.$router.push({ name: 'Login' });
			}
		},
	},
	created() {
		if (this.token) {
			axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
			this.user = JSON.parse(localStorage.getItem('user'));
			this.redirectUser();
		} else {
			this.$router.push({ name: 'Login' });
		}
	},
};
</script>
