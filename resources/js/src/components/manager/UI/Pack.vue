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
              <v-col>
                <v-btn
                  v-if="
                    productsOrder[0]?.products &&
                    productsOrder[0].products.some(
                      (product) =>
                        product.qty > 0 && product.qty <= product.ilosc
                    )
                  "
                  class="btn"
                  @click="
                    dialogWeight = true;
                    workTTN;
                  "
                  >Utwórz TTN</v-btn
                >
                <v-btn
                  class="btn"
                  @click="ConfirmAnuluy()"
                  v-if="
                    productsOrder[0]?.products &&
                    productsOrder[0].products.some((product) => product.qty > 0)
                  "
                  >Anulowanie pakowania</v-btn
                >
                <v-btn
                  class="btn"
                  @click="print"
                  v-if="productsOrder[0]?.products.length == 0"
                  >Print faktura</v-btn
                >
              </v-col>
              <v-spacer></v-spacer>
              <GetQrCode @result="handleResult" />
            </v-row>
            <h5 class="text-red" v-if="message_error">{{ message_error }}</h5>
            <h5 class="text-green" v-if="message">{{ message }}</h5>

            <PackProductList
              :products="productsOrder[0]?.products || []"
              :ttn="productsOrder.ttn"
              :showBtns="true"
              @change-counter="changeCounter"
              @delete-ttn="ConfirmdeleteTTN"
              @print-ttn="printTTN"
            />
            <v-dialog v-model="dialogWeight" max-width="1200">
              <v-card>
                <v-card-title>
                  Wprowadź wagę i wymiary paczki
                  <v-spacer />
                  <v-btn
                    icon
                    class="close-btn"
                    @click="dialogWeight = false"
                    style="position: absolute; top: 8px; right: 8px"
                  >
                    <v-icon>mdi-close</v-icon>
                  </v-btn>
                </v-card-title>
                <v-card-text class="overflow-y-auto">
                  <DynamicForm
                    :fields="fields"
                    :packageFields="packageFields"
                    v-model="formValues"
                    @save="onSave"
                  />
                </v-card-text>
                <v-card-actions>
                  <v-spacer></v-spacer>
                  <!-- <v-btn color="primary" @click="getTTN">get TTN</v-btn> -->
                  <!-- <v-btn text @click="dialogWeight = false">Anulowanie</v-btn> -->
                </v-card-actions>
              </v-card>
            </v-dialog>
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
    <ConfirmDlg ref="confirm" />
  </div>
</template>

<script>
import axios from "axios";
import _ from "lodash";

