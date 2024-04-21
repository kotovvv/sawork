<template>
	<v-app id="inspire">
		<v-navigation-drawer :location="$vuetify.display.mobile ? 'bottom' : undefined">
			<!-- menu -->
			<v-list
				:lines="false"
				density="compact"
				nav
			>
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
			<v-list>
				<v-list-item
					@click="$emit('login', {})"
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

export default {
	name: 'dictionaryComponent',
	components: [dictionaryComponent],
	props: ['user'],
	data: () => ({
		drawer: true,
		selectedItem: 0,
		theMenu: 'dictionaryComponent',

		items: [{ text: 'Podręczniki', name: 'dictionaryComponent', icon: 'mdi-list-box-outline' }],
	}),
	computed: {
		setComponent() {
			if (this.theMenu == 'dictionaryComponent') return dictionaryComponent;
		},
	},
};
</script>
