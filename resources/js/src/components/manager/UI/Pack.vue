<template>
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
    <v-btn
      @click="
        dialogPack = true;
        getPackOrders();
      "
      icon="mdi-package-down"
    ></v-btn>
    <v-dialog
      id="dialogPack"
      v-model="dialogPack"
      transition="dialog-bottom-transition"
      fullscreen
      @keyup="handleKeypress"
    >
      <v-container>
        <v-card height="80vh" style="overflow-y: auto">
          <v-card-title class="mb-5 bg-grey-lighten-3">
            <v-row>
              <v-col cols="10" md="auto">
                <span
                  class="transport-company-selector d-flex flex-wrap align-center"
                  style="cursor: pointer; user-select: none"
                >
                  <span
                    v-if="!showTransCompanyList && selectTransCompany"
                    class="company-chip selected"
                    @click="showTransCompanyList = true"
                    style="
                      margin: 4px 8px 4px 0;
                      padding: 6px 0;
                      border-radius: 16px;
                      background: #eee;
                      display: inline-block;
                      min-width: 100px;
                      text-align: center;
                      word-break: break-word;
                    "
                  >
                    <v-icon
                      icon="mdi-menu-down"
                      size="small"
                      style="margin-left: 4px"
                    />
                    {{
                      transCompany.find((c) => c.key === selectTransCompany)
                        ?.title || "Wybierz firmę"
                    }}
                  </span>
                  <span v-else style="display: flex; flex-wrap: wrap">
                    <span
                      v-for="company in transCompany"
                      :key="company.key"
                      :class="[
                        'company-chip',
                        { selected: selectTransCompany === company.key },
                      ]"
                      @click="
                        selectTransCompany = company.key;
                        getTransOrders();
                        showTransCompanyList = false;
                      "
                      style="
                        margin: 4px 8px 4px 0;
                        padding: 6px 12px;
                        border-radius: 16px;
                        background: #eee;
                        display: inline-block;
                        min-width: 100px;
                        text-align: center;
                        word-break: break-word;
                      "
                    >
                      {{ company.title }}
                    </span>
                  </span>
                </span>
              </v-col>
              <v-col
                v-if="selectTransCompany"
                class="d-flex align-center gap-2"
                style="width: content-fit"
              >
                <span>
                  {{
                    transOrders.length > 0 &&
                    transOrders[indexTransOrders] &&
                    transOrders[indexTransOrders] != ""
                      ? transOrders[indexTransOrders].OrderNumber
                      : ""
                  }}
                  <small>{{
                    transOrders.length > 0 &&
                    transOrders[indexTransOrders] &&
                    transOrders[indexTransOrders] != ""
                      ? transOrders[indexTransOrders].Nr_Baselinker
                      : ""
                  }}</small>
                </span>
                <v-btn
                  icon="mdi-arrow-left"
                  @click="
                    if (indexTransOrders > 0) indexTransOrders--;
                    else indexTransOrders = transOrders.length - 1;
                  "
                ></v-btn>
                {{ indexTransOrders + 1 }} /
                {{ transOrders.length > 0 ? transOrders.length : 0 }}
                <v-btn
                  icon="mdi-arrow-right"
                  @click="
                    if (indexTransOrders < transOrders.length - 1)
                      indexTransOrders++;
                    else indexTransOrders = 0;
                  "
                ></v-btn>
              </v-col>
              <v-spacer></v-spacer>

              <div
                class="btn close-btn"
                @click="
                  dialogPack = false;
                  clear();
                  text = '';
                "
              >
                <v-icon icon="mdi-close"></v-icon></div
            ></v-row>
          </v-card-title>

          <v-card-text>
            <v-row>
              <v-col
                v-if="
                  productsOrder[0]?.products &&
                  productsOrder[0].products.some((product) => product.qty > 0)
                "
              >
                <v-btn
                  class="btn"
                  @click="
                    dialogWeight = true;
                    workTTN;
                    this.clearValueTtn();
                  "
                  >Utwórz TTN</v-btn
                >
                <v-btn class="btn" @click="anuluy">Anulowanie pakowania</v-btn>
                <v-btn class="btn" @click="print">Print faktura</v-btn>
              </v-col>
              <v-spacer></v-spacer>
              <GetQrCode @result="handleResult" />
            </v-row>
            <h5 class="text-red" v-if="message_error">{{ message_error }}</h5>
            <h5 class="text-green" v-if="message">{{ message }}</h5>

            <div
              class="product_line border my-3"
              v-for="(product, index) in productsOrder[0]?.products"
              :key="product.IDTowaru"
            >
              <v-row>
                <v-col>
                  <div class="d-flex">
                    {{ index + 1 }}.
                    <img
                      v-if="product.img"
                      :src="'data:image/jpeg;base64,' + product.img"
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
                      :class="{
                        'bg-red-darken-4': product.qty > product.ilosc,
                        'bg-green-lighten-4': product.qty == product.ilosc,
                      }"
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
            </div>
            <div v-if="productsOrder.ttn">
              <div
                v-for="(ttnData, ttnNumber) in productsOrder.ttn"
                :key="ttnNumber"
                class="mb-4 bg-grey-lighten-3"
              >
                <v-card
                  class="pa-3 mb-2 bg-grey-lighten-3"
                  outlined
                  max-height="60vh"
                >
                  <div class="gap-2 d-flex flex-wrap align-center">
                    <strong>TTN:</strong> {{ ttnNumber }},
                    <strong>Waga:</strong> {{ ttnData.weight }},
                    <strong>Długość:</strong> {{ ttnData.length }},
                    <strong>Szerokość:</strong> {{ ttnData.width }},
                    <strong>Wysokość:</strong> {{ ttnData.height }},
                    <strong>Date:</strong>
                    {{ ttnData.lastUpdate }}
                    <v-btn
                      icon="mdi-file-document-remove-outline"
                      @click="deleteTTN(ttnNumber)"
                    >
                    </v-btn>
                    <v-btn
                      icon="mdi-printer-pos-outline"
                      @click="printTTN(ttnNumber)"
                    >
                    </v-btn>
                  </div>
                  <v-row
                    class="product_line border my-0"
                    v-for="(product, index) in ttnData.products"
                    :key="product.IDTowaru"
                  >
                    <v-col cols="10">
                      <div class="d-flex">
                        {{ index + 1 }}.
                        <img
                          v-if="product.img"
                          :src="'data:image/jpeg;base64,' + product.img"
                          alt="pic"
                          style="height: 3em"
                        />
                        <span>
                          {{ product.Nazwa }}<br />cod:
                          {{ product.KodKreskowy }}, sku: {{ product.sku }}
                        </span>
                      </div>
                    </v-col>

                    <v-col cols="2">
                      <div class="d-flex justify-start">
                        <div :id="product.IDTowaru" class="text-center">
                          {{ product.qty }}
                        </div>
                      </div>
                    </v-col>
                  </v-row>
                </v-card>
              </div>
            </div>
          </v-card-text>

          <v-progress-linear
            :active="loading"
            indeterminate
            color="purple"
          ></v-progress-linear>
          <p class="text-grey px-4">{{ test }}</p>
        </v-card>
      </v-container>
    </v-dialog>
    <v-dialog v-model="dialogWeight" max-width="500">
      <v-card>
        <v-card-title> Wprowadź wagę i wymiary paczki </v-card-title>
        <v-card-text>
          <v-form ref="weightForm">
            <v-text-field v-model="TTN" label="TTN" required></v-text-field>
            <v-text-field
              v-model="weight"
              label="Waga (kg)"
              type="number"
              min="0"
              step="0.01"
              required
            ></v-text-field>
            <v-text-field
              v-model="length"
              label="Długość (cm)"
              type="number"
              min="0"
              required
            ></v-text-field>
            <v-text-field
              v-model="width"
              label="Szerokość (cm)"
              type="number"
              min="0"
              required
            ></v-text-field>
            <v-text-field
              v-model="height"
              label="Wysokość (cm)"
              type="number"
              min="0"
              required
            ></v-text-field>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="primary" @click="workTTN">Zapisz</v-btn>
          <v-btn text @click="dialogWeight = false">Anulowanie</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import axios from "axios";
