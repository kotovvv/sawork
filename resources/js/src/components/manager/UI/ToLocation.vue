'<template>
  <div>
    <v-btn @click="dialogLocation = true">Сменить локацию</v-btn>
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
            <v-row>
              <GetQrCode @result="handleResult" />
            </v-row>
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
      type: Number,
      required: true,
    },
    location: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      dialogLocation: false,
      step: 0,
      selected_item: {},
      product: {},
      toLocation: {},
      toLocations: [],
      loading: false,
      message: "",
      test: "",
      imputCod: "",
      warehousesLoc: [],
      selectedWarehause: null,
    };
  },

  mounted() {},

  methods: {
    handleResult(result) {
      console.log(result);
      this.message = result;
      // Handle the result data here
    },
    getWarehouseLocations() {
      const vm = this;
      axios
        .get("/api/getWarehouseLocations/" + vm.$props.IDWarehouse)
        .then((res) => {
          if (res.status == 200) {
            vm.warehousesLoc = res.data;
          }
        })
        .catch((error) => console.log(error));
    },
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
  },
};
</script>

