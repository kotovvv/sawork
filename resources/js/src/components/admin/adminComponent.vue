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
import dictionaryComponent from './dictionaryComponent.vue';
import locationComponent from './locationComponent.vue';
import getXLSX from './getXLSX.vue';
import test from './test.vue';

export default {
	name: 'adminComponent',
	components: [dictionaryComponent, locationComponent, test],
	props: ['user'],
	data: () => ({
		drawer: null,

		theMenu: 'dictionaryComponent',

		items: [
			{ text: 'Podręczniki', name: 'dictionaryComponent', icon: 'mdi-list-box-outline' },
			{ text: 'Lokalizacja', name: 'locationComponent', icon: 'mdi-forklift' },
			{ text: 'Report XLSX', name: 'getXLSX', icon: 'mdi-file-excel' },
			{ text: 'Raporty', name: 'report', icon: 'mdi-file-chart' },
			// { text: 'Test', name: 'test', icon: 'mdi-barcode-scan' },
			{ text: 'logView', name: 'logViewer', icon: 'mdi-file-account' },
			{ text: 'Coming', name: 'coming', icon: 'mdi-file-account' },
		],
	}),
	computed: {
		setComponent() {
			if (this.theMenu == 'dictionaryComponent') return dictionaryComponent;
			if (this.theMenu == 'locationComponent') return locationComponent;
			if (this.theMenu == 'getXLSX') return getXLSX;
			if (this.theMenu == 'report') return defineAsyncComponent(() => import('../client/clientReport.vue'));
			if (this.theMenu == 'logViewer') return defineAsyncComponent(() => import('./LogViewer.vue'));
			if (this.theMenu == 'coming') return defineAsyncComponent(() => import('../client/comingComponent.vue'));
			//if (this.theMenu == 'test') return test;
		},
	},
};
</script>