import _ from "lodash";

import GetQrCode from "../../UI/GetQrCode.vue";
export default {
  name: "PackOrders",
  components: {
    GetQrCode,
  },

  data() {
    return {
      snackbar: false,
      dialogPack: false,
      dialogWeight: false,
      loading: false,
      message: "",
      message_error: "",
      test: "",
      imputCod: "",
      orders: [],
      transOrders: [""],

      indexTransOrders: 0,
      transCompany: [],
      selectTransCompany: null,
      productsOrder: [],
      showTransCompanyList: false,
      weight: "",
      length: "",
      width: "",
      height: "",
      TTN: "",
    };
  },

  mounted() {},
  watch: {
    indexTransOrders: function (newValue, oldValue) {
      if (this.transOrders.length > 0) {
        this.imputCod = "";
        this.test = "";
        this.getOrderPackProducts(this.transOrders[newValue].IDOrder);
      }
    },
  },
  methods: {
    print() {
      axios.post("/api/print", {
        doc: "invoice",
        order: this.transOrders[this.indexTransOrders],
      });
    },
    printTTN(ttnNumber) {
      let forTN = {};
      forTN.weght = this.productsOrder[0].ttn[ttnNumber].weight;
      forTN.length = this.productsOrder[0].ttn[ttnNumber].length;
      forTN.width = this.productsOrder[0].ttn[ttnNumber].width;
      forTN.height = this.productsOrder[0].ttn[ttnNumber].height;
      axios.post("/api/print", {
        doc: "ttn",
        order: this.transOrders[this.indexTransOrders],
        forTN: forTN,
        ttn: ttnNumber,
      });
    },
    clearValueTtn() {
      this.weight = "";
      this.length = "";
      this.width = "";
      this.height = "";
      this.TTN = "";
    },

    deleteTTN(ttnNumber) {
      axios
        .post("/api/deleteTTN", {
          IDOrder: this.transOrders[this.indexTransOrders].IDOrder,
          nttn: ttnNumber,
        })
        .then((response) => {
          if (response.data.status == "error") {
            this.message_error = response.data.message;
            this.snackbar = true;
          } else {
            this.getOrderPackProducts(
              this.transOrders[this.indexTransOrders].IDOrder
            );
            this.snackbar = true;
            this.message = response.data.message;
          }
        })
        .catch((error) => {
          console.log(error);
        });
    },
    workTTN() {
      if (this.$refs.weightForm.validate()) {
        // Perform the save operation here
        console.log("Weight and dimensions saved:", {
          weight: this.weight,
          length: this.length,
          width: this.width,
          height: this.height,
        });
        this.writeTTN();
        //this.dialogWeight = false;
        const currentOrder = this.transOrders[this.indexTransOrders];
        if (currentOrder) {
          // If there are still orders for this transport company
          if (this.transOrders.length > 0) {
            // Stay on the same index (or move to previous if at end)
            if (this.indexTransOrders >= this.transOrders.length) {
              this.indexTransOrders = 0;
            }
            this.getOrderPackProducts(
              this.transOrders[this.indexTransOrders].IDOrder
            );
          } else {
            // No more orders for this company, move to next company
            const currentCompanyIndex = this.transCompany.findIndex(
              (c) => c.key === this.selectTransCompany
            );
            let foundNext = false;
            for (
              let i = currentCompanyIndex + 1;
              i < this.transCompany.length;
              i++
            ) {
              const nextCompany = this.transCompany[i];
              const companyOrders = this.orders.filter(
                (order) => order.IDTransport === nextCompany.key
              );
              if (companyOrders.length > 0) {
                this.selectTransCompany = nextCompany.key;
                this.getTransOrders();
                foundNext = true;
                break;
              }
            }
            if (!foundNext) {
              // Try from the beginning if not found after current
              for (let i = 0; i < currentCompanyIndex; i++) {
                const nextCompany = this.transCompany[i];
                const companyOrders = this.orders.filter(
                  (order) => order.IDTransport === nextCompany.key
                );
                if (companyOrders.length > 0) {
                  this.selectTransCompany = nextCompany.key;
                  this.getTransOrders();
                  foundNext = true;
                  break;
                }
              }
            }
            if (!foundNext) {
              // No more companies with orders
              this.dialogPack = false;
              this.snackbar = true;
              this.message = "Wszystkie zamówienia zostały zrealizowane!";
            }
          }
        }
      }
    },
    writeTTN() {
      let o_ttn = {
        [this.TTN]: {
          weight: this.weight,
          length: this.length,
          width: this.width,
          height: this.height,
          products: [],
          lastUpdate: "",
        },
      };

      // Iterate backwards to safely remove items while looping
      for (let i = this.productsOrder[0].products.length - 1; i >= 0; i--) {
        const product = this.productsOrder[0].products[i];
        if (product.qty > 0) {
          o_ttn[this.TTN].products.push({
            [product.KodKreskowy]: product.qty,
          });
          // Update product.ilosc to reflect the remaining quantity after packing
          if (product.ilosc - product.qty > 0) {
            product.ilosc = product.ilosc - product.qty;
          } else {
            // Remove product from productsOrder[0].products
            this.productsOrder[0].products.splice(i, 1);
          }
        }
      }

      axios
        .post("/api/writeTTN", {
          IDOrder: this.transOrders[this.indexTransOrders].IDOrder,
          nttn: this.TTN,
          o_ttn: o_ttn,
        })
        .then((response) => {
          if (response.data.status == "error") {
            this.message_error = response.data.message;
            this.snackbar = true;
          } else {
            this.print();
            this.anuluy();
            this.dialogWeight = false;
            this.snackbar = true;
            this.message = response.data.message;
          }
        })
        .catch((error) => {
          console.log(error);
        });
    },
    // Check if all products are done
    // If all products are done, show message and open dialog
    // If not, show message and close dialog
    // If all products are done, set order pack products
    // If not, set order pack products to null
    canDoTTN() {
      let allDone = true;

      this.productsOrder[0]?.products.forEach((product) => {
        if (product.qty > 0) {
          allDone = false;
        }
      });

      if (allDone) {
        this.message = "Zamówienie zrealizowane";
        this.snackbar = true;
        this.$nextTick(() => {
          this.dialogWeight = true;
        });
      } else {
        this.message_error = "Nie wszystkie produkty zostały zeskanowane";
        this.snackbar = true;
      }
    },
    isOrderDone() {
      let allDone = true;

      let o_pack = { 0: { products: [], lastUpdate: "" } };

      this.productsOrder[0]?.products.forEach((product) => {
        //when qty > 0 add to o_pack.products ('KodKreskowy', 'qty')
        if (product.qty > 0) {
          o_pack["0"].products.push({ [product.KodKreskowy]: product.qty });
        }
        if (product.qty < product.ilosc) {
          allDone = false;
        }
      });

      this.setOrderPackProducts(o_pack);

      if (allDone) {
        this.message = "Zamówienie zrealizowane";
        this.snackbar = true;
        this.$nextTick(() => {
          this.dialogWeight = true;
        });
      }
    },
    setOrderPackProducts(o_pack) {
      axios
        .post("/api/setOrderPackProducts", {
          IDOrder: this.transOrders[this.indexTransOrders].IDOrder,
          o_pack: o_pack,
        })
        .then((response) => {
          if (response.data.status == "error") {
            this.message_error = response.data.message;
            this.snackbar = true;
          }
        })
        .catch((error) => {
          console.log(error);
        });
    },
    anuluy() {
      this.productsOrder[0].products.forEach((product) => {
        product.qty = 0;
      });
      this.message = "";
      this.setOrderPackProducts(null);
    },
    scanKod() {
      this.message = "";
      this.message_error = "";
      this.test = "";
      this.productsOrder.forEach((product) => {
        if (
          product.KodKreskowy == this.imputCod &&
          product.qty + 1 <= product.ilosc
        ) {
          product.qty = parseInt(product.qty) + 1;
          if (typeof window !== "undefined" && window.AudioContext) {
            const ctx = new (window.AudioContext ||
              window.webkitAudioContext)();
            const oscillator = ctx.createOscillator();
            oscillator.type = "sine";
            oscillator.frequency.setValueAtTime(880, ctx.currentTime); // 880 Hz
            oscillator.connect(ctx.destination);
            oscillator.start();
            setTimeout(() => {
              oscillator.stop();
              ctx.close();
            }, 150); // 150 ms beep
          }
          //   this.message = "Zeskanowano kod: " + this.imputCod;
          //   this.snackbar = true;
          this.imputCod = "";
          this.test = "";
        } else {
          if (typeof window !== "undefined" && window.AudioContext) {
            const ctx = new (window.AudioContext ||
              window.webkitAudioContext)();
            const oscillator = ctx.createOscillator();
            const gain = ctx.createGain();
            oscillator.type = "square";
            oscillator.frequency.setValueAtTime(220, ctx.currentTime); // 220 Hz for error
            gain.gain.setValueAtTime(0.5, ctx.currentTime);
            oscillator.connect(gain).connect(ctx.destination);

            // Repeat beep: 3 short beeps
            let beepCount = 0;
            function beep() {
              oscillator.start(ctx.currentTime + beepCount * 0.25);
              oscillator.stop(ctx.currentTime + beepCount * 0.25 + 0.15);
              beepCount++;
              if (beepCount < 3) {
                setTimeout(beep, 250);
              } else {
                setTimeout(() => ctx.close(), 800);
              }
            }
            beep();
          }
          this.message_error = "Coś jest nie tak!!!";
          // this.snackbar = true;
        }
      });
      this.isOrderDone();
    },
    getOrderPackProducts(id) {
      this.message = "";
      this.message_error = "";
      this.test = "";
      this.loading = true;
      axios
        .get("/api/getOrderPackProducts/" + id)
        .then((response) => {
          if (response.data.status == "error") {
            this.message_error = response.data.message;
            this.snackbar = true;
          } else {
            this.productsOrder = response.data;
          }
          this.loading = false;
        })
        .catch((error) => {
          console.log(error);
          this.loading = false;
        });
    },
    getTransOrders() {
      this.indexTransOrders = 0;
      this.imputCod = "";

      this.productsOrder = [];
      this.message = "";
      this.message_error = "";
      this.transOrders = this.orders
        .filter((order) => order.IDTransport == this.selectTransCompany)
        // Map to objects with only IDOrder and OrderNumber
        .map((order) => ({
          IDOrder: order.IDOrder,
          OrderNumber: order.OrderNumber,
          Nr_Baselinker: order.Nr_Baselinker,
          invoice_number: order.invoice_number,
          IDWarehouse: order.IDWarehouse,
        }))
        // Remove duplicates by IDOrder
        .filter(
          (order, index, self) =>
            index === self.findIndex((o) => o.IDOrder === order.IDOrder)
        );
      this.getOrderPackProducts(
        this.transOrders[this.indexTransOrders].IDOrder
      );
      this.$nextTick(() => {
        const dialog = document.getElementById("dialogPack");
        if (dialog) {
          dialog.focus();
        }
      });
    },
    handleResult(result) {
      console.log(result);
      this.message = result.data;
      this.imputCod = result.data;
      // Handle the result data here
      this.scanKod();
    },

    clear() {
      this.loading = false;
      this.imputCod = "";
      this.test = "";
      this.product = null;
      this.message = "";
      this.message_error = "";
    },
    handleKeypress(event) {
      if (event.key === "Shift") {
        return;
      }
      if (event.key === "Enter") {
        console.log(this.imputCod);
        this.scanKod();
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

      this.isOrderDone();
      //this.setFocus();
    },
    getPackOrders: function () {
      const vm = this;
      this.loading = true;
      this.message = "";
      this.message_error = "";
      this.test = "";

      let url = "/api/getPackOrders/";
      axios
        .get(url)
        .then((response) => {
          if (response.data.status == "error") {
            vm.message_error = response.data.message;
            vm.snackbar = true;
          } else {
            vm.orders = response.data.orders;
            vm.transCompany = _.uniqBy(
              vm.orders.map((order) => ({
                key: order.IDTransport,
                title: order.transport_name,
              })),
              "title"
            );
          }
          vm.loading = false;
        })
        .catch((error) => {
          console.log(error);
          this.loading = false;
        });
    },
  },
};
</script>

<style>
.close-btn {
  position: absolute;
  right: 0;
  top: 0;
  z-index: 1;
}
.active .qty {
  background: #e0e0e0;
}
.error .qty {
  background: #ffcdd2;
}
</style>
