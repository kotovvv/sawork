<template>
	<v-app id="inspire">
		<v-navigation-drawer
			:location="$vuetify.display.mobile ? 'bottom' : undefined"
			:rail="rail"
			permanent
			@click.stop="rail = !rail"
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
					@click.stop="theMenu = item.name"
					:title="item.text"
					:prepend-icon="item.icon"
				>
				</v-list-item>
			</v-list>
			<v-divider></v-divider>
			<v-list>
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
import dictionaryComponent from './dictionaryComponent.vue';
import locationComponent from './locationComponent.vue';
import test from './test.vue';

export default {
	name: 'adminComponent',
	components: [dictionaryComponent, locationComponent, test],
	props: ['user'],
	data: () => ({
		drawer: true,
		rail: true,

		theMenu: 'dictionaryComponent',

		items: [
			{ text: 'Podręczniki', name: 'dictionaryComponent', icon: 'mdi-list-box-outline' },
			{ text: 'Lokalizacja', name: 'locationComponent', icon: 'mdi-forklift' },
			{ text: 'Test', name: 'test', icon: 'mdi-barcode-scan' },
		],
	}),
	computed: {
		setComponent() {
			if (this.theMenu == 'dictionaryComponent') return dictionaryComponent;
			if (this.theMenu == 'locationComponent') return locationComponent;
			if (this.theMenu == 'test') return test;
		},
	},
};
</script>
