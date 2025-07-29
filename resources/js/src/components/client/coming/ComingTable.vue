<template>
  <div>
    <v-progress-linear
      :active="loading"
      indeterminate
      color="purple"
    ></v-progress-linear>

    <v-data-table
      :items="docsDM"
      :headers="headers"
      item-value="IDRuchuMagazynowego"
      :search="searchInTable"
      @click:row="handleClick"
      select-strategy="single"
      :row-props="colorRowItem"
      fixed-header
      return-object
    >
      <template v-slot:[`item.NrDokumentu`]="{ item }">
        <v-icon
          size="small"
          icon="mdi-eye-check-outline"
          v-if="item.noBaselink > 0"
        ></v-icon>
        <span v-if="item.doc" class="doc">{{ item.doc }}</span>
        <span v-if="item.photo" class="photo">{{ item.photo }} </span>
        <v-icon
          color="yellow"
          size="small"
          icon="mdi-alert"
          v-if="item.brk"
        ></v-icon>
        <span v-if="item.ready" class="percent">{{ item.ready }}%</span>
        {{ item.NrDokumentu }}
      </template>
      <template v-slot:top v-if="docsDM.length">
        <v-row class="align-center">
          <v-col class="v-col-sm-6 v-col-md-2">
            <v-text-field
              label="odzyskiwanie"
              v-model="searchInTable"
              clearable
              hide-details
            ></v-text-field>
          </v-col>
          <v-btn @click="getDM" icon="mdi-refresh"></v-btn>
          <v-btn
            @click="openImportDialog"
            icon="mdi-file-import"
            color="primary"
            class="ml-2"
          ></v-btn>
          <v-btn
            @click="openAddProductDialog"
            icon="mdi-plus"
            color="success"
            class="ml-2"
          ></v-btn>
        </v-row>
      </template>
    </v-data-table>

    <!-- Dialog for DM import -->
    <v-dialog v-model="importDialog">
      <v-card>
        <v-card-title>
          <span class="text-h5">Import DM</span>
          <v-btn
            icon="mdi-close"
            variant="text"
            style="position: absolute; right: 8px; top: 8px"
            @click="closeImportDialog"
          ></v-btn>
        </v-card-title>
        <v-card-text>
          <DM :IDWarehouse="IDWarehouse" @import-success="onImportSuccess" />
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="blue darken-1" text @click="closeImportDialog">
            Zamknij
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Dialog for adding single product -->
    <v-dialog v-model="addProductDialog" max-width="800px">
      <v-card>
        <v-card-title>
          <span class="text-h5">Dodaj towar do magazynu</span>
          <v-btn
            icon="mdi-close"
            variant="text"
            style="position: absolute; right: 8px; top: 8px"
            @click="closeAddProductDialog"
          ></v-btn>
        </v-card-title>
        <v-card-text>
          <AddSingleProduct
            :IDWarehouse="IDWarehouse"
            @product-added="onProductAdded"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="blue darken-1" text @click="closeAddProductDialog">
            Zamknij
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import axios from "axios";
import DM from "./DM.vue";
import AddSingleProduct from "./AddSingleProduct.vue";

export default {
  name: "ComingTable",
  components: {
    DM,
    AddSingleProduct,
  },
  props: ["IDWarehouse"],
  data: () => ({
    docsDM: [],
    selected: {},
    importDialog: false,
    addProductDialog: false,
    headers: [
      { title: "Data", key: "Data" },
      { title: "Nr Dokumentu", key: "NrDokumentu", sortable: false },
      {
        title: "Wartość Dokumentu",
        key: "WartoscDokumentu",
        sortable: false,
        align: "end",
      },
      { title: "Status", key: "status", nowrap: true },
      { title: "Uwaga", key: "Uwagi", nowrap: true, sorted: false },
    ],
    searchInTable: "",
    loading: false,
  }),
  mounted() {
    this.getDM();
  },
  methods: {
    handleClick(e, row) {
      this.selected = row.item;
      this.$emit("item-selected", this.selected); // Emit event with selected item
    },

    colorRowItem(item) {
      if (
        item.item.IDRuchuMagazynowego != undefined &&
        item.item.IDRuchuMagazynowego == this.selected.IDRuchuMagazynowego
      ) {
        return { class: "bg-red-darken-4" };
      }
    },
    getDM() {
      const vm = this;
      let data = {};
      vm.loading = true;
      data.IDMagazynu = vm.IDWarehouse;
      axios
        .post("/api/getDM", data)
        .then((res) => {
          if (res.status == 200) {
            vm.docsDM = res.data;
            vm.docsDM.forEach((el) => {
              el.Data = el.Data.substr(0, 16);
              el.brk = el.brk == "1" ? true : false;
              el.WartoscDokumentu = parseFloat(el.WartoscDokumentu).toFixed(2);
              if (el.ID1) {
                el.status =
                  "Towary przyjęte na magazyn (" + el.RelatedNrDokumentu + ")";
              } else {
                el.status = "Oczekiwanie na dostawę";
              }
            });
          }
          vm.loading = false;
        })
        .catch((error) => console.log(error));
    },

    openImportDialog() {
      this.importDialog = true;
    },

    closeImportDialog() {
      this.importDialog = false;
    },

    onImportSuccess() {
      this.closeImportDialog();
      this.getDM(); // Refresh the table after successful import
    },

    openAddProductDialog() {
      this.addProductDialog = true;
    },

    closeAddProductDialog() {
      this.addProductDialog = false;
    },

    onProductAdded() {
      this.closeAddProductDialog();
      this.getDM(); // Refresh the table after successful product addition
    },
  },
};
</script>

<style scoped>
.doc {
  background-color: orange;
  color: rgb(0, 0, 0);
  padding: 0 2px;
  border-radius: 0;
  font-size: 0.7rem;
}
.photo {
  background-color: #bbdefb;
  color: rgb(0, 0, 0);
  padding: 0 2px;
  border-radius: 8px;
  font-size: 0.7rem;
}
.percent {
  background-color: #ffcc80;
  color: rgb(0, 0, 0);
  padding: 0 2px;
  border-radius: 8px;
  font-size: 0.7rem;
}
</style>
