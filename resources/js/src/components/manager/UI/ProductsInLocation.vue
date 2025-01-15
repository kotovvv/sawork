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
        </v-data-table> </v-col
    ></v-row>
  </v-container>
</template>

<script>
import axios from "axios";
export default {
  name: "ProductsInLocation",
  props: {
    location: {
      type: String,
      required: true,
    },
    IDWarehouse: {
      type: Number,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      productsInlocation: [],
      headers: [
        { title: "SKU", key: "SKU" },
        { title: "KodKreskowy", key: "KodKreskowy" },
        { title: "Uwagi", key: "Uwagi", nowrap: true },
        { title: "Data", key: "Data" },
        { title: "NrDokumentu", key: "NrDokumentu" },
        { title: "Nazwa", key: "Nazwa", nowrap: true },
      ],
    };
  },
  created() {
    this.getProductsInLocation(this.location);
  },
  mounted() {},
  watch: {},
  methods: {
    getProductsInLocation(location) {
      const vm = this;
      vm.productsInlocation = [];
      vm.loading = true;
      axios
        .get("/api/getProductsInLocation/" + vm.IDWarehouse + "/" + location)
        .then((res) => {
          if (res.status == 200) {
            vm.productsInlocation = res.data;
          }
          vm.loading = false;
        })
        .catch((error) => console.log(error));
    },
  },
};
</script>
