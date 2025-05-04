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
            max-width="300"
            @update:modelValue="changeWarehouse()"
          ></v-select>
        </v-col>
        <v-col class="datezo">
          <datepicker
            v-model="dateMin"
            format="yyyy-MM-dd"
            monday-first
            language="pl"
            @update:modelValue="dateSelected(this.dateMin, 'dateMin')"
          ></datepicker>

          <datepicker
            v-model="dateMax"
            format="yyyy-MM-dd"
            monday-first
            language="pl"
            @update:modelValue="dateSelected(this.dateMax, 'dateMax')"
          ></datepicker>
        </v-col>

        <v-col>
          <v-btn @click="getOrders()" size="x-large">uzyskać dokumenty</v-btn>
        </v-col>
        <v-spacer></v-spacer>
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
            :items="filteredListZO"
            item-value="IDOrder"
            :search="searchInTable"
            @click:row="handleClick"
            v-model="selected"
            :show-select="$attrs.user.IDRoli == 1"
            return-object
            :row-props="colorRowItem"
            height="55vh"
            fixed-header
          >
            <template v-slot:top="{}">
              <div class="align-center ga-3 d-flex flex-wrap">
                <v-text-field
                  label="odzyskiwanie"
                  v-model="searchInTable"
                  clearable
                  hide-details="auto"
                  min-width="200"
                  max-width="300"
                ></v-text-field>

                <v-btn @click="prepareXLSX()" size="x-large"
                  >pobieranie XLSX</v-btn
                >
                <v-btn
                  @click="createWZfromZO"
                  v-if="selected.length > 0 && $attrs.user.IDRoli == 1"
                  size="x-large"
                  >{{ selected.length }} create WZ</v-btn
                >
                <v-btn icon @click="showFilterDialog = true">
                  <v-icon :color="isFilterActive ? 'warning' : ''"
                    >mdi-filter</v-icon
                  >
                </v-btn>
              </div>
            </template>
          </v-data-table>
        </v-col>
      </v-row>
    </v-container>
    <v-dialog v-model="showFilterDialog" max-width="800" min-height="400">
      <v-card min-height="600">
        <v-btn
          icon
          class="ma-2"
          style="position: absolute; top: 0; right: 0"
          @click="showFilterDialog = false"
        >
          <v-icon>mdi-close</v-icon>
        </v-btn>
        <v-card-title>Ustawienie filtra</v-card-title>
        <v-card-text>
          <div class="d-flex ga-3 flex-wrap">
            <div class="datezo">
              Data WZ
              <datepicker
                v-model="dateMinWZ"
                format="yyyy-MM-dd"
                monday-first
                language="pl"
                @update:modelValue="dateSelected(this.dateMinWZ, 'dateMinWZ')"
                clear-button
              ></datepicker>

              <datepicker
                v-model="dateMaxWZ"
                format="yyyy-MM-dd"
                monday-first
                language="pl"
                @update:modelValue="dateSelected(this.dateMaxWZ, 'dateMaxWZ')"
                clear-button
              ></datepicker>
            </div>
            <v-select
              label="Pusty"
              v-model="empty"
              :items="fields.filter((field) => !full.includes(field.value))"
              hide-details="auto"
              multiple
              clearable
              min-width="180"
              max-width="300"
            ></v-select>
            <v-select
              label="Nie pusty"
              v-model="full"
              :items="fields.filter((field) => !empty.includes(field.value))"
              hide-details="auto"
              multiple
              clearable
              min-width="180"
              max-width="300"
            ></v-select>
            <v-select
              label="Status."
              v-model="filterStatus"
              :items="workStatuses"
              return-object
              hide-details="auto"
              multiple
              clearable
              min-width="180"
              max-width="300"
            ></v-select>
            <v-btn icon @click="clearFilters">
              <v-icon>mdi-filter-remove</v-icon>
            </v-btn>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import Datepicker from "vuejs3-datepicker";

import axios from "axios";
import moment from "moment";
import * as XLSX from "xlsx";
import { saveAs } from "file-saver";

