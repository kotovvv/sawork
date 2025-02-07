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
    <v-row
      ><v-col>
        <v-data-table
          :items="productsInlocation"
          :headers="headers"
          item-value="IDProduktu"
          :search="searchInTable"
          select-strategy="single"
          return-object
        >
          <template v-slot:top="{}">
            <v-row class="align-center d-flex gap-3 ma-3">
              <v-btn
                v-if="productsInlocation.length"
                @click="prepareXLSX()"
                icon="mdi-file-download"
              ></v-btn>
              <ToLocation
                v-if="user.IDRoli != 4"
                :products="productsInlocation"
                :location="location"
                :startStep="1"
                :warehouse="warehouse"
                @update:products="updateProductsInLocation"
              />
            </v-row>
          </template>
        </v-data-table> </v-col
    ></v-row>
  </v-container>
</template>

<script>
import axios from "axios";
import * as XLSX from "xlsx";
import { saveAs } from "file-saver";
import ToLocation from "./ToLocation.vue";
export default {
  name: "ProductsInLocation",
  components: {
    ToLocation,
  },
  props: {
    location: {
      type: String,
      required: true,
    },
    user: {
      type: Object,
      required: true,
    },
    warehouse: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      productsInlocation: [],
      searchInTable: "",
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
    this.getProductsInLocation(this.location);
  },
  mounted() {},
  watch: {},
  methods: {
    prepareXLSX() {
      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.json_to_sheet(this.productsInlocation);
      XLSX.utils.book_append_sheet(wb, ws, "");

      const wbout = XLSX.write(wb, { bookType: "xlsx", type: "array" });
      saveAs(
        new Blob([wbout], { type: "application/octet-stream" }),
        this.location + " " + this.$props.warehouse.IDMagazynu + ".xlsx"
      );
    },
    getProductsInLocation(location) {
      const vm = this;
      vm.productsInlocation = [];
      vm.loading = true;
      axios
        .get(
          "/api/getProductsInLocation/" +
            vm.$props.warehouse.IDMagazynu +
            "/" +
            location
        )
        .then((res) => {
          if (res.status == 200) {
            vm.productsInlocation = res.data;
            vm.productsInlocation.forEach((element) => {
              element.ilosc = parseInt(element.ilosc);
              element.KodKreskowy = parseInt(element.KodKreskowy);
            });
          }
          vm.loading = false;
        })
        .catch((error) => console.log(error));
    },
    updateProductsInLocation(updatedProducts) {
      this.productsInlocation = updatedProducts;
    },
  },
};
</script>
