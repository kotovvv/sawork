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
      :items="filteredDocsWZk"
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
          <v-btn
            @click="getDocsWZk"
            icon="mdi-refresh"
            :loading="loading"
            title="Odśwież dokumenty"
          ></v-btn>
          <v-btn
            @click="refreshLocations"
            icon="mdi-redo-variant"
            title="Odśwież lokalizacje"
            :loading="loading"
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
          <v-btn icon @click="showFilterDialog = true">
            <v-icon :color="isFilterActive ? 'warning' : ''">mdi-filter</v-icon>
          </v-btn>
          <v-btn
            v-if="selected && selected.length > 0"
            @click="confirmUpdateIsWartosc"
            color="success"
            variant="elevated"
          >
            <v-icon>mdi-cash-check</v-icon>
            Oznacz jako zwrócone ({{ selected.length }})
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
          <v-select
            v-model="filter_isWartosc"
            :items="[
              { title: 'Wszystkie', value: null },
              { title: 'Tak', value: 'Tak' },
              { title: 'Nie', value: 'Nie' },
            ]"
            item-title="title"
            item-value="value"
            label="Pieniądze zwrócone"
            clearable
            outlined
            dense
          ></v-select>
          <div class="d-flex ga-3 flex-wrap">
            <v-btn icon @click="clearFilters">
              <v-icon>mdi-filter-remove</v-icon>
            </v-btn>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>

    <ConfirmDlg ref="confirmDialog" />
  </div>
</template>

<script>
import Datepicker from "vuejs3-datepicker";
import moment from "moment";
import axios from "axios";

import ProductsInLocation from "./ProductsInLocation.vue";
import ConfirmDlg from "../../UI/ConfirmDlg.vue";

export default {
  name: "WZkTable",
  components: { Datepicker, ProductsInLocation, ConfirmDlg },
  props: ["user", "warehouse"],
  data: () => ({
    dialogProductsInLocation: false,
    dateMin: moment().subtract(2, "months").format("YYYY-MM-DD"),
    dateMax: moment().format("YYYY-MM-DD"),
    docsWZk: [],

    selected: null,
    marked: {},
    wzk_headers: [
      { title: "NrDokumentu", key: "NrDokumentu", nowrap: true },
      { title: "Data", key: "Data" },
      { title: "Kontrahent", key: "Kontrahent", nowrap: true },
      { title: "Uwagi fulstor", key: "Uwagi", nowrap: true },
      { title: "Uwagi Sprzedawcy", key: "uwagiSprzedawcy", nowrap: true },
      { title: "Pieniądze zwrócone", key: "isWartosc" },
      { title: "Status", key: "status" },
    ],
    searchInTable: "",
    loading: false,
    locations: {
      Zwrot: 0,
      Naprawa: 0,
      Zniszczony: 0,
    },

    location: "",
    showFilterDialog: false,
    filter_isWartosc: null, // Filter for isWartosc
  }),
  mounted() {
    this.getDocsWZk();
  },
  computed: {
    isFilterActive() {
      return this.filter_isWartosc !== null;
    },
    filteredDocsWZk() {
      if (!this.filter_isWartosc) return this.docsWZk;
      return this.docsWZk.filter((doc) => {
        if (this.filter_isWartosc === "Tak") {
          return doc.isWartosc === "Tak";
        } else if (this.filter_isWartosc === "Nie") {
          return doc.isWartosc === "Nie";
        }
        return true; // For 'Wszystkie', return all documents
      });
    },
  },
  methods: {
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
      this.$emit("item-marked", this.marked); // Emit event with marked item
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

    clearFilters() {
      this.filter_isWartosc = null;
    },

    async confirmUpdateIsWartosc() {
      if (!this.selected || this.selected.length === 0) {
        return;
      }

      const title = "Potwierdzenie aktualizacji";
      const message = `Czy na pewno chcesz oznaczyć ${this.selected.length} dokumentów jako "Pieniądze zwrócone: Tak"?`;

      try {
        const confirmed = await this.$refs.confirmDialog.open(title, message);
        if (confirmed) {
          await this.updateIsWartoscForSelected();
        }
      } catch (error) {
        console.error("Error showing confirmation dialog:", error);
      }
    },

    async updateIsWartoscForSelected() {
      if (!this.selected || this.selected.length === 0) {
        return;
      }

      this.loading = true;

      try {
        const documentIds = this.selected.map((doc) => doc.IDRuchuMagazynowego);

        const response = await axios.post("/api/updateIsWartoscBulk", {
          documentIds: documentIds,
        });

        if (response.status === 200 && response.data.success) {
          // Update local data
          this.selected.forEach((selectedDoc) => {
            const docIndex = this.docsWZk.findIndex(
              (doc) =>
                doc.IDRuchuMagazynowego === selectedDoc.IDRuchuMagazynowego
            );
            if (docIndex !== -1) {
              this.docsWZk[docIndex].isWartosc = "Tak";
            }
          });

          // Clear selection
          this.selected = [];

          console.log(
            `Successfully updated ${response.data.updatedCount} documents`
          );
        } else {
          console.error("Failed to update documents:", response.data.message);
        }
      } catch (error) {
        console.error("Error updating documents:", error);
      } finally {
        this.loading = false;
      }
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
