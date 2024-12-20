<template>
  <div style="min-height: 100vh">
    <v-container fluid>
      <v-row>
        <v-col cols="12" md="2" lg="2">
          <v-select
            label="Magazyn"
            v-model="IDWarehouse"
            :items="warehouses"
            item-title="Nazwa"
            item-value="IDMagazynu"
            hide-details="auto"
          ></v-select>
        </v-col>
        <v-col>
          <p>
            Okres New
            <span v-if="daysBetween !== 0">({{ daysBetween }} days)</span>
          </p>

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
          <p>Okres Old ({{ daysBetweenOld }} days)</p>
          <datepicker
            v-model="dateDoMin"
            format="yyyy-MM-dd"
            monday-first
          ></datepicker>

          <datepicker
            v-model="dateDoMax"
            format="yyyy-MM-dd"
            monday-first
          ></datepicker>
        </v-col>

        <v-col cols="12" md="2" lg="2">
          <v-text-field
            v-model="DaysOn"
            label="Dni na dostawę"
            type="number"
          ></v-text-field>
        </v-col>
        <v-col>
          <v-btn @click="getQuantity()" size="x-large" :disabled="loading"
            >uzyskać dane</v-btn
          >
          <v-btn v-if="dataforxsls.length" @click="prepareXLSX()" size="x-large"
            >pobieranie XLSX</v-btn
          ></v-col
        >
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
    <v-container fluid v-if="dataforxsls.length">
      <v-row>
        <v-col cols="12">
          <!-- :headers="headers" -->
          <v-data-table
            :items="dataforxsls"
            item-value="IDTowaru"
            :headers="headers"
            height="55vh"
            fixed-header
          >
          </v-data-table>
        </v-col>
      </v-row>
    </v-container>
    <v-snackbar v-model="snackbar" :timeout="4000" location="top">
      {{ message }}

      <template v-slot:actions>
        <v-btn color="pink" variant="text" @click="snackbar = false">
          <v-icon icon="mdi-close"></v-icon>
        </v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<script>
import Datepicker from "vuejs3-datepicker";

import axios from "axios";
import moment from "moment";
import * as XLSX from "xlsx";
import { saveAs } from "file-saver";

export default {
  name: "FulstorReportQuantity",
  components: {
    Datepicker,
  },
  data() {
    return {
      loading: false,
      dateDoMin: moment()
        .subtract(1, "months")
        .endOf("month")
        .subtract(moment().date() - 1, "days")
        .format("YYYY-MM-DD"),
      dateDoMax: moment()
        .subtract(1, "months")
        .endOf("month")
        .format("YYYY-MM-DD"),
      dateMin: moment().format("YYYY-MM-01"),
      dateMax: moment().format("YYYY-MM-DD"),
      daysBetween: 0,
      daysBetweenOld: 0,
      dataforxsls: [],
      warehouses: [],
      IDWarehouse: null,
      searchInTable: "",
      DaysOn: 21,
      headers: [
        { title: "Nazwa", key: "Nazwa", nowrap: true },
        { title: "Kod Kreskowy", key: "KodKreskowy" },
        { title: "SKU", key: "SKU" },
        { title: "Stan", key: "stan", align: "end" },
        { title: "Analiz ABC", key: "Analiz_ABC", align: "end" },
        { title: "Grupa Towarów", key: "GrupaTowarów", align: "end" },
        { title: "Dni na dostawę", key: "DniNaDostawę", align: "end" },
        { title: "Trend w %", key: "Trend", align: "end" },
        {
          title: "Sprzedaż w dniu magazynowania",
          key: "SprzedażWdniuMagazynowania",
          align: "end",
        },
        { title: "Zamówienie", key: "Zamówienie", align: "end" },
        { title: "Dni do końca", key: "DniDoKońca", align: "end" },
        {
          title: "Days in stock OkresOld",
          key: "DaysInStockOkresOld",
          align: "end",
        },
        {
          title: "Days in stock OkresNew",
          key: "DaysInStockOkresNew",
          align: "end",
        },
        { title: "Obrót OkresOld", key: "ObrótOkresOld", align: "end" },
        { title: "Obrót OkresNew", key: "ObrótOkresNew", align: "end" },
      ],
      message: "",
      snackbar: false,
    };
  },

  mounted() {
    this.getWarehouse();
    this.calculateDaysBetween();
    this.calculateDaysBetweenOld();
  },
  watch: {
    dateDoMin() {
      this.calculateDaysBetweenOld();
    },
    dateDoMax() {
      this.calculateDaysBetweenOld();
    },
    dateMin() {
      this.calculateDaysBetween();
    },
    dateMax() {
      this.calculateDaysBetween();
    },
  },

  methods: {
    calculateDaysBetween() {
      this.daysBetween = moment(this.dateMax).diff(
        moment(this.dateMin),
        "days"
      );
    },
    calculateDaysBetweenOld() {
      this.daysBetweenOld = moment(this.dateDoMax).diff(
        moment(this.dateDoMin),
        "days"
      );
    },
    getQuantity() {
      const vm = this;
      vm.loading = true;
      vm.message = "";
      let data = {};
      vm.dataforxsls = [];
      data.dataDoMin = vm.dateDoMin;
      data.dataDoMax = vm.dateDoMax;
      data.dataMin = vm.dateMin;
      data.dataMax = vm.dateMax;
      data.DaysOn = vm.DaysOn;
      data.IDMagazynu = vm.IDWarehouse;

      axios
        .post("/api/getQuantity", data)
        .then((res) => {
          if (res.status == 200) {
            vm.dataforxsls = res.data;
          } else {
            vm.message = res.data.message;
            vm.snackbar = true;
          }
          vm.loading = false;
        })
        .catch((error) => {
          console.log(error);
          vm.loading = false;
        });
    },

    getWarehouse() {
      const vm = this;
      axios
        .get("/api/getWarehouse")
        .then((res) => {
          if (res.status == 200) {
            vm.warehouses = res.data;
            vm.IDWarehouse = vm.warehouses[0].IDMagazynu;
          }
        })
        .catch((error) => console.log(error));
    },
    prepareXLSX() {
      // Создание новой книги
      this.dataforxsls.forEach((el) => {
        el.KodKreskowy = parseInt(el.KodKreskowy);
      });
      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.json_to_sheet(this.dataforxsls);
      XLSX.utils.book_append_sheet(wb, ws, "");

      // Генерация файла и его сохранение
      const wbout = XLSX.write(wb, { bookType: "xlsx", type: "array" });
      saveAs(
        new Blob([wbout], { type: "application/octet-stream" }),
        "zamovlen_" +
          moment(this.dateDoMin).format("YYYY-MM-DD") +
          "_" +
          moment(this.dateDoMax).format("YYYY-MM-DD") +
          "_" +
          moment(this.dateMin).format("YYYY-MM-DD") +
          "_" +
          moment(this.dateMax).format("YYYY-MM-DD") +
          ".xlsx"
      );
    },
  },
};
</script>
