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
          <v-select
            label="Pusty"
            v-model="empty"
            :items="fields.filter((field) => !full.includes(field.value))"
            hide-details="auto"
            multiple
          ></v-select>
        </v-col>
        <v-col>
          <v-select
            label="Nie pusty"
            v-model="full"
            :items="fields.filter((field) => !empty.includes(field.value))"
            hide-details="auto"
            multiple
          ></v-select>
        </v-col>
        <v-col>
          <v-select
            label="Status"
            v-model="filterStatus"
            :items="statuses"
            hide-details="auto"
            multiple
          ></v-select>
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
    <v-container fluid v-if="listZO.length > 0">
      <v-row>
        <v-col cols="12">
          <v-data-table
            :headers="headers"
            :items="filterWO"
            item-value="IDOrder"
            :search="searchInTable"
            @click:row="handleClick"
            v-model="selected"
            show-select
            return-object
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
                    hide-details="auto"
                  ></v-text-field>
                </v-col>
                <v-btn
                  @click="createWZfromZO"
                  v-if="selected.length > 0"
                  size="x-large"
                  >{{ selected.length }} create WZ</v-btn
                >
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
  name: "zo2wz",
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
      { title: "product_Chang", key: "product_Chang" },
      { title: "Powiązane_WZ", key: "Powiązane_WZ", nowrap: true },
      { title: "Date", key: "Date" },
      { title: "Number", key: "Number", nowrap: true },
      { title: "Kontrahent", key: "Kontrahent", nowrap: true },
      { title: "Status", key: "Status" },
      { title: "Uwagi", key: "Uwagi", nowrap: true },
      { title: "Zmodyfikowane", key: "Zmodyfikowane", nowrap: true },
      { title: "Rodzaj_transportu", key: "Rodzaj_transportu", nowrap: true },
      { title: "Nr_Baselinker", key: "Nr_Baselinker", nowrap: true },
      { title: "Nr_Nadania", key: "Nr_Nadania" },
      { title: "Nr_Faktury", key: "Nr_Faktury" },
      { title: "Nr_Zwrotny", key: "Nr_Zwrotny" },
      { title: "Nr_Korekty", key: "Nr_Korekty" },
      { title: "Źródło", key: "Źródło" },
      { title: "External_id", key: "External_id" },
      { title: "Login_klienta", key: "Login_klienta" },
    ],
    listZO: [],
    empty: [],
    full: [],
    fields: [
      { title: "product_Chang", value: "_OrdersTempString5" },
      { title: "Powiązane_WZ", value: "rm.NrDokumentu" },
      { title: "Rodzaj_transportu", value: "rt.Nazwa" },

      { title: "Uwagi", value: "Remarks" },
      { title: "Zmodyfikowane", value: "ord.Modified" },

      { title: "Nr_Baselinker", value: "_OrdersTempDecimal2" },
      { title: "Nr_Nadania", value: "_OrdersTempString2" },
      { title: "Nr_Faktury", value: "_OrdersTempString1" },
      { title: "Nr_Zwrotny", value: "_OrdersTempString4" },
      { title: "Nr_Korekty", value: "_OrdersTempString3" },
      { title: "Źródło", value: "_OrdersTempString7" },
      { title: "External_id", value: "_OrdersTempString8" },
      { title: "Login_klienta", value: "_OrdersTempString9" },
    ],
    statuses: [],
    filterStatus: [],
  }),
  mounted() {
    this.getWarehouse();
    this.getStatuses();
  },
  computed: {
    filterWO() {
      return this.listZO;
    },
  },
  methods: {
    createWZfromZO() {
      const vm = this;
      vm.loading = true;
      if (vm.selected.length > 0) {
        axios
          .post("/api/createWZfromZO", {
            IDOrders: vm.selected
              .filter((order) => order.Powiązane_WZ == null)
              .map((order) => ({
                IDOrder: order.IDOrder,
                Number: order.Number,
              })),
            warehouse: vm.warehouses
              .filter((warehouse) => warehouse.IDMagazynu == vm.IDWarehouse)
              .map((warehouse) => ({
                IDMagazynu: warehouse.IDMagazynu,
                Symbol: warehouse.Symbol,
              })),
          })
          .then((res) => {
            if (res.status == 200) {
              vm.selected = [];
              let ret = res.data;

              vm.listZO = vm.listZO.map((order) => {
                let el = Object.keys(ret).find((key) => key == order.IDOrder);
                if (el != undefined) {
                  order.Powiązane_WZ = ret[el].Powiązane_WZ;
                }
                return order;
              });
            }
            if (res.status == 500) {
              alert(res.data);
            }
            vm.loading = false;
          })
          .catch((error) => console.log(error));
      }
    },
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
    getStatuses() {
      const vm = this;
      axios
        .get("/api/getStatuses")
        .then((res) => {
          if (res.status == 200) {
            vm.statuses = res.data;
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
      data.empty = vm.empty;
      data.full = vm.full;
      data.statuses = vm.filterStatus;
      axios
        .post("/api/getOrders", data)
        .then((res) => {
          if (res.status == 200) {
            vm.listZO = res.data.orders;
            vm.listZO = vm.listZO.map((order) => {
              order.nr_Baselinker = parseInt(order.nr_Baselinker);
              order.Date = order.Date.substring(0, 16);
              order.Zmodyfikowane = order.Zmodyfikowane.substring(0, 16);
              return order;
            });
          }
          vm.loading = false;
        })
        .catch((error) => console.log(error));
    },
  },
};
</script>
