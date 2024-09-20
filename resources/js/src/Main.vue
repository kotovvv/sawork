<template>
	<div>
		<component
			:is="currentComponent"
			@login="handleLogin"
			@logout="handleLogout"
			:user="user"
		/>
		<v-snackbar
			v-model="snackbar"
			:timeout="timeout"
			location="top"
			color="red"
		>
			{{ text }}

			<template v-slot:actions>
				<v-btn
					variant="text"
					@click="snackbar = false"
					icon="mdi-close"
				>
				</v-btn>
			</template>
		</v-snackbar>
	</div>
</template>

<script>
import { defineAsyncComponent } from 'vue';
import axios from 'axios';
import LoginComponent from './components/LoginComponent.vue';

export default {
	components: {
		LoginComponent,
	},
	data() {
		return {
			user: null,
			token: localStorage.getItem('token') || '',
			text: '',
			snackbar: false,
			timeout: 6000,
		};
	},
	computed: {
		currentComponent() {
			if (!this.user) return 'LoginComponent';
			if (this.user.IDRoli === '3')
				return defineAsyncComponent(() => import('./components/manager/managerComponent.vue'));
			if (this.user.IDRoli === '1')
				return defineAsyncComponent(() => import('./components/admin/adminComponent.vue'));
			if (this.user.IDRoli === '4')
				return defineAsyncComponent(() => import('./components/client/clientComponent.vue'));
			return 'LoginComponent';
		},
	},
	methods: {
		async handleLogin(credentials) {
			const vm = this;
			vm.text = '';
			await axios
				.post('/api/login', credentials)
				.then((res) => {
					localStorage.setItem('token', res.data.token);
					axios.defaults.headers.common['Authorization'] = `Bearer ${res.data.token}`;
					vm.user = res.data.user;
				})
				.catch((error) => {
					if (error.response && error.response.status >= 400) {
						vm.text = error.response.data.error;
						vm.snackbar = true;
					}
					console.error('Login failed:', error);
				});
		},

		handleLogout() {
			this.token = '';
			this.user = null;
			localStorage.removeItem('token');
			delete axios.defaults.headers.common['Authorization'];
		},
		created() {
			if (this.token) {
				axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
				// Fetch user data from API or use stored user data if already fetched
				// this.fetchUserData();
			}
		},
	},
};
</script>
