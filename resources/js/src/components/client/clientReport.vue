<template>
	<v-card>
		<v-tabs
			v-model="tab"
			bg-color="primary"
		>
			<v-tab value="day">Stan</v-tab>
			<v-tab value="notactive">nieaktywny</v-tab>
			<v-tab value="tarif">tarif</v-tab>
		</v-tabs>

		<v-card-text>
			<v-tabs-window v-model="tab">
				<v-tabs-window-item value="day">
					<ReportDay />
				</v-tabs-window-item>

				<v-tabs-window-item value="notactive">
					<ReportNotActiveProducts />
				</v-tabs-window-item>
				<v-tabs-window-item value="tarif">
					<ReportTarif />
				</v-tabs-window-item>
			</v-tabs-window>
		</v-card-text>
	</v-card>
</template>
<script>
import ReportDay from './reportDay.vue';
import ReportNotActiveProducts from './reportNotActiveProducts.vue';
import ReportTarif from './reportTraif.vue';
export default {
	components: { ReportDay, ReportNotActiveProducts, ReportTarif },
	data: () => ({
		tab: null,
	}),
};
</script>