export default {
  name: "zo2wz",
  components: {
    Datepicker,
  },
  data: () => ({
    loading: false,
    dateMin: moment()
      .subtract(90, "days")
      .startOf("day")
      .format("YYYY-MM-DD HH:mm:ss"),
    dateMax: moment().endOf("day").format("YYYY-MM-DD HH:mm:ss"),
    dateMinWZ: null,
    dateMaxWZ: null,

    warehouses: [],
    IDWarehouse: null,
    searchInTable: "",
    marked: [],
    selected: [],
    headers: [
      { title: "Data WZ", key: "DataWZ" },
      { title: "Number ZO", key: "Number", nowrap: true, sortable: false },
      { title: "Data ZO", key: "DataZO", nowrap: true },
      { title: "Kwota brutto", key: "KwotaBrutto", nowrap: true },
      {
        title: "Nr_Baselinker",
        key: "Nr_Baselinker",
        nowrap: true,
        sortable: false,
      },
      { title: "Kontrahent", key: "Kontrahent", nowrap: true, sortable: false },
      { title: "Status", key: "Status" },
      { title: "Uwagi", key: "Uwagi", nowrap: true, sortable: false },

      { title: "Rodzaj_transportu", key: "Rodzaj_transportu", nowrap: true },
      { title: "Nr_Nadania", key: "Nr_Nadania" },
      { title: "Nr_Faktury", key: "Nr_Faktury" },
      { title: "Nr_Zwrotny", key: "Nr_Zwrotny" },
      { title: "Nr_Korekty", key: "Nr_Korekty" },
      { title: "Źródło", key: "Źródło", nowrap: true },
      { title: "External_id", key: "External_id", nowrap: true },
      { title: "Login_klienta", key: "Login_klienta" },
    ],
    listZO: [],
    empty: [],
    full: [],
    fields: [
      { title: "Rodzaj_transportu", key: "rt.Nazwa" },

      { title: "Uwagi", key: "Remarks" },

      { title: "Nr_Baselinker", key: "_OrdersTempDecimal2" },
      { title: "Nr_Nadania", key: "_OrdersTempString2" },
      { title: "Nr_Faktury", key: "_OrdersTempString1" },
      { title: "Nr_Zwrotny", key: "_OrdersTempString4" },
      { title: "Nr_Korekty", key: "_OrdersTempString3" },
      { title: "Źródło", key: "_OrdersTempString7" },
      { title: "External_id", key: "_OrdersTempString8" },
      { title: "Login_klienta", key: "_OrdersTempString9" },
    ],
    statuses: [],
    workStatuses: [],
    filterStatus: [],
    showFilterDialog: false,
  }),

  mounted() {
    this.getWarehouse();
    this.getStatuses();
    if (this.$attrs.user.IDRoli == 1) {
      this.headers.unshift(
        { title: "product_Chang", key: "product_Chang" },
        { title: "Zmodyfikowane", key: "Zmodyfikowane", nowrap: true },
        { title: "Powiązane_WZ", key: "Powiązane_WZ", nowrap: true }
      );
      this.fields.push(
        { title: "product_Chang", key: "_OrdersTempString5" },
        { title: "Powiązane_WZ", key: "rm.NrDokumentu" },
        { title: "Zmodyfikowane", key: "ord.Modified", nowrap: true }
      );
    }
  },
  computed: {
    isFilterActive() {
      return (
        this.empty.length > 0 ||
        this.full.length > 0 ||
        this.filterStatus.length > 0 ||
        this.dateMinWZ !== null ||
        this.dateMaxWZ !== null
      );
    },
    filteredListZO() {
      // Фильтруем данные на основе выбранных фильтров
      return this.listZO.filter((order) => {
        // Проверка на пустые поля
        if (
          this.empty.length > 0 &&
          !this.empty.every((field) => !order[field])
        ) {
          return false;
        }

        // Проверка на непустые поля
        if (this.full.length > 0 && !this.full.every((field) => order[field])) {
          return false;
        }

        // Проверка на статус
        if (
          this.filterStatus.length > 0 &&
          !this.filterStatus.some((status) => status.title === order.Status)
        ) {
          return false;
        }

        if (this.dateMinWZ === null && this.dateMaxWZ === null) {
          return true;
        } else if (
          this.dateMinWZ &&
          this.dateMaxWZ &&
          order.DataWZ &&
          moment(order.DataWZ).isBetween(
            this.dateMinWZ,
            this.dateMaxWZ,
            null,
            "[]"
          )
        ) {
          return true;
        } else {
          return false;
        }
        return true;
      });
    },
  },
  methods: {
    clearFilters() {
      this.empty = [];
      this.full = [];
      this.filterStatus = [];
      this.dateMinWZ = null;
      this.dateMaxWZ = null;
    },
    dateSelected(date, v) {
      if (v.toLowerCase().includes("min")) {
        this[v] = moment(date).startOf("day").format("YYYY-MM-DD HH:mm:ss");
      } else if (v.toLowerCase().includes("max")) {
        this[v] = moment(date).endOf("day").format("YYYY-MM-DD HH:mm:ss");
      } else {
        this[v] = moment(date).format("YYYY-MM-DD HH:mm:ss");
      }
    },
    applyFilters() {
      this.showFilterDialog = false;
      this.getOrders(); // Обновить список с учетом фильтров
    },
    changeWarehouse() {
      this.listZO = [];
      this.getOrders();
    },
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
              vm.getOrders();
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

      axios
        .post("/api/getOrders", data)
        .then((res) => {
          if (res.status == 200) {
            vm.listZO = res.data.orders;
            vm.listZO = vm.listZO.map((order) => {
              order.nr_Baselinker = parseInt(order.nr_Baselinker);
              order.DataZO = order.DataZO.substring(0, 16);
              order.Zmodyfikowane = order.Zmodyfikowane.substring(0, 16);

              return order;
            });
            vm.workStatuses = vm.statuses.filter((status) =>
              vm.listZO.some((order) => order.Status === status.title)
            );
          }
          vm.loading = false;
        })
        .catch((error) => console.log(error));
    },
    prepareXLSX() {
      // Создание новой книги
      //   this.listZO.forEach((el) => {

      //   });
      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.json_to_sheet(this.filteredListZO);
      XLSX.utils.book_append_sheet(wb, ws, "");

      // Генерация файла и его сохранение
      const wbout = XLSX.write(wb, { bookType: "xlsx", type: "array" });
      saveAs(
        new Blob([wbout], { type: "application/octet-stream" }),
        "ZO " +
          moment(this.dateMin).format("YYYY-MM-DD") +
          "_" +
          moment(this.dateMax).format("YYYY-MM-DD") +
          ".xlsx"
      );
    },
  },
};
</script>

<style>
.datezo .vuejs3-datepicker__value {
  min-width: auto;
  margin-right: 0.5rem;
}
@media (max-width: 600px) {
  .datezo .vuejs3-datepicker__value {
    min-width: 100%;
    margin-right: 0;
  }
  .datezo {
    display: flex;
    flex-direction: column;
  }
}
</style>
