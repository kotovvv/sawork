<template>
	<v-app id="inspire">
		<v-navigation-drawer
			:location="$vuetify.display.mobile ? 'bottom' : undefined"
			:rail="rail"
			permanent
			@click="rail = false"
		>
			<!-- menu -->

			<v-list
				:lines="false"
				density="compact"
				nav
			>
				<v-list-item
					nav
					prepend-avatar="/img/logo.webp"
				>
					<template v-slot:append>
						<v-btn
							icon="mdi-chevron-left"
							variant="text"
							@click.stop="rail = !rail"
						></v-btn>
					</template>
				</v-list-item>
				<v-list-item
					v-for="(item, i) in items"
					:key="i"
					color="primary"
					@click="theMenu = item.name"
					:title="item.text"
					:prepend-icon="item.icon"
				>
				</v-list-item>
			</v-list>
			<v-divider></v-divider>
			<v-list
				density="compact"
				nav
			>
				<v-list-item
					@click="$emit('logout')"
					prepend-icon="mdi-logout"
					title="Wyjście"
				>
				</v-list-item>
			</v-list>
		</v-navigation-drawer>

		<v-main>
			<v-container fluid>
				<component
					:user="user"
					:is="setComponent"
				/>
			</v-container>
		</v-main>
	</v-app>
</template>

<script>
import { defineAsyncComponent } from 'vue';
import cabinet from './cabinetComponent.vue';

export default {
	name: 'clientComponent',
	components: [cabinet],
	props: ['user'],
	data: () => ({
		drawer: true,
		rail: true,
		selectedItem: 0,
		theMenu: 'cabinet',
		items: [
			{ text: 'Cabinet', name: 'cabinet', icon: 'mdi-account-box' },
			{ text: 'Report', name: 'report', icon: 'mdi-file-chart' },
		],
	}),
	computed: {
		setComponent() {
			if (this.theMenu == 'cabinet') return cabinet;
			if (this.theMenu == 'report') return defineAsyncComponent(() => import('./clientReport.vue'));
		},
	},
};
</script>
