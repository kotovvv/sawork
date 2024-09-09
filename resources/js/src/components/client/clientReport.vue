<template>
	<v-card>
		<v-tabs
			v-model="tab"
			bg-color="primary"
		>
			<v-tab
				value="day"
				v-if="$attrs.user.IDRoli == 1"
				>Stan</v-tab
			>
			<v-tab value="notactive">nieaktywny</v-tab>
			<v-tab
				value="tarif"
				v-if="$attrs.user.IDRoli == 1"
				>Tarif</v-tab
			>
		</v-tabs>

		<v-card-text>
			<v-tabs-window v-model="tab">
				<v-tabs-window-item
					value="day"
					v-if="$attrs.user.IDRoli == 1"
				>
					<ReportDay :user="$attrs.user" />
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
	name: 'clientReport',
	components: { ReportDay, ReportNotActiveProducts, ReportTarif },
	data: () => ({
		tab: null,
	}),
};
</script>
