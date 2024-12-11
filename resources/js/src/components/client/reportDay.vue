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
            v-model="date"
            format="yyyy-MM-dd"
            monday-first
          ></datepicker>
          <!-- <v-date-input
						v-model="date"
						label="Select a date"
						width="368"
						max-width="400"
						first-day-of-week="1"
						keyboardDate
						location="pl-PL"
					></v-date-input
					>--></v-col
        >
        <v-col>
          <div class="d-flex">
            <v-btn @click="getDataForXLSDay()" size="x-large"
              >uzyskać dane</v-btn
            >
            <v-btn
              v-if="dataforxsls.length"
              @click="prepareXLSX()"
              size="x-large"
              >pobieranie XLSX</v-btn
            >
          </div>
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
    <v-container fluid v-if="dataforxsls.length">
      <v-row>
        <v-col cols="12">
          <v-data-table
            :items="dataforxsls[0][1]"
            :headers="headers"
            item-value="IDTowaru"
            :search="searchInTable"
            @click:row="handleClick"
            select-strategy="single"
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
                <productHistory :product_id="selected[0]" />
              </v-row>
            </template>
          </v-data-table>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script>
// import { VDateInput } from 'vuetify/labs/VDateInput';
import Datepicker from "vuejs3-datepicker";
import moment from "moment";
import axios from "axios";
import * as XLSX from "xlsx";
import { saveAs } from "file-saver";
import productHistory from "./productHistory.vue";

export default {
  name: "reportDay",

  components: {
    productHistory,
    //VDateInput,
    Datepicker,
  },
  data: () => ({
    selected: [],
    searchInTable: "",
    loading: false,
    date: moment().format("YYYY-MM-DD"),
    dataforxsls: [],
    IDWarehouse: null,
    warehouses: [],
    headers: [
      { title: "Nazwa towaru", key: "Nazwa", nowrap: true },
      { title: "Kod kreskowy", key: "KodKreskowy" },
      { title: "SKU", key: "sku" },
      { title: "Wartość", key: "wartosc" },
      { title: "Stan", key: "stan" },
      { title: "Rezerv", key: "rezerv" },
      { title: "Zniszczony", key: "Zniszczony" },
      { title: "Naprawa", key: "Naprawa" },

      { title: "Pozostać", key: "pozostać" },
    ],
  }),
  mounted() {
    this.getWarehouse();
    if (this.$attrs.user.IDRoli == 1) {
      this.headers.push({ title: "m3xstan", key: "m3xstan", sortable: false });
    }
  },
  methods: {
    colorRowItem(item) {
      if (
        item.item.IDTowaru != undefined &&
        item.item.IDTowaru == this.selected[0]
      ) {
        return { class: "bg-red-darken-4" };
      }
    },
    handleClick(event, row) {
      this.selected = [row.item.IDTowaru];
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
    getDataForXLSDay() {
      const vm = this;
      vm.loading = true;
      vm.dataforxsls = [];
      axios
        .get(
          "/api/getDataForXLSDay/" +
            moment(vm.date).format("YYYY-MM-DD") +
            "/" +
            vm.IDWarehouse
        )
        .then((res) => {
          if (res.status == 200) {
            vm.dataforxsls = Object.entries(res.data);
            // console.log(vm.dataforxsls);
            vm.dataforxsls[0][1].forEach((el) => {
              el.stan = parseInt(el.stan);
              el.pozostać = parseInt(el.pozostać);
              el.rezerv = parseInt(el.rezerv);
              el.wartosc = parseFloat(el.wartosc).toFixed(2);
              el.m3xstan = parseFloat(el.m3xstan).toFixed(2);
            });
            vm.selected[0] = vm.dataforxsls[0][1][0].IDTowaru;
            vm.loading = false;
          }
        })
        .catch((error) => {
          console.log(error);
          vm.loading = false;
        });
    },
    prepareXLSX() {
      let all = [];
      let sum = 0;
      // Создание новой книги
      const wb = XLSX.utils.book_new();

      if (this.$attrs.user.IDRoli == "1") {
        // get sum
        this.dataforxsls.forEach((sheet) => {
          let m3 = sheet[1].reduce((acc, o) => acc + parseFloat(o.m3xstan), 0);
          sum += parseFloat(m3 * 2.1);
          all.push({ day: sheet[0], m3: m3, zl: m3 * 2.1 });
        });
        all.push({ day: "Итого", m3: "", zl: sum });

        const ws = XLSX.utils.json_to_sheet(all);
        XLSX.utils.book_append_sheet(wb, ws, "Итого");
      }

      this.dataforxsls.forEach((sheet) => {
        sheet[1].forEach((item) => {
          item.stan = parseFloat(item.stan);
          item.wartosc = parseFloat(item.wartosc);
          item.m3xstan = parseFloat(item.m3xstan);
        });
        const ws = XLSX.utils.json_to_sheet(sheet[1]);
        XLSX.utils.book_append_sheet(wb, ws, sheet[0]);
      });

      // Генерация файла и его сохранение
      const wbout = XLSX.write(wb, { bookType: "xlsx", type: "array" });
      saveAs(
        new Blob([wbout], { type: "application/octet-stream" }),
        "Зберігання " +
          this.date.toLocaleString().substring(0, 10).replaceAll(".", "_") +
          ".xlsx"
      );
    },
  },
};
</script>
