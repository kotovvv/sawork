<template>
  <v-container class="container align-center px-1">
    <v-row>
      <v-col md="6" cols="12">
        <v-number-input
          v-model="days"
          controlVariant="default"
          label="liczba dni"
          hide-details="auto"
          :hideInput="false"
          :inset="false"
          :max="20"
          :min="3"
        ></v-number-input>
      </v-col>
      <v-col md="6" cols="12">
        <div class="d-flex">
          <v-select
            label="Magazyn"
            v-model="selectedWarehause"
            :items="warehouses"
            item-title="Nazwa"
            item-value="IDMagazynu"
            hide-details="auto"
            return-object
          ></v-select>
          <v-btn
            size="x-large"
            class="btn primary"
            @click="
              TowarLocationTipTab();
              createdDoc = {};
            "
            >Odbiór</v-btn
          >
        </div>
      </v-col>
    </v-row>
    <v-row>
      <!--  -->
      <v-col cols="12"
        ><v-data-table
          :items="dataTowarLocationTipTab.filter((l) => l.peremestit > 0)"
          :loading="loading"
          @click:row="clickRow"
        ></v-data-table
      ></v-col>
    </v-row>
    <!-- contenteditable="true" -->
    <v-dialog
      id="dialogLocation"
      ref="dLocation"
      v-model="dialogLocation"
      transition="dialog-bottom-transition"
      fullscreen
      @keyup="handleKeypress"
    >
      <v-container>
        <v-card height="80vh" class="ovoverflow-y-auto">
          <v-card-title class="mb-5 bg-grey-lighten-3">
            <v-row>
              <v-col>
                <b
                  >Z lokalizacji: {{ selected_item.LocationCode }}
                  <span v-if="step >= 1"
                    ><v-icon
                      icon="mdi-checkbox-marked-circle-outline"
                      color="green"
                    ></v-icon></span
                ></b>
              </v-col>
              <v-spacer></v-spacer>

              <div
                class="btn border"
                @click="
                  dialogLocation = false;
                  clear();
                  text = '';
                "
              >
                <v-icon icon="mdi-close"></v-icon></div
            ></v-row>
          </v-card-title>

          <v-card-text>
            <h5 class="text-red" v-if="message">{{ message }}</h5>
            <!-- step 1 -->
            <h3 class="text-red" v-if="step == 0">Potwierdź lokalizację!</h3>
            <v-row class="product_line border my-0" v-if="product">
              <v-col>
                <div class="d-flex">
                  <img
                    v-if="product.Zdjecie"
                    :src="'data:image/jpeg;base64,' + product.Zdjecie"
                    alt="pic"
                    style="height: 3em"
                  />
                  <span
                    ><h5>
                      {{ product.Nazwa }}<br />cod: {{ product.KodKreskowy }},
                      sku: {{ product.sku }}
                    </h5>
                  </span>
                  <!-- <v-btn
										class="d-none"
										@click="
											edit = product;
											dialogMessageQty = true;
										"
										><v-icon>mdi-pencil</v-icon></v-btn
									> -->
                </div>
              </v-col>

              <v-col>
                <div class="d-flex justify-end">
                  <div class="btn border" @click="changeCounter(product, -1)">
                    -
                  </div>
                  <div
                    :id="product.IDTowaru"
                    class="border qty text-h5 text-center"
                  >
                    {{ product.qty }} z
                    {{ parseInt(selected_item.peremestit) }} (<small>{{
                      parseInt(selected_item.Quantity)
                    }}</small
                    >)
                  </div>
                  <div class="btn border" @click="changeCounter(product, 1)">
                    +
                  </div>
                </div>
              </v-col>
            </v-row>
          </v-card-text>
          <template v-slot:actions>
            <v-row v-if="product">
              <v-col cols="12">
                <b
                  >Do lokalizacji:
                  <span v-if="toLocation.LocationCode ?? 0" class="px-2"
                    >{{ toLocation.LocationCode }}

                    <v-icon
                      v-if="toLocation.LocationCode ?? 0"
                      icon="mdi-checkbox-marked-circle-outline"
                      color="green"
                    ></v-icon></span
                ></b>

                <v-table density="compact">
                  <thead>
                    <tr>
                      <th class="text-left">LocationCode</th>
                      <th class="text-left">Quantity</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="l in toLocations"
                      :key="l.idLocationCode"
                      :class="{
                        'bg-green-lighten-4':
                          l.LocationCode == toLocation.LocationCode,
                      }"
                    >
                      <td>{{ l.LocationCode }}</td>
                      <td>{{ l.Quantity }}</td>
                    </tr>
                  </tbody>
                </v-table>
              </v-col>
              <v-col>
                <v-btn
                  v-if="step == 3"
                  class="btn primary mb-5"
                  variant="tonal"
                  @click.once="doRelokacja"
                  >Relokacja</v-btn
                >
              </v-col>
            </v-row>
          </template>
          <v-progress-linear
            :active="loading"
            indeterminate
            color="purple"
          ></v-progress-linear>
          <p class="text-grey px-4">{{ test }}</p>
        </v-card>
      </v-container>
    </v-dialog>
  </v-container>
