<template>
	<div>
		<component
			ref="main"
			:user="user"
			v-on:login="onLogin"
			:is="theComponent"
		/>
	</div>
</template>

<script>
import logincomponent from './components/LoginComponent.vue';

import managerComponent from './components/manager/managerComponent.vue';
import adminComponent from './components/admin/adminComponent.vue';

import axios from 'axios';
export default {
	components: [logincomponent, managerComponent],
	data: () => ({
		user: {},
	}),
	computed: {
		theComponent() {
			if (this.user.role_id == undefined) return logincomponent;
			if (this.user.role_id == 3) return managerComponent;
			if (this.user.role_id == 1) return adminComponent;
		},
	},
	methods: {
		onLogin(data) {
			this.user = data;
			if (this.user.role_id == undefined && localStorage.user != undefined) localStorage.clear();
		},
		isExist(user) {
			return !!localStorage[user];
		},
		clear() {
			localStorage.clear();
			this.user = {};
		},
	},
	mounted: function () {
		if (this.isExist('user')) {
			const self = this;
			const local_user = JSON.parse(localStorage.user);
			this.user = local_user;

			// axios
			// 	.post('/api/session', local_user)
			// 	.then((res) => {
			// 		if (res.data == 'create') {
			// 			self.clear();
			// 		}
			// 	})
			// 	.catch((error) => console.log(error));
		}
	},
};
</script>
