<template>
  <v-card>
    <v-tabs v-model="tab" bg-color="primary">
      <v-tab value="email">Magazyn email</v-tab>
      <v-tab value="price">Magazyn price</v-tab>
      <v-tab value="setprice">Set price</v-tab>
      <v-tab value="bltab">BaseLinker</v-tab>
      <v-tab value="locations">Locations</v-tab>
    </v-tabs>

    <v-card-text>
      <v-tabs-window v-model="tab">
        <v-tabs-window-item value="email">
          <MagazynEmail />
        </v-tabs-window-item>

        <v-tabs-window-item value="price">
          <MagazynPrice />
        </v-tabs-window-item>
        <v-tabs-window-item value="setprice">
          <SetPrice />
        </v-tabs-window-item>
        <v-tabs-window-item value="bltab">
          <BL />
        </v-tabs-window-item>
        <v-tabs-window-item value="locations">
          <Locations />
        </v-tabs-window-item>
      </v-tabs-window>
    </v-card-text>
  </v-card>
</template>
<script>
import MagazynEmail from "./MagazynEmail.vue";
import MagazynPrice from "./MagazynPrice.vue";
import SetPrice from "./setPrice.vue";
import BL from "./BaseLinkerComponent.vue";
import Locations from "./warehouseLocations.vue";
export default {
  components: { MagazynEmail, MagazynPrice, SetPrice, BL, Locations },
  data: () => ({
    tab: null,
  }),
};
</script>
