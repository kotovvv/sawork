<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12">
        <v-progress-linear
          :active="loading"
          indeterminate
          color="purple"
        ></v-progress-linear>
      </v-col>
    </v-row>
    <v-row>
      <v-col>
        <v-tabs v-model="activeTab" color="primary">
          <v-tab v-for="user in usersList" :key="user" :value="user">
            {{ user }}
          </v-tab>
        </v-tabs>

        <v-tabs-window v-model="activeTab">
          <v-tabs-window-item
            v-for="user in usersList"
            :key="user"
            :value="user"
          >
            <v-card flat>
              <v-card-text>
                <v-data-table
                  :items="productsInlocation[user] || []"
                  :headers="headers"
                  item-value="IDTowaru"
                  :search="searchInTable"
                  select-strategy="single"
                  return-object
                >
                  <template v-slot:top="{}">
                    <v-row class="align-center d-flex gap-3 ma-3">
                      <v-text-field
                        v-model="searchInTable"
                        label="Search..."
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        density="compact"
                        clearable
                        hide-details
                        class="me-3"
                        style="max-width: 300px"
                      ></v-text-field>
                      <v-btn
                        v-if="productsInlocation[user]?.length"
                        @click="prepareXLSX(user)"
                        icon="mdi-file-download"
                        color="primary"
                        variant="tonal"
                      ></v-btn>
                    </v-row>
                  </template>
                </v-data-table>
              </v-card-text>
            </v-card>
          </v-tabs-window-item>
        </v-tabs-window>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import axios from "axios";
import * as XLSX from "xlsx";
import { saveAs } from "file-saver";

export default {
  name: "ProductsInUser",

  data() {
    return {
      loading: false,
      productsInlocation: {},
      searchInTable: "",
      activeTab: null,
      usersList: [],
      headers: [
        { title: "SKU", key: "SKU" },
        { title: "KodKreskowy", key: "KodKreskowy" },
        { title: "Uwagi", key: "Uwagi", nowrap: true },
        { title: "Data", key: "Data" },
        { title: "NrDokumentu", key: "NrDokumentu" },
        { title: "Nazwa", key: "Nazwa", nowrap: true },
        { title: "Ilość", key: "ilosc" },
      ],
    };
  },
  created() {
    this.getProductsInLocationByUser();
  },
  mounted() {},
  watch: {},
  methods: {
    prepareXLSX(user) {
      const wb = XLSX.utils.book_new();
      const userData = this.productsInlocation[user] || [];
      const ws = XLSX.utils.json_to_sheet(userData);
      XLSX.utils.book_append_sheet(wb, ws, user);

      const wbout = XLSX.write(wb, { bookType: "xlsx", type: "array" });
      saveAs(
        new Blob([wbout], { type: "application/octet-stream" }),
        `${user}_products.xlsx`
      );
    },
    getProductsInLocationByUser() {
      const vm = this;
      vm.productsInlocation = {};
      vm.usersList = [];
      vm.loading = true;
      axios
        .get("/api/getProductsInLocationByUser")
        .then((res) => {
          if (res.status == 200) {
            vm.productsInlocation = res.data;
            vm.usersList = Object.keys(res.data);
            if (vm.usersList.length > 0) {
              vm.activeTab = vm.usersList[0];
            }
          }
          vm.loading = false;
        })
        .catch((error) => console.log(error));
    },
  },
};
</script>
