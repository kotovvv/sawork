<template>
	<v-app id="inspire">
		<v-app-bar prominent>
			<v-app-bar-nav-icon
				variant="text"
				@click.stop="drawer = !drawer"
			></v-app-bar-nav-icon>

			<v-toolbar-title></v-toolbar-title>

			<v-spacer></v-spacer>

			<template v-if="$vuetify.display.mdAndUp">
				{{ $props.user.NazwaUzytkownika }}
			</template>

			<v-btn
				icon="mdi-logout"
				variant="text"
				@click="$emit('logout')"
			></v-btn>
		</v-app-bar>

		<v-navigation-drawer
			v-model="drawer"
			:location="$vuetify.display.mobile ? 'bottom' : undefined"
			temporary
		>
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
							@click.stop="drawer = !drawer"
						></v-btn>
					</template>
				</v-list-item>
				<v-list-item
					v-for="(item, i) in items"
					:key="i"
					color="primary"
					@click.stop="theMenu = item.name"
					:title="item.text"
					:prepend-icon="item.icon"
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
import refunds2 from './refunds2Component.vue';
import sendPDF from './sendPDF.vue';

export default {
	name: 'managerComponent',
	components: [refunds2],
	props: ['user'],
	data: () => ({
		drawer: true,
		rail: true,
		selectedItem: 0,
		theMenu: 'refunds2',

		items: [
			{ text: 'Zwroty', name: 'refunds2', icon: 'mdi-database-plus' },
			{ text: 'sendPDF', name: 'sendPDF', icon: 'mdi-file-send' },
			{ text: 'Dostawa do magazynu', name: 'coming', icon: 'mdi-van-utility' },
			{ text: 'Lokalizacja', name: 'locationComponent', icon: 'mdi-forklift' },
		],
	}),
	computed: {
		setComponent() {
			// if (this.theMenu == 'refunds') return refunds;
			if (this.theMenu == 'refunds2') return refunds2;
			if (this.theMenu == 'sendPDF') return sendPDF;
			if (this.theMenu == 'coming') return defineAsyncComponent(() => import('../client/comingComponent.vue'));
			if (this.theMenu == 'locationComponent')
				return defineAsyncComponent(() => import('../admin/locationComponent.vue'));
		},
	},
};
</script>
