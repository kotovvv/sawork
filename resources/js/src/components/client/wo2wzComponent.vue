<template>
  <div style="min-height: 100vh">
    <v-container fluid>
      <v-row>
        <v-col>
          <v-select
            label="Magazyn"
            v-model="IDWarehouse"
            :items="warehouses"
            item-title="Nazwa"
            item-value="IDMagazynu"
            hide-details="auto"
            width="368"
            max-width="400"
          ></v-select>
        </v-col>
        <v-col>
          <datepicker
            v-model="dateMin"
            format="yyyy-MM-dd"
            monday-first
          ></datepicker>

          <datepicker
            v-model="dateMax"
            format="yyyy-MM-dd"
            monday-first
          ></datepicker>
        </v-col>
        <v-col>
          <v-btn @click="getOrders()" size="x-large">uzyskać dokumenty</v-btn>
        </v-col>
      </v-row>
    </v-container>

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
    </v-container>
    <v-container fluid v-if="listWO.length > 0">
      <v-row>
        <v-col cols="12">
          <!-- :headers="headers" -->
          <v-data-table
            :items="filterWO"
            item-value="IDOrder"
            :search="searchInTable"
            @click:row="handleClick"
            v-model="selected"
            show-select
            :row-props="colorRowItem"
            height="55vh"
            fixed-header
          >
            <template v-slot:top="{}">
              <v-row class="align-center">
                <v-col class="v-col-sm-6 v-col-md-2">
                  <v-text-field
                    label="odzyskiwanie"
                    v-model="searchInTable"
                    clearable
                  ></v-text-field>
                </v-col>
              </v-row>
            </template>
          </v-data-table>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script>
import Datepicker from "vuejs3-datepicker";

import axios from "axios";
import moment from "moment";

export default {
  name: "wo2wz",
  components: {

    Datepicker,
  },
  data: () => ({
    loading: false,
    dateMin: moment().format("YYYY-MM-01"),
    dateMax: moment().format("YYYY-MM-DD"),

    warehouses: [],
    IDWarehouse: null,
    searchInTable: "",
    marked: [],
    selected: [],
    headers: [
      { title: "nazwa towaru", key: "Towar", nowrap: true },
      { title: "kod kreskowy", key: "KodKreskowy" },
      { title: "sku", key: "sku" },
      { title: "Stan Poczatkowy", key: "StanPoczatkowy", align: "end" },
      { title: "Ilość Wchodząca", key: "IlośćWchodząca", align: "end" },
      { title: "Ilość Wychodząca", key: "IlośćWychodząca", align: "end" },
      { title: "Stan Koncowy", key: "StanKoncowy", align: "end" },
    ],
    listWO: [],
  }),
  mounted() {
    this.getWarehouse();
  },
  computed: {
    filterWO() {
      return this.listWO;
    },
  },
  methods: {
    colorRowItem(item) {
      if (
        item.item.IDTowaru != undefined &&
        item.item.IDTowaru == this.marked[0]
      ) {
        return { class: "bg-red-darken-4" };
      }
    },
    handleClick(event, row) {
      this.marked = [row.item.IDTowaru];
    },
    getWarehouse() {
      const vm = this;
      axios
        .get("/api/getWarehouse")
        .then((res) => {
          if (res.status == 200) {
            vm.warehouses = res.data;
            if (vm.warehouses.length > 0) {
              vm.IDWarehouse = vm.warehouses[0].IDMagazynu;
            }
          }
        })
        .catch((error) => console.log(error));
    },
    getOrders() {
      const vm = this;
      vm.loading = true;
      let data = {};
      data.dateMin = vm.dateMin;
      data.dateMax = vm.dateMax;
      data.IDWarehouse = vm.IDWarehouse;
      axios
        .post("/api/getOrders", data)
        .then((res) => {
          if (res.status == 200) {
            vm.listWO = res.data.orders;
          }
          vm.loading = false;
        })
        .catch((error) => console.log(error));
    },
  },
};
</script>
