<template>
	<form @submit.prevent="dologin">
		<input
			type="text"
			v-model="login"
			placeholder="Login"
			required
		/>
		<input
			type="password"
			v-model="password"
			placeholder="Password"
			required
		/>
		<button type="submit">Login</button>
	</form>
</template>

<script>
export default {
	data() {
		return {
			login: '',
			password: '',
		};
	},
	methods: {
		dologin() {
			this.$emit('login', { login: this.login, password: this.password });
		},
	},
};
</script>
