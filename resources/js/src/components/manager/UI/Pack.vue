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
                        ?.title || "Выбрать компанию"
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
                {{
                  transOrders.length > 0 &&
                  transOrders[indexTransOrders] &&
                  transOrders[indexTransOrders] != ""
                    ? transOrders[indexTransOrders].OrderNumber
                    : ""
                }}
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
              <v-spacer></v-spacer>
              <GetQrCode @result="handleResult" />
            </v-row>
            <h5 class="text-red" v-if="message_error">{{ message_error }}</h5>
            <h5 class="text-green" v-if="message">{{ message }}</h5>

            <v-row
              class="product_line border my-0"
              v-for="(product, index) in productsOrder"
              :key="product.IDTowaru"
            >
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
          <v-btn color="primary" @click="submitWeight">Zapisz</v-btn>
          <v-btn text @click="dialogWeight = false">Anuluj</v-btn>
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
      selectedOrderProducts: [],
      indexTransOrders: 0,
      transCompany: [],
      selectTransCompany: null,
      productsOrder: [],
      showTransCompanyList: false,
    };
  },

  mounted() {},
  watch: {
    indexTransOrders: function (newValue, oldValue) {
      if (this.transOrders.length > 0) {
        this.imputCod = "";
        this.test = "";
        this.getOrderProducts(this.transOrders[newValue].IDOrder);
      }
    },
  },
  methods: {
    submitWeight() {
      if (this.$refs.weightForm.validate()) {
        // Perform the save operation here
        console.log("Weight and dimensions saved:", {
          weight: this.weight,
          length: this.length,
          width: this.width,
          height: this.height,
        });
        this.dialogWeight = false;
      }
    },
    isOrderDone() {
      let allDone = true;
      this.productsOrder.forEach((product) => {
        if (product.qty < product.ilosc) {
          allDone = false;
        }
      });
      if (allDone) {
        this.message = "Zamówienie zrealizowane";
        this.snackbar = true;
        this.$nextTick(() => {
          this.dialogWeight = true;
        });
      }
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
    getOrderProducts(id) {
      this.message = "";
      this.message_error = "";
      this.test = "";
      this.productsOrder = this.orders
        .filter((order) => order.IDOrder == id)
        .map((order) => ({
          IDTowaru: order.IDTowaru,
          Nazwa: order.Nazwa,
          ilosc: order.ilosc,
          qty: 0,
          img: order.img,
          KodKreskowy: order.KodKreskowy,
          sku: order.sku,
        }));
    },
    getTransOrders() {
      this.indexTransOrders = 0;
      this.imputCod = "";
      this.selectedOrderProducts = [];
      this.productsOrder = [];
      this.message = "";
      this.message_error = "";
      this.transOrders = this.orders
        .filter((order) => order.IDTransport == this.selectTransCompany)
        // Map to objects with only IDOrder and OrderNumber
        .map((order) => ({
          IDOrder: order.IDOrder,
          OrderNumber: order.OrderNumber,
        }))
        // Remove duplicates by IDOrder
        .filter(
          (order, index, self) =>
            index === self.findIndex((o) => o.IDOrder === order.IDOrder)
        );
      this.getOrderProducts(this.transOrders[this.indexTransOrders].IDOrder);
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
      if (item.qty > item.ilosc) {
        this.message = "Za dużo!!!";
        this.message_error = "Za dużo!!!";
        this.snackbar = true;
        item.qty = item.ilosc;
      }
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
                title: order.TransportCompanyName,
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
</style>