</template>

<script>
import { VNumberInput } from "vuetify/labs/VNumberInput";
import axios from "axios";
export default {
  components: {
    VNumberInput,
  },
  name: "locationComponent",

  data() {
    return {
      headers: [
        { text: "IDMagazyn", value: "IDMagazynu" },
        { text: "Magazyn", value: "Nazwa" },
        { text: "Details", value: "eMailAddress" },
        { text: "Dokument Cod", value: "cod", name: "cod", width: "180" },
        { text: "Action", value: "actions", sortable: false },
      ],
      dataTowarLocationTipTab: [],
      warehouses: [],
      warehousesLoc: [],
      warehouseLocations: [],
      selectedWarehause: null,
      days: 20,
      snackbar: false,
      message: "",
      loading: false,
      dialogLocation: false,
      location: "",
      selected_item: {},
      step: 0,
      imputCod: "",
      message: "",
      product: null,
      toLocations: [],
      toLocation: {},
      test: "",
      createdDoc: {},
    };
  },
  mounted() {
    this.getWarehouse();
  },
  watch: {},
  methods: {
    clear() {
      this.step = 0;
      this.loading = false;
      this.imputCod = "";
      this.test = "";
      this.selected_item = {};
      this.product = null;
      this.message = "";
      this.toLocation = {};
    },
    changeCounter: function (item, num) {
      item.qty = parseInt(item.qty) + parseInt(+num);
      if (item.qty < 0) item.qty = 0;
      //this.setFocus();
    },
    steps() {
      const vm = this;
      this.message = "";

      // this.imputCod = this.imputCod.replaceAll(/Shift(.)/g, (_, p1) => p1.toUpperCase());
      this.imputCod = this.imputCod.replace("Unidentified", "");

      if (this.step == 0) {
        if (this.imputCod == this.selected_item.LocationCode) {
          this.step = 1;
          this.toLocations = this.dataTowarLocationTipTab.filter(
            (l) =>
              l.KodKreskowy == this.selected_item.KodKreskowy &&
              //l.LocationCode != this.selected_item.LocationCode &&
              l.TypLocations != 2
          );
          vm.loading = true;
          // get product this.selected_item.IDTowaru
          axios
            .get("/api/getProduct/" + this.selected_item.IDTowaru)
            .then((res) => {
              if (res.status == 200) {
                vm.product = res.data;
                vm.loading = false;
                // vm.getPZ();
              }
            })
            .catch((error) => console.log(error));
          return;
        } else {
          this.message = "Błąd lokalizacji (";
        }
      }
      if (this.step == 1) {
        let to_loc = this.warehousesLoc.find((f) => {
          return f.LocationCode == this.imputCod;
        });
        if (to_loc && this.product.qty > 0) {
          this.step = 3;
          this.toLocation = to_loc;
          return;
        }
        if (this.imputCod == this.selected_item.KodKreskowy) {
          this.changeCounter(this.product, 1);
        } else {
          if (/[a-zA-Z]+/.test(this.imputCod)) {
            this.message = "Błąd lokalizacji (";
          } else {
            this.message = "Brak produktu!!!";
          }
        }
      }
      if (this.step == 3) {
        let to_loc = this.warehousesLoc.find((f) => {
          return f.LocationCode == this.imputCod;
        });
        if (to_loc && this.product.qty > 0) {
          this.toLocation = to_loc;
          return;
        }
      }
    },
    handleKeypress(event) {
      if (event.key === "Shift") {
        return;
      }
      if (event.key === "Enter") {
        this.steps();
        this.imputCod = "";
      } else {
        let key = event.key;
        this.imputCod += key;
        this.test = this.imputCod;
      }
    },
    clickRow(event, row) {
      this.clear();
      this.selected_item = row.item;
      this.dialogLocation = true;
    },
    getWarehouseLocations() {
      const vm = this;
      vm.warehouseLocations = [];
      axios
        .get("/api/getWarehouseLocations/" + vm.$props.warehouse.IDMagazynu)
        .then((res) => {
          if (res.status == 200) {
            vm.warehouseLocations = res.data;
          }
        })
        .catch((error) => console.log(error));
    },
    TowarLocationTipTab() {
      const vm = this;
      if (!vm.selectedWarehause) return;
      vm.loading = true;
      let data = {};
      data.stor = vm.selectedWarehause.IDMagazynu;
      data.days = vm.days;
      axios
        .post("/api/TowarLocationTipTab", data)
        .then((res) => {
          if (res.status == 200) {
            vm.dataTowarLocationTipTab = res.data;
            vm.loading = false;
            vm.getWarehouseLocations();
          }
        })
        .catch((error) => console.log(error));
    },
    getWarehouse() {
      const vm = this;
      axios
        .get("/api/getWarehouse")
        .then((res) => {
          if (res.status == 200) {
            vm.warehouses = res.data;
          }
        })
        .catch((error) => console.log(error));
    },
    getWarehouseLocations() {
      const vm = this;
      axios
        .get("/api/getWarehouseLocations/" + vm.selectedWarehause.IDMagazynu)
        .then((res) => {
          if (res.status == 200) {
            vm.warehousesLoc = res.data;
          }
        })
        .catch((error) => console.log(error));
    },
    getPZ() {
      const vm = this;
      axios
        .get(
          "/api/getPZ/" +
            vm.selected_item.IDTowaru +
            "/" +
            vm.selected_item.LocationCode
        )
        .then((res) => {
          if (res.status == 200) {
            console.log(res.data);
          }
        })
        .catch((error) => console.log(error));
    },
    doRelokacja() {
      const vm = this;
      let data = {};
      vm.loading = true;
      vm.message = "";
      data.IDTowaru = vm.product.IDTowaru;
      data.qty = vm.product.qty;

      data.fromLocation = vm.warehouseLocations.find((w) => {
        return w.LocationCode == vm.selected_item.LocationCode;
      });
      data.toLocation = vm.toLocation;
      data.selectedWarehause = vm.selectedWarehause;
      data.createdDoc = vm.createdDoc;
      axios
        .post("/api/doRelokacja", data)
        .then((res) => {
          if (res.status == 200) {
            vm.createdDoc = res.data.createdDoc;
            vm.loading = false;
            vm.message = `Dokumenty przeniesienia zostały utworzone. ${vm.createdDoc.idmin} ${vm.createdDoc.idpls}`;
            vm.dataTowarLocationTipTab = vm.dataTowarLocationTipTab.filter(
              (o) => o !== vm.selected_item
            );
          }
        })
        .catch((error) => console.log(error));
    },
  },
};
</script>
