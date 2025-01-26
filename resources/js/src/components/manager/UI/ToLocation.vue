'<template>
  <div>
    <v-snackbar v-model="snackbar" timeout="6000" location="top">
      {{ message }}

      <template v-slot:actions>
        <v-btn
          color="pink"
          variant="text"
          icon="mdi-close"
          @click="snackbar = false"
        >
          Close
        </v-btn>
      </template>
    </v-snackbar>
    <v-btn @click="dialogLocation = true" icon="mdi-forklift"></v-btn>
    <v-dialog
      id="dialogLocation"
      ref="dLocation"
      v-model="dialogLocation"
      transition="dialog-bottom-transition"
      fullscreen
      @keyup="handleKeypress"
    >
      <v-container>
        <v-card height="80vh" class="overflow-y-auto">
          <v-card-title class="mb-5 bg-grey-lighten-3">
            <v-row>
              <v-col>
                <b
                  >Z lokalizacji: {{ fromLocation }}
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
            <v-row>
              <v-spacer></v-spacer>
              <GetQrCode @result="handleResult" />
            </v-row>
            <h5 class="text-red" v-if="message">{{ message }}</h5>
            <!-- step 1 -->
            <h3 class="text-red" v-if="step == 0">Potwierdź lokalizację!</h3>
            <h3 class="text-red" v-if="step == 1 && product == null">
              Skanowanie kodu kreskowego
            </h3>
            <v-row class="product_line border my-0" v-if="product != null">
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
                    {{ parseInt(product.ilosc) }}
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

                <v-table density="compact" style="width: 300px">
                  <thead>
                    <tr>
                      <th class="text-left">LocationCode</th>
                      <th class="text-left">Quantity</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="l in productLocations.filter(
                        (f) => f.LocationCode != fromLocation
                      )"
                      :key="l.idLocationCode"
                      :class="{
                        'bg-green-lighten-4':
                          l.LocationCode == toLocation.LocationCode,
                      }"
                    >
                      <td>{{ l.LocationCode }}</td>
                      <td>{{ l.ilosc }}</td>
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
  </div>
</template>

<script>
import axios from "axios";

import GetQrCode from "../../UI/GetQrCode.vue";
export default {
  name: "FulstorToLocation",
  components: {
    GetQrCode,
  },
  props: {
    products: {
      type: Object,
      required: true,
    },
    IDWarehouse: {
      type: String,
      required: true,
    },
    startStep: {
      type: Number,
      default: 0,
    },
    location: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      snackbar: false,
      dialogLocation: false,
      product: null,
      fromLocation: this.$props.location,
      toLocation: {},
      step: this.$props.startStep,
      loading: false,
      message: "",
      test: "",
      imputCod: "",
      warehouseLocations: [],
      productLocations: [],
      selectedWarehause: null,
      createdDoc: {},
    };
  },

  mounted() {
    this.getWarehouseLocations();
  },

  methods: {
    getProductLocations() {
      const vm = this;
      vm.productLocations = [];
      axios
        .get("/api/getProductLocations/" + vm.product.IDTowaru)
        .then((res) => {
          if (res.status == 200) {
            vm.productLocations = res.data;
            vm.productLocations = vm.productLocations.map((f) => {
              f.ilosc = parseInt(f.ilosc);
              return f;
            });
          }
        })
        .catch((error) => console.log(error));
    },

    handleResult(result) {
      console.log(result);
      this.message = result.data;
      this.imputCod = result.data;
      // Handle the result data here
    },
    getWarehouseLocations() {
      const vm = this;
      vm.warehouseLocations = [];
      axios
        .get("/api/getWarehouseLocations/" + vm.$props.IDWarehouse)
        .then((res) => {
          if (res.status == 200) {
            vm.warehouseLocations = res.data;
          }
        })
        .catch((error) => console.log(error));
    },
    clear() {
      this.step = this.$props.startStep;
      this.loading = false;
      this.imputCod = "";
      this.test = "";
      this.product = null;
      //   this.fromLocation = {};
      this.message = "";
      this.toLocation = {};
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
    changeCounter: function (item, num) {
      item.qty = parseInt(item.qty) + parseInt(+num);
      if (item.qty < 0) item.qty = 0;
      if (item.qty > item.ilosc) {
        this.message = "Za dużo!!!";
        this.snackbar = true;
        item.qty = item.ilosc;
      }
      //this.setFocus();
    },
    steps() {
      const vm = this;
      this.message = "";

      // this.imputCod = this.imputCod.replaceAll(/Shift(.)/g, (_, p1) => p1.toUpperCase());
      this.imputCod = this.imputCod.replace("Unidentified", "");
      // set location from
      if (this.step == 0) {
        let from_loc = this.warehouseLocations.find((f) => {
          return f.LocationCode == this.imputCod;
        });
        console.log(
          "from_loc",
          from_loc,
          this.warehouseLocations,
          this.imputCod
        );
        if (from_loc) {
          this.fromLocation = from_loc;
          this.step = 1;
        } else {
          this.message = "Błąd lokalizacji (";
        }
      }
      if (this.step == 1) {
        if (this.product == null) {
          let item = this.$props.products.find((f) => {
            return f.KodKreskowy == this.imputCod;
          });

          if (item) {
            this.product = item;
            this.product.qty = 1;
            // in whitch location is this product
            this.getProductLocations();
            return;
          } else {
            this.message = "Brak produktu!!!";
            this.snackbar = true;
            return;
          }
        }
        if (this.product.KodKreskowy == this.imputCod) {
          this.changeCounter(this.product, 1);
        } else {
          if (/[a-zA-Z]+/.test(this.imputCod)) {
            this.message = "Błąd lokalizacji (";
          } else {
            this.message = "Błąd kodu kreskowego!!!";
          }
        }
        let to_loc = this.warehouseLocations.find((f) => {
          return f.LocationCode == this.imputCod;
        });
        if (to_loc && this.product.qty > 0) {
          this.step = 3;
          this.toLocation = to_loc;
          return;
        }
      }
      //   if (this.step == 3) {
      //     let to_loc = this.warehouseLocations.find((f) => {
      //       return f.LocationCode == this.imputCod;
      //     });
      //     if (to_loc && this.product.qty > 0) {
      //       this.toLocation = to_loc;
      //       return;
      //     }
      //   }
    },
    doRelokacja() {
      const vm = this;
      let data = {};
      vm.loading = true;
      vm.message = "";
      data.IDTowaru = vm.product.IDTowaru;
      data.qty = vm.product.qty;
      data.fromLocation = vm.fromLocation;
      data.toLocation = vm.toLocation;
      data.selectedWarehause = vm.$props.IDWarehouse;
      data.createdDoc = vm.createdDoc;
      //   axios
      //     .post("/api/doRelokacja", data)
      //     .then((res) => {
      //       if (res.status == 200) {
      //         vm.createdDoc = res.data.createdDoc;
      //         vm.loading = false;
      //         vm.message = `Dokumenty przeniesienia zostały utworzone. ${vm.createdDoc.idmin} ${vm.createdDoc.idpls}`;
      //         vm.snackbar = true;
      //       }
      //     })
      //     .catch((error) => console.log(error));
      vm.loading = false;
      vm.clear();
    },
  },
};
</script>

