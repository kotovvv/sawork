<template>
	<v-card style="min-height: 100vh">
		<v-tabs
			v-model="tab"
			bg-color="primary"
		>
			<v-tab value="day">Stan</v-tab>
			<v-tab value="notactive">nieaktywny</v-tab>
			<v-tab
				value="tarif"
				v-if="$attrs.user.IDRoli == 1"
				>Tarif</v-tab
			>
			<v-tab
				value="oborot"
				v-if="$attrs.user.IDRoli == 1"
				>Oborot</v-tab
			>
		</v-tabs>

		<v-card-text>
			<v-tabs-window v-model="tab">
				<v-tabs-window-item value="day">
					<ReportDay :user="$attrs.user" />
				</v-tabs-window-item>
				<v-tabs-window-item value="notactive">
					<ReportNotActiveProducts />
				</v-tabs-window-item>
				<v-tabs-window-item value="tarif">
					<ReportTarif />
				</v-tabs-window-item>
				<v-tabs-window-item value="oborot">
					<ReportOborot :user="$attrs.user" />
				</v-tabs-window-item>
			</v-tabs-window>
		</v-card-text>
	</v-card>
</template>
<script>
import ReportDay from './reportDay.vue';
import ReportNotActiveProducts from './reportNotActiveProducts.vue';
import ReportTarif from './reportTraif.vue';
import ReportOborot from './reportOborot.vue';
export default {
	name: 'clientReport',
	components: { ReportDay, ReportNotActiveProducts, ReportTarif, ReportOborot },
	data: () => ({
		tab: null,
	}),
};
</script>
