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

      <v-btn icon="mdi-logout" variant="text" @click="$emit('logout')"></v-btn>
    </v-app-bar>

    <v-navigation-drawer
      v-model="drawer"
      :location="$vuetify.display.mobile ? 'bottom' : undefined"
      temporary
    >
      <v-list :lines="false" density="compact" nav>
        <v-list-item nav prepend-avatar="/img/logo.webp">
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
        <component :user="user" :is="setComponent" />
      </v-container>
    </v-main>
  </v-app>
</template>

<script>
import { defineAsyncComponent } from "vue";
// import cabinet from './cabinetComponent.vue';

export default {
  name: "clientComponent",
  // components: [cabinet],
  props: ["user"],
  data: () => ({
    drawer: true,
    rail: true,
    selectedItem: 0,
    theMenu: "report",
    items: [
      // { text: 'Cabinet', name: 'cabinet', icon: 'mdi-account-box' },
      { text: "Reporty", name: "report", icon: "mdi-file-chart" },
      { text: "Dostawa do magazynu", name: "coming", icon: "mdi-van-utility" },
      { text: "Zwroty", name: "zwroty", icon: "mdi-database-plus" },
    ],
  }),
  computed: {
    setComponent() {
      // if (this.theMenu == 'cabinet') return cabinet;
      if (this.theMenu == "report")
        return defineAsyncComponent(() => import("./clientReport.vue"));
      if (this.theMenu == "coming")
        return defineAsyncComponent(() => import("./comingComponent.vue"));
      if (this.theMenu == "zwroty")
        return defineAsyncComponent(() =>
          import("../manager/refunds2Component.vue")
        );
    },
  },
};
</script>
