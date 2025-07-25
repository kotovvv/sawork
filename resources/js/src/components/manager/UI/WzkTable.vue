<template>
  <div>
    <v-progress-linear
      :active="loading"
      indeterminate
      color="purple"
    ></v-progress-linear>
    <datepicker v-model="dateMin" format="yyyy-MM-dd" monday-first></datepicker>

    <datepicker v-model="dateMax" format="yyyy-MM-dd" monday-first></datepicker>
    <v-data-table
      :items="docsWZk"
      :headers="wzk_headers"
      item-value="IDRuchuMagazynowego"
      :search="searchInTable"
      @click:row="handleClick"
      v-model="selected"
      show-select
      :row-props="colorRowItem"
      fixed-header
      return-object
    >
      <template v-slot:item.NrDokumentu="{ item }">
        <span v-if="item.photo" class="photo">{{ item.photo }} </span>
        {{ item.NrDokumentu }}
      </template>
      <template v-slot:top="{}" v-if="docsWZk.length > 0">
        <v-row class="align-center">
          <v-col class="v-col-sm-6 v-col-md-2">
            <v-text-field
              label="odzyskiwanie"
              v-model="searchInTable"
              clearable
              hide-details
            ></v-text-field>
          </v-col>
          <v-btn @click="getDocsWZk" icon="mdi-refresh"></v-btn>
          <v-btn
            @click="refreshLocations"
            icon="mdi-redo-variant"
            v-if="$props.user.IDRoli != 4"
          >
            <v-tooltip bottom>
              <template v-slot:activator="{ attrs }">
                <v-icon v-bind="attrs" :click="refreshLocations"
                  >mdi-redo-variant</v-icon
                >
              </template>
              <span>Refresh Locations</span>
            </v-tooltip>
          </v-btn>

          <v-col>
            <div class="d-flex ga-5 flex-wrap">
              <v-btn
                v-if="locations.Zwrot && $props.user.IDRoli != 4"
                @click="openDialog('Zwrot')"
                >Ilość w zwrot: {{ locations.Zwrot }}</v-btn
              >
              <v-btn v-if="locations.Naprawa" @click="openDialog('Naprawa')"
                >Naprawa: {{ locations.Naprawa }}</v-btn
              >
              <v-btn
                v-if="locations.Zniszczony"
                @click="openDialog('Zniszczony')"
                >Zniszczony: {{ locations.Zniszczony }}</v-btn
              >
            </div>
          </v-col>
          <v-col>
            <v-btn
              @click="downloadPDFs"
              :disabled="selected.length == 0"
              icon="mdi-file-download"
            ></v-btn>
          </v-col>
        </v-row>
      </template>
    </v-data-table>
    <v-dialog
      v-model="dialogProductsInLocation"
      transition="dialog-bottom-transition"
      fullscreen
    >
      <v-card>
        <v-card-title class="headline">
          <v-col>
            <v-row>
              Products in Location {{ location }}
              <v-spacer></v-spacer>
              <v-btn
                icon="mdi-close"
                @click="dialogProductsInLocation = false"
              ></v-btn>
            </v-row>
          </v-col>
        </v-card-title>
        <v-card-text>
          <ProductsInLocation
            :location="location"
            :warehouse="warehouse"
            :user="user"
          />
        </v-card-text>
        <v-card-actions> </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import Datepicker from "vuejs3-datepicker";
import moment from "moment";
import axios from "axios";

import ProductsInLocation from "./ProductsInLocation.vue";

export default {
  name: "WZkTable",
  components: { Datepicker, ProductsInLocation },
  props: ["user", "warehouse"],
  data: () => ({
    dialogProductsInLocation: false,
    dateMin: moment().subtract(2, "months").format("YYYY-MM-DD"),
    dateMax: moment().format("YYYY-MM-DD"),
    docsWZk: [],
    marked: {},
    selected: [],
    wzk_headers: [
      { title: "NrDokumentu", key: "NrDokumentu", nowrap: true },
      { title: "Data", key: "Data" },
      { title: "Kontrahent", key: "Kontrahent", nowrap: true },
      { title: "Pieniądze zwrócone", key: "isWartosc" },
      { title: "Status", key: "status" },
      { title: "Źródło", key: "Zrodlo" },
      { title: "Uwagi Sprzedawcy", key: "uwagiSprzedawcy", nowrap: true },
      { title: "Uwagi fulstor", key: "Uwagi", nowrap: true },
    ],
    searchInTable: "",
    loading: false,
    locations: {
      Zwrot: 0,
      Naprawa: 0,
      Zniszczony: 0,
    },

    location: "",
  }),
  mounted() {
    this.getDocsWZk();
  },
  methods: {
    downloadPDFs() {
      const vm = this;
      axios
        .post(
          "/api/downloadPdfs",
          {
            ids: vm.selected.map((item) => item.IDRuchuMagazynowego),
          },
          {
            responseType: "blob", // Important for binary data
          }
        )
        .then((res) => {
          if (res.status === 200) {
            // Create a blob from the response
            const url = window.URL.createObjectURL(new Blob([res.data]));
            const link = document.createElement("a");
            link.href = url;
            link.setAttribute("download", "documents.zip");
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
          }
        })
        .catch((error) => console.log(error));
    },
    openDialog(location) {
      this.location = location;
      this.dialogProductsInLocation = true;
    },

    refreshLocations() {
      const vm = this;
      vm.loading = true;
      axios
        .post("/api/refreshLocations", {
          IDWarehouse: vm.$props.warehouse.IDMagazynu,
          dateMin: vm.dateMin,
          dateMax: vm.dateMax,
        })
        .then((res) => {
          if (res.status == 200) {
          }
          vm.loading = false;
        })
        .catch((error) => console.log(error));
    },
    handleClick(e, row) {
      this.marked = row.item;
      this.$emit("item-selected", this.marked); // Emit event with selected item
    },

    colorRowItem(item) {
      if (
        item.item.IDRuchuMagazynowego != undefined &&
        item.item.IDRuchuMagazynowego == this.marked.IDRuchuMagazynowego
      ) {
        return { class: "bg-red-darken-4" };
      }
    },
    getDocsWZk() {
      const vm = this;

      if (vm.$props.warehouse.IDMagazynu == null) return;
      vm.docsWZk = [];
      vm.loading = true;
      axios
        .post("/api/getDocsWZk", {
          IDWarehouse: vm.$props.warehouse.IDMagazynu,
          dateMin: vm.dateMin,
          dateMax: vm.dateMax,
        })
        .then((res) => {
          if (res.status == 200) {
            vm.docsWZk = res.data.DocsWZk;
            vm.docsWZk.map((e) => {
              e.Data = e.Data.substring(0, 16);
            });
            vm.locations.Zwrot = res.data.Zwrot;
            vm.locations.Naprawa = res.data.Naprawa;
            vm.locations.Zniszczony = res.data.Zniszczony;
          }
          vm.loading = false;
        })
        .catch((error) => console.log(error));
    },
  },
};
</script>

<style scoped>
.photo {
  background-color: #bbdefb;
  color: rgb(0, 0, 0);
  padding: 0 2px;
  border-radius: 8px;
  font-size: 0.7rem;
}
</style>
