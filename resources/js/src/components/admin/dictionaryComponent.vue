<template>
	<v-card>
		<v-tabs
			v-model="tab"
			bg-color="primary"
		>
			<v-tab value="email">Magazyn email</v-tab>
			<v-tab value="price">Magazyn price</v-tab>
		</v-tabs>

		<v-card-text>
			<v-tabs-window v-model="tab">
				<v-tabs-window-item value="email">
					<MagazynEmail />
				</v-tabs-window-item>

				<v-tabs-window-item value="price">
					<MagazynPrice />
				</v-tabs-window-item>
			</v-tabs-window>
		</v-card-text>
	</v-card>
</template>
<script>
import MagazynEmail from './MagazynEmail.vue';
import MagazynPrice from './MagazynPrice.vue';
export default {
	components: { MagazynEmail, MagazynPrice },
	data: () => ({
		tab: null,
	}),
};
</script>
