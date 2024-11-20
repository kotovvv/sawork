<template>
  <div>
    <v-progress-linear
      :active="loading"
      indeterminate
      color="purple"
    ></v-progress-linear>

    <v-data-table
      :items="docsWZk"
      :headers="wzk_headers"
      item-value="IDRuchuMagazynowego"
      :search="searchInTable"
      @click:row="handleClick"
      select-strategy="single"
      :row-props="colorRowItem"
      fixed-header
      return-object
    >
      <template v-slot:item.NrDokumentu="{ item }">
        <span v-if="item.photo" class="photo">{{ item.photo }} </span>
        {{ item.NrDokumentu }}
      </template>
      <template v-slot:top="{}" v-if="docsWZk.length">
        <v-row class="align-center">
          <v-col class="v-col-sm-6 v-col-md-2">
            <v-text-field
              label="odzyskiwanie"
              v-model="searchInTable"
              clearable
              hide-details
            ></v-text-field>
          </v-col>
          <v-btn @click="getDocsWZk" icon="mdi-refresh"></v-btn>
          <v-col cols="5" sm="12">
            <div class="d-flex ga-5 flex-wrap">
              <span v-if="locations.Zwrot"
                >Ilość w zwrot: {{ locations.Zwrot }}</span
              >
              <span v-if="locations.Naprawa"
                >Naprawa: {{ locations.Naprawa }}</span
              >
              <span v-if="locations.Zniszczony"
                >Zniszczony: {{ locations.Zniszczony }}</span
              >
            </div>
          </v-col>
        </v-row>
      </template>
    </v-data-table>
  </div>
</template>

<script>
import axios from "axios";

export default {
  name: "WZkTable",
  props: ["IDWarehouse"],
  data: () => ({
    docsWZk: [],
    selected: {},
    wzk_headers: [
      { title: "NrDokumentu", key: "NrDokumentu", nowrap: true },
      { title: "Data", key: "Data" },
      { title: "Kontrahent", key: "Kontrahent", nowrap: true },
      { title: "Uwagi", key: "Uwagi", nowrap: true },
    ],
    searchInTable: "",
    loading: false,
    locations: {
      Zworot: 0,
      Naprawa: 0,
      Zniszczony: 0,
    },
  }),
  mounted() {
    this.getDocsWZk();
  },
  methods: {
    handleClick(e, row) {
      this.selected = row.item;
      this.$emit("item-selected", this.selected); // Emit event with selected item
    },

    colorRowItem(item) {
      if (
        item.item.IDRuchuMagazynowego != undefined &&
        item.item.IDRuchuMagazynowego == this.selected.IDRuchuMagazynowego
      ) {
        return { class: "bg-red-darken-4" };
      }
    },
    getDocsWZk() {
      const vm = this;
      if (vm.IDWarehouse == null) return;
      vm.loading = true;
      axios
        .post("/api/getDocsWZk", { IDWarehouse: vm.IDWarehouse })
        .then((res) => {
          if (res.status == 200) {
            vm.docsWZk = res.data.DocsWZk;
            vm.docsWZk.map((e) => {
              e.Data = e.Data.substring(0, 16);
            });
            vm.locations.Zwrot = res.data.Zwrot;
            vm.locations.Naprawa = res.data.Naprawa;
            vm.locations.Zniszczony = res.data.Zniszczony;
          }
          vm.loading = false;
        })
        .catch((error) => console.log(error));
    },
  },
};
</script>

<style scoped>
.photo {
  background-color: #bbdefb;
  color: rgb(0, 0, 0);
  padding: 0 2px;
  border-radius: 8px;
  font-size: 0.7rem;
}
</style>
