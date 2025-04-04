<template>
  <v-container>
    <v-row>
      <v-col md="6" sm="12">
        <v-select
          label="Magazyn"
          v-model="IDWarehouse"
          :items="warehouses"
          item-title="Nazwa"
          item-value="IDMagazynu"
          @change="clear()"
          hide-details="auto"
        ></v-select>
      </v-col>
      <v-col md="6" sm="12">
        <div class="d-flex">
          <v-select
            v-model="selectedMonth"
            :items="month"
            item-title="name"
            item-value="id"
            label="miesiąc"
            persistent-hint
            single-line
          ></v-select>
          <v-btn @click="getDataForXLS()" size="x-large">uzyskać XLSX</v-btn>
        </div></v-col
      >
    </v-row>
    <v-progress-linear
      :active="loading"
      indeterminate
      color="purple"
    ></v-progress-linear>
  </v-container>
</template>

<script>
import moment from "moment";
import axios from "axios";
import * as XLSX from "xlsx";
import { saveAs } from "file-saver";

export default {
  name: "GetXLSX",

  data() {
    return {
      loading: false,
      selectedMonth: moment().month(),
      month: [
        { id: 0, name: "01 styczeń" },
        { id: 1, name: "02 luty" },
        { id: 2, name: "03 marzec" },
        { id: 3, name: "04 kwiecień" },
        { id: 4, name: "05 maj" },
        { id: 5, name: "06 czerwiec" },
        { id: 6, name: "07 lipiec" },
        { id: 7, name: "08 sierpień" },
        { id: 8, name: "09 wrzesień" },
        { id: 9, name: "10 październik" },
        { id: 10, name: "11 listopad" },
        { id: 11, name: "12 grudzień" },
      ],
      IDWarehouse: null,
      warehouses: [],
      curyear: moment().year(),
      curmonth: moment().month(),
      curdate: moment().date(),
      dataforxsls: [],
    };
  },

  mounted() {
    this.getWarehouse();
    this.month = this.month.filter((m) => {
      return m.id <= this.curmonth;
    });
    if (this.curmonth == 0) {
      this.month.unshift({ id: 11, name: "12 grudzień" });
    }
  },

  methods: {
    prepareXLSX() {
      let all = [];
      let sum = 0;
      let koef = this.warehouses.find(
        (w) => w.IDMagazynu == this.IDWarehouse
      ).koef;
      // Создание новой книги
      const wb = XLSX.utils.book_new();

      // get sum
      this.dataforxsls.forEach((sheet) => {
        let m3 = sheet[1].reduce((acc, o) => acc + parseFloat(o.m3xstan), 0);
        sum += parseFloat(m3 * koef);
        all.push({ day: sheet[0], m3: parseFloat(m3).toFixed(2), zl: m3 * koef });
      });
      all.push({ day: "Итого", m3: "", zl: sum });

      const ws = XLSX.utils.json_to_sheet(all);
      XLSX.utils.book_append_sheet(wb, ws, "Итого");

      this.dataforxsls.forEach((sheet) => {
        sheet[1].forEach((item) => {
          item.stan = parseFloat(item.stan);
          item.Wartosc = parseFloat(item.Wartosc);
          //item.m3xstan = parseFloat(item.m3xstan);
        });
        const ws = XLSX.utils.json_to_sheet(sheet[1]);
        XLSX.utils.book_append_sheet(wb, ws, sheet[0]);
      });

      // Генерация файла и его сохранение
      const wbout = XLSX.write(wb, { bookType: "xlsx", type: "array" });
      saveAs(
        new Blob([wbout], { type: "application/octet-stream" }),
        "Зберігання " +
          this.warehouses.find((w) => w.IDMagazynu == this.IDWarehouse).Symbol +
          " " +
          this.month.find((m) => m.id == this.selectedMonth).name +
          ".xlsx"
      );
    },

    getDataForXLS() {
      const vm = this;
      let data = {};
      vm.loading = true;
      data.IDWarehouse = vm.IDWarehouse;
      data.month = vm.selectedMonth;
      data.year = vm.curyear;
      if (vm.curmonth == 0 && vm.selectedMonth == 11) {
        data.year = vm.curyear - 1;
      }
      axios
        .post("/api/getDataForXLS", data)
        .then((res) => {
          if (res.status == 200) {
            vm.dataforxsls = Object.entries(res.data);
            vm.loading = false;
            vm.prepareXLSX();
          }
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
            vm.warehouses = vm.warehouses.map((w) => {
              w.koef = parseFloat(w.koef);
              return w;
            });
          }
        })
        .catch((error) => console.log(error));
    },
  },
};
</script>
