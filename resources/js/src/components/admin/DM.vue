<template>
  <div id="importXLS">
    <v-snackbar v-model="snackbar" top right timeout="-1">
      <v-card-text>
        {{ message }}
      </v-card-text>
      <template v-slot:action="{ attrs }">
        <v-btn color="pink" text v-bind="attrs" @click="snackbar = false">
          X
        </v-btn>
      </template>
    </v-snackbar>

    <v-container fluid>
      <v-row>
        <v-col cols="2">
          <v-select
            label="Magazyn"
            v-model="IDWarehouse"
            :items="warehouses"
            item-title="Nazwa"
            item-value="IDMagazynu"
            @update:modelValue="clear()"
            hide-details="auto"
          ></v-select>
        </v-col>
        <v-col cols="2" v-if="IDWarehouse">
          <v-file-input
            v-model="files"
            ref="fileupload"
            label="przesyłanie Excel"
            show-size
            truncate-length="24"
            @change="onFileChange"
          ></v-file-input>
        </v-col>
      </v-row>
      <v-progress-linear
        :active="loading"
        indeterminate
        color="purple"
      ></v-progress-linear>
      <v-row v-if="table.length && files">
        <v-col cols="12">
          <p>Loaded {{ table.length }} rows</p>
          <p v-if="table.length > 0">Columns: {{ table[0].length }}</p>
          <v-btn color="primary" @click="makeJson"> Make JSON </v-btn>
        </v-col>

        <v-col cols="9">
          <v-table id="loadedTable">
            <thead>
              <tr>
                <th v-for="el in table[0].length" :key="el">
                  <v-select
                    :items="[
                      '',
                      'Nazwa',
                      'SKU',
                      'EAN',
                      'Ilość',
                      'jednostka',
                      'Cena',
                      'Waga',
                      'Długość (cm)',
                      'Szerokość (cm)',
                      'Wysokość (cm)',
                      'Informacje dodatkowe ',
                    ]"
                    outlined
                    @update:modelValue="makeHeader"
                  >
                  </v-select>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, ix) in table" :key="ix">
                <td v-for="(it, i) in item" :key="i">{{ it }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script>
import * as XLSX from "xlsx";
import axios from "axios";
import _ from "lodash";
export default {
  name: "FulstorImportXLS",
  props: ["user"],
  data: () => ({
    loading: false,

    errorMessages: [],
    message: "",
    snackbar: false,

    files: null,
    table: [],
    header: [],
    IDWarehouse: null,
    warehouses: [],
  }),

  mounted() {
    this.getWarehouse();
  },
  watch: {},
  methods: {
    makeJson() {
      if (this.table.length) {
        const result = [];
        for (let i = 1; i < this.table.length; i++) {
          const row = {};
          for (let j = 0; j < this.header.length; j++) {
            if (this.header[j]) {
              row[this.header[j]] = this.table[i][j];
            }
          }
          result.push(row);
        }
        console.log(result);
        this.message = "JSON created in console";
        this.snackbar = true;
      }
    },
    clear() {
      this.files = null;
      this.table = [];
      this.header = [];
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
    getheader() {
      setTimeout(() => {
        const tableElement = document.querySelector("#loadedTable thead");
        if (tableElement) {
          this.header = tableElement.innerText
            .split("\t")
            .map((i) => i.replaceAll(/[\W_]+/g, ""));
          console.log("Header:", this.header);
        }
      }, 300);
    },
    makeHeader() {
      this.getheader();
    },
    onFileChange(event) {
      // event - объект события change
      const fileList = event && event.target ? event.target.files : null;
      let file = fileList && fileList.length ? fileList[0] : null;
      if (!file) return;

      // Проверяем расширение файла
      const allowedExtensions = [".xlsx", ".xls"];
      const fileName = file.name ? file.name.toLowerCase() : "";
      const hasValidExtension = allowedExtensions.some((ext) =>
        fileName.endsWith(ext)
      );

      // Проверяем MIME-тип файла
      const allowedTypes = [
        "application/vnd.ms-excel",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      ];
      const hasValidType = allowedTypes.includes(file.type);

      if (hasValidExtension || hasValidType) {
        this.files = file; // сохраняем только сам файл
        this.createInput(file);
      } else {
        this.files = null;
        this.message =
          "Nieprawidłowy format pliku. Pobierz plik Excel (.xlsx lub .xls)";
        this.snackbar = true;
      }
    },
    createInput(f) {
      let vm = this;
      var reader = new FileReader();

      reader.onload = function (e) {
        var data = e.target.result,
          fixedData = vm.fixdata(data),
          workbook = XLSX.read(btoa(fixedData), { type: "base64" }),
          firstSheetName = workbook.SheetNames[0],
          worksheet = workbook.Sheets[firstSheetName];

        vm.loading = true;
        setTimeout(() => {
          vm.table = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
          console.log("Loaded table data:", vm.table);
          console.log("Table length:", vm.table.length);
          vm.loading = false;
        }, 100);
      };
      reader.readAsArrayBuffer(f);
    },
    fixdata(data) {
      var o = "",
        l = 0,
        w = 10240;
      for (; l < data.byteLength / w; ++l)
        o += String.fromCharCode.apply(
          null,
          new Uint8Array(data.slice(l * w, l * w + w))
        );
      o += String.fromCharCode.apply(null, new Uint8Array(data.slice(l * w)));
      return o;
    },
  },
};
</script>

<style >
#inspire #importXLS .v-text-field__details {
  display: initial;
}
#loadedTable .v-data-table__wrapper {
  overflow: auto;
  height: 80vh;
}
</style>
