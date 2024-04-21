<template>
	<v-app id="inspire">
		<v-main>
			<v-container class="fill-height">
				<v-row class="justify-center">
					<v-card class="elevation-12">
						<v-toolbar
							color="primary"
							dark
							flat
						>
							<v-toolbar-title>Połączenie z systemem</v-toolbar-title>
						</v-toolbar>
						<v-row class="ma-2">
							<v-col><v-btn @click="role(3)">Kierownik</v-btn></v-col>
							<v-col><v-btn @click="role(1)">Аdministrator</v-btn></v-col>
						</v-row>
					</v-card>
				</v-row>
			</v-container>
		</v-main>
	</v-app>
</template>

<script>
export default {
	name: 'login',
	props: [],
	data: () => ({}),
	methods: {
		role(id) {
			this.$attrs.user.role_id = id;
			this.$emit('login', this.$attrs.user);
			// localStorage.user = JSON.stringify(this.user);
		},
	},
};
</script>