import GetQrCode from "../../UI/GetQrCode.vue";
import PackProductList from "./PackProductList.vue";
import ConfirmDlg from "../../UI/ConfirmDlg.vue";
import DynamicForm from "../../UI/DynamicForm.vue";
export default {
  name: "PackOrders",
  components: {
    ConfirmDlg,
    GetQrCode,
    PackProductList,
    DynamicForm,
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
      // Fields for TTN
      fields: [],
      packageFields: [],
      formValues: {},

      package_id: "",
      package_number: "",
      courier_inner_number: "",

      filepath: "",
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
    showTransCompanyList: function (newValue) {
      if (newValue == true) {
        this.getPackOrders();
        this.clearDialogOrders();
      }
    },
  },
  methods: {
    clearDialogOrders() {
      this.imputCod = "";
      this.test = "";
      this.message = "";
      this.message_error = "";
      this.selectTransCompany = null;
      this.transOrders = [];
      this.productsOrder = [];
    },
    getTTN() {
      //   if (this.$refs.weightForm.validate()) {
      //     if (!this.weight || !this.length || !this.width || !this.height) {
      //       this.message = "Wszystkie pola muszą być wypełnione!";
      //       this.snackbar = true;
      //       return;
      //     }

      let data = {
        IDOrder: this.transOrders[this.indexTransOrders].IDOrder,
        IDWarehouse: this.transOrders[this.indexTransOrders].IDWarehouse,
        Nr_Baselinker: this.transOrders[this.indexTransOrders].Nr_Baselinker,
        forttn: {
          order_id: this.transOrders[this.indexTransOrders].Nr_Baselinker,
          courier_code: "",
          account_id: 0,
        },
      };
      data.forttn["fields"] = Object.entries(this.formValues.fields).map(
        ([key, value]) => {
          return { id: key, value: value };
        }
      );
      if (this.formValues.packageFields) {
        let new_packegeValue = {};
        Object.entries(this.formValues.packageFields).forEach(([k, v]) => {
          if (k === "size_width") {
            new_packegeValue["width"] = v;
          } else if (k === "size_length") {
            new_packegeValue["length"] = v;
          } else if (k === "size_height") {
            new_packegeValue["height"] = v;
          } else {
            new_packegeValue[k] = v;
          }
        });

        data.forttn["packages"] = [new_packegeValue];
      }
      axios
        .post("/api/getTTN", data)
        .then((response) => {
          this.package_id = response.data.package_id;
          this.courier_inner_number = response.data.courier_inner_number || "";
          this.package_number = response.data.package_number;

          this.filepath = response.data.filePath || "";
          this.workTTN();
        })
        .catch((error) => {
          if (error.request.status == 404) {
            this.dialogWeight = false;
            // this.message = error.request.response;
            this.message_error = error.request.response;
            this.snackbar = true;
          }
          console.log(error);
        });
      //}
    },
    print() {
      axios
        .post("/api/print", {
          doc: "invoice",
          order: this.transOrders[this.indexTransOrders],
        })
        .then((response) => {
          // handle success if needed
        })
        .catch((error) => {
          if (
            error.response &&
            error.response.status === 400 &&
            error.response.data &&
            error.response.data.message === "Printer not ready"
          ) {
            this.message_error = error.response.data.message;
            this.snackbar = true;
          } else {
            // handle other errors
            this.message_error = error.message || "Printing error";
            this.snackbar = true;
          }
        });
    },

    printTTN(ttnNumber) {
      axios
        .post("/api/print", {
          doc: "ttn",
          path: this.filepath,
        })
        .then((response) => {
          // handle success if needed
        })
        .catch((error) => {
          if (
            error.response &&
            error.response.status === 400 &&
            error.response.data &&
            error.response.data.message === "Printer not ready"
          ) {
            this.message_error = error.response.data.message;
            this.snackbar = true;
          } else {
            // handle other errors
            this.message_error = error.message || "Printing error";
            this.snackbar = true;
          }
        });
    },

    async ConfirmdeleteTTN(ttnNumber) {
      if (
        await this.$refs.confirm.open(
          "Numer TTN zostanie usunięty!",
          "Czy na pewno chcesz usunąć ten TTN?"
        )
      ) {
        await this.deleteTTN(ttnNumber);
      }
    },
    deleteTTN(ttnNumber) {
      axios
        .post("/api/deleteTTN", {
          IDOrder: this.transOrders[this.indexTransOrders].IDOrder,
          nttn: ttnNumber,
        })
        .then((response) => {
          this.getOrderPackProducts(
            this.transOrders[this.indexTransOrders].IDOrder
          );
          this.snackbar = true;
          this.message = response.data.message;
        })
        .catch((error) => {
          if (error.request.status == 404) {
            this.message = error.request.response;
            this.snackbar = true;
          }
          console.log(error);
        });
    },
    workTTN() {
      //if (this.$refs.weightForm.validate()) {
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
      //}
    },
    writeTTN() {
      let o_ttn = {
        [this.package_number]: {
          package_id: this.package_id,
          courier_inner_number: this.courier_inner_number,
          fields: this.formValues.fields || {},
          packages: this.formValues.packageFields || {},
          products: [],
          lastUpdate: "",
        },
      };

      // Iterate backwards to safely remove items while looping
      for (let i = this.productsOrder[0].products.length - 1; i >= 0; i--) {
        const product = this.productsOrder[0].products[i];
        if (product.qty > 0) {
          o_ttn[this.package_number].products.push({
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
          Order: this.transOrders[this.indexTransOrders],
          nttn: this.package_number,
          o_ttn: o_ttn,
        })
        .then((response) => {
          this.dialogWeight = false;
          this.snackbar = true;
          this.message = response.data.message;
        })
        .catch((error) => {
          if (error.request.status == 404) {
            this.message = error.request.response;
            this.message_error = error.request.response;
            this.snackbar = true;
            this.dialogWeight = false;
            console.log(error.request.response);
          }
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
        this.message = "Zamówienie zapakowany";
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
          o_pack["0"].products.push({
            [product.KodKreskowy]: product.qty,
          });
        }
        if (product.qty < product.ilosc || product.qty > product.ilosc) {
          allDone = false;
        }
      });

      this.setOrderPackProducts(o_pack, allDone);

      if (allDone) {
        this.message = "Zamówienie zapakowany i gotowe do wysyłki";
        this.snackbar = true;
        this.print();
        this.$nextTick(() => {
          this.dialogWeight = true;
        });
      }
    },
    setOrderPackProducts(o_pack, allDone = false) {
      axios
        .post("/api/setOrderPackProducts", {
          Order: this.transOrders[this.indexTransOrders],
          o_pack: o_pack,
          allDone: allDone,
        })
        .then((response) => {})
        .catch((error) => {
          this.message = error.request.response;
          this.message_error = error.request.response;
          this.snackbar = true;
          console.log(error);
        });
    },
    async ConfirmAnuluy() {
      if (
        await this.$refs.confirm.open(
          "Nie resetuj liczby zapakowanych towarów do zera!",
          "Czy na pewno chcesz anulować pakowanie?"
        )
      ) {
        await this.anuluy();
      }
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
      let find = false;
      this.productsOrder[0].products.forEach((product) => {
        if (product.KodKreskowy == this.imputCod) {
          find = true;
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
        }
      });
      // this.snackbar = true;
      if (!find) {
        if (typeof window !== "undefined" && window.AudioContext) {
          const ctx = new (window.AudioContext || window.webkitAudioContext)();
          const gain = ctx.createGain();
          gain.gain.setValueAtTime(0.5, ctx.currentTime);
          gain.connect(ctx.destination);

          // Repeat beep: 3 short beeps
          let beepCount = 0;

          function beep() {
            const oscillator = ctx.createOscillator();
            oscillator.type = "square";
            oscillator.frequency.setValueAtTime(220, ctx.currentTime);
            oscillator.connect(gain);
            oscillator.start();
            setTimeout(() => {
              oscillator.stop();
              oscillator.disconnect();
              beepCount++;
              if (beepCount < 3) {
                setTimeout(beep, 100); // пауза между бипами
              } else {
                setTimeout(() => ctx.close(), 200);
              }
            }, 150); // длительность бипа
          }
          beep();

          this.message_error = "Coś jest nie tak!!!";
        }
      }

      this.imputCod = "";
      this.test = "";
      this.isOrderDone();
    },
    getOrderPackProducts(id) {
      this.message = "";
      this.message_error = "";
      this.test = "";
      this.loading = true;
      axios
        .post("/api/getOrderPackProducts/" + id)
        .then((response) => {
          this.productsOrder = response.data;
          this.getForm(id);
          this.loading = false;
        })
        .catch((error) => {
          this.message = error.request.response;
          this.message_error = error.request.response;
          this.snackbar = true;
          console.log(error);
          this.loading = false;
        });
    },
    getForm(id) {
      axios
        .get("/api/getForm/" + id)
        .then((response) => {
          const forFormData =
            typeof response.data.fields === "string"
              ? JSON.parse(response.data.fields)
              : response.data.fields;
          this.fields = Array.isArray(forFormData)
            ? forFormData
            : forFormData.fields || [];

          this.packageFields = forFormData.package_fields || [];

          this.formValues = response.data.default_values || {};
        })
        .catch((error) => {
          console.error("Error fetching form data:", error);
          this.message_error = "Błąd pobierania formularza";
          this.snackbar = true;
        });
    },
    onSave() {
      // Handle save action
      this.getTTN();
      console.log("Form saved with values:", this.formValues);
      this.message = "Formularz zapisany";
      this.snackbar = true;
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
      this.orders = [];
      this.transOrders = [];

      let url = "/api/getPackOrders/";
      axios
        .get(url)
        .then((response) => {
          vm.orders = response.data.orders;
          vm.transCompany = _.uniqBy(
            vm.orders.map((order) => ({
              key: order.IDTransport,
              title: order.transport_name,
            })),
            "title"
          );
          vm.loading = false;
        })
        .catch((error) => {
          this.message = error.request.response;
          this.message_error = error.request.response;
          this.snackbar = true;
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
