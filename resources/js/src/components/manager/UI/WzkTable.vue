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
      { title: "Uwagi", key: "Uwagi", nowrap: true },
    ],
    searchInTable: "",
    loading: false,
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
      vm.loading = true;
      axios
        .post("/api/getDocsWZk", { IDWarehouse: vm.IDWarehouse })
        .then((res) => {
          if (res.status == 200) {
            vm.docsWZk = res.data;
            vm.docsWZk.map((e) => {
              e.Data = e.Data.substring(0, 16);
            });
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
