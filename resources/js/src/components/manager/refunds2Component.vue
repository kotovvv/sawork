<template>
  <v-container>
    <div
      fluid
      v-if="selectedItem"
      :id="selectedItem.IDRuchuMagazynowego"
      style="min-height: 100vh"
      :key="selectedItem.IDRuchuMagazynowego"
    >
      <v-row>
        <v-col>
          <h3>
            {{ selectedItem.NrDokumentu }}
            <small>{{ selectedItem.Data.substring(0, 10) }}</small>
          </h3>
          <div v-if="selectedItem.Uwagi">
            Uwagi: {{ selectedItem.Uwagi }}
            <v-btn
              size="small"
              icon="mdi-pencil"
              @click="dialogEditUwagiDoc = !dialogEditUwagiDoc"
            ></v-btn>
          </div>
          <v-dialog v-model="dialogEditUwagiDoc" width="500">
            <v-card>
              <v-card-title>Dokument</v-card-title>
              <v-card-text>
                <v-textarea
                  v-model="selectedItem.Uwagi"
                  label="Uwagi"
                ></v-textarea>
              </v-card-text>
              <v-card-actions>
                <v-btn text @click="dialogEditUwagiDoc = false">Cancel</v-btn>
                <v-btn text @click="saveUwagiDoc">Save</v-btn>
              </v-card-actions>
            </v-card>
          </v-dialog>
        </v-col>
        <v-spacer></v-spacer>
        <v-btn icon="mdi-close" @click="selectedItem = null"></v-btn>
      </v-row>
      <v-row
        ><v-col>
          <v-card>
            <v-tabs
              v-model="tab"
              bg-color="primary"
              @update:modelValue="tabChanged"
            >
              <v-tab value="products"> Products </v-tab>
              <v-tab value="photo"> Photo </v-tab>
            </v-tabs>
            <v-tabs-window
              v-model="tab"
              style="height: 80vh; overflow-y: auto"
              class="mt-3"
            >
              <v-tabs-window-item value="products">
                <v-row>
                  <v-col>
                    <v-data-table
                      :items="wzk_products"
                      :headers="headers_products"
                      @click:row="handleClick"
                      :row-props="colorRowItem"
                      select-strategy="single"
                    >
                      <template v-slot:top="{}">
                        <v-row class="align-center">
                          <productHistory
                            :product_id="selectedProduct.IDTowaru"
                          />
                          <v-btn
                            size="x-small"
                            :disabled="!selectedProduct.IDTowaru > 0"
                            @click="editProductUwagi"
                            icon="mdi-pencil"
                          ></v-btn>
                        </v-row>
                      </template>
                    </v-data-table>
                  </v-col>
                </v-row>
              </v-tabs-window-item>

              <v-tabs-window-item value="photo">
                <v-row v-if="$attrs.user.IDRoli != 4">
                  <v-col cols="12" md="3" lg="2">
                    <v-file-input
                      clearable
                      v-model="files"
                      label="Files input"
                      multiple
                      hide-details
                      append-inner-icon="mdi-content-save"
                      @click:appendInner="uploadFiles('zworot')"
                    ></v-file-input
                  ></v-col>

                  <v-col cols="4">
                    <v-btn @click="openModal" icon="mdi-camera"></v-btn>
                  </v-col>
                </v-row>
                <div v-if="results.length">
                  <h2>Results:</h2>
                  <div v-for="(result, index) in results" :key="index">
                    <div v-if="result.type === 'photo'">
                      <img
                        :src="result.data"
                        alt="Captured Photo"
                        style="width: 100%; max-width: 200px"
                      />
                      <v-btn icon="mdi-delete" @click="delPic(index)"></v-btn>
                    </div>
                  </div>
                  <v-btn @click="uploadSnapshots('zworot')"
                    >Save Snapshots</v-btn
                  >
                  <div v-if="message">{{ message }}</div>
                </div>
                <v-row v-if="photoFiles.length">
                  <v-col>
                    <v-list>
                      <v-list-item v-for="file in photoFiles" :key="file.name">
                        <v-list-item-action class="overflow-auto">
                          <v-btn @click="downloadFile(file.url, file.name)">
                            <img
                              :src="file.url"
                              :alt="file.name"
                              style="height: 38px; width: auto"
                              v-if="file.is_image == true"
                            />
                            <v-icon v-else>mdi-file</v-icon>

                            {{ file.name }}
                            <v-icon>mdi-download</v-icon>
                          </v-btn>
                          <v-btn
                            v-if="$attrs.user.IDRoli != 4"
                            icon="mdi-delete"
                            @click="deleteFile(file.url)"
                            class="ml-2"
                          ></v-btn>
                        </v-list-item-action>
                      </v-list-item>
                    </v-list>
                  </v-col>
                </v-row>
              </v-tabs-window-item>
            </v-tabs-window>
          </v-card> </v-col
      ></v-row>
    </div>
    <v-row>
      <v-col md="6" sm="12">
        <v-select
          label="Magazyn"
          v-model="IDWarehouse"
          :items="warehouses"
          item-title="Nazwa"
          item-value="IDMagazynu"
          @update:modelValue="
            clear();
            setLocations();
          "
          hide-details="auto"
        ></v-select>
      </v-col>
      <v-col md="6" sm="12"
        ><v-text-field
          label="Dokument"
          v-model="ordername"
          id="getorder"
          @keyup.enter="getOrder()"
          hide-details="auto"
        ></v-text-field
      ></v-col>
    </v-row>
    <v-container fluid>
      <v-row>
        <v-col cols="12">
          <v-progress-linear
            :active="loading"
            indeterminate
            color="purple"
          ></v-progress-linear>
        </v-col>
      </v-row>
    </v-container>
    <!-- Order -->
    <v-row>
      <v-col cols="12">
        <label for="getorder">
          <span v-if="order.Number"
            ><b>Order: </b
            >{{
              (order.Number ?? "") +
                " - " +
                order.pk +
                " (" +
                (order.Created ?? "") +
                ") - " +
                order.cName ?? ""
            }}</span
          >
          <span v-if="order_mes" style="color: red"
            ><b>Order: </b>{{ order_mes }}</span
          ></label
        ></v-col
      >
    </v-row>
    <v-row>
      <v-col>
        <WzkTable
          :IDWarehouse="IDWarehouse"
          :key="IDWarehouse"
          @item-selected="handleItemSelected"
        />
      </v-col>
    </v-row>
    <!-- Products -->
    <ConfirmDlg ref="confirm" />
    <v-dialog v-model="dialogMessageQty" width="auto">
      <v-card width="600" prepend-icon="mdi-pencil">
        <v-card-text>
          <v-textarea
            v-model="order.Uwagi"
            label="Dokument Uwagi"
            row-height="15"
            rows="1"
            variant="outlined"
            auto-grow
          ></v-textarea>
          <v-text-field label="Uwagi" v-model="edit.Uwagi"></v-text-field>
          <v-text-field label="Ilość" v-model="edit.qty"></v-text-field>
          <v-select
            v-model="edit.IDWarehouseLocation"
            :items="locations"
            label="Locations"
            :item-value="key"
          ></v-select>
        </v-card-text>
        <template v-slot:actions>
          <v-btn
            class="ms-auto"
            text="Ok"
            @click.once="dialogMessageQty = false"
          ></v-btn>
        </template>
      </v-card>
    </v-dialog>
    <v-dialog v-model="dialog" width="auto">
      <v-card
        max-width="600"
        prepend-icon="mdi-alert-outline"
        :text="dialog_text"
        :title="dialog_title"
      >
        <template v-slot:actions>
          <v-btn
            class="ms-auto"
            text="Ok"
            @click="
              dialog = false;
              $refs.dProduct.focus;
            "
          ></v-btn>
        </template>
      </v-card>
    </v-dialog>
    <v-dialog
      id="dialogProduct"
      ref="dProduct"
      v-model="dialogProduct"
      transition="dialog-bottom-transition"
      fullscreen
      @keyup="handleKeypress"
    >
      <v-container>
        <v-card>
          <v-card-title class="mb-5 bg-grey-lighten-3">
            <v-row>
              <v-col>
                <b>Order: </b>
                <span v-if="order.Number">{{
                  (order.Number ?? "") +
                    " - " +
                    order.pk +
                    " (" +
                    (order.Created ?? "") +
                    ") - " +
                    order.cName ?? ""
                }}</span>
                <span v-if="order_mes" style="color: red">{{
                  order_mes
                }}</span></v-col
              >
              <v-spacer></v-spacer>

              <v-btn
                icon="mdi-close"
                @click="
                  dialogProduct = false;
                  text = '';
                "
              ></v-btn
            ></v-row>
            <v-row
              ><v-col> Uwagi: {{ order.Uwagi }} </v-col></v-row
            >
          </v-card-title>

          <v-card-text class="vscroll">
            <div class="d-flex flex-column">
              <v-row
                class="product_line border my-0"
                v-for="p in products"
                :key="p.IDTowaru"
                :class="{
                  active: p.IDTowaru == edit.IDTowaru,
                  error: p.qty > p.Quantity,
                  'green-lighten-4': p.qty == p.Quantity,
                  'order-1': p.IDTowaru == edit.IDTowaru,
                  'order-4': p.qty == p.Quantity,
                  'order-0': p.qty > p.Quantity,
                  'order-3': p.qty == 0,
                }"
              >
                <v-col cols="7">
                  <div class="d-flex">
                    <img
                      v-if="p.img"
                      :src="'data:image/jpeg;base64,' + p.img"
                      alt="pic"
                      style="height: 3em"
                    />
                    <span
                      ><h5>
                        {{ p.Nazwa }}<br />cod: {{ p.KodKreskowy }}, sku:
                        {{ p._TowarTempString1 }}
                      </h5>
                    </span>
                    <v-btn
                      size="small"
                      @click="
                        edit = p;
                        dialogMessageQty = true;
                      "
                      icon="mdi-pencil"
                    ></v-btn>
                  </div>
                  <div v-if="p.Uwagi">Uwagi: {{ p.Uwagi }}</div>
                </v-col>
                <v-col cols="2" xs="12">
                  <v-select
                    v-model="p.IDWarehouseLocation"
                    :items="locations"
                    label="Locations"
                    :item-value="key"
                  ></v-select>
                </v-col>
                <v-col cols="3" xs="12">
                  <div class="d-flex justify-end">
                    <v-btn @click="changeCounter(p, -1)">-</v-btn>
                    <div
                      :id="p.IDTowaru"
                      class="border qty text-h5 text-center"
                    >
                      {{ p.qty }} z {{ parseInt(p.Quantity) }}
                      <v-btn
                        size="x-small"
                        @click="splitProduct(p)"
                        v-if="p.qty > 0 && p.qty < p.Quantity"
                        icon="mdi-call-split"
                      ></v-btn>
                    </div>
                    <v-btn @click="changeCounter(p, 1)">+</v-btn>
                  </div>
                </v-col>
              </v-row>
            </div>
          </v-card-text>
          <template v-slot:actions>
            <v-spacer></v-spacer>
            <!-- v-if="products.find((e) => e.qty > 0)" -->
            <section class="row">
              <div class="col">
                <p>Niepełnowartościowe</p>
                <label><input type="radio" v-model="full" value="0" />Nie</label
                ><br />
                <label
                  ><input type="radio" v-model="full" value="1" />Tak</label
                >
              </div>
            </section>
            <button
              class="btn btn-primary my-3"
              :class="{
                disabled:
                  products.find((e) => e.qty > e.Quantity) ||
                  products.reduce((ak, el) => ak + el.qty, 0) == 0,
              }"
              @click.once="checkFullOrder()"
            >
              Tworzenie dokumentu zwrotu
            </button>
          </template>
        </v-card>
      </v-container>
    </v-dialog>
    <Modal v-if="showModal" @close="closeModal">
      <PhotoCapture @result="handleResult" @close="closeModal" />
    </Modal>
    <v-dialog v-model="dialogEditUwagiProduct" width="500">
      <v-card>
        <v-card-title>Product</v-card-title>
        <v-card-text>
          <v-textarea
            v-model="selectedProduct.Uwagi"
            label="Uwagi"
          ></v-textarea>
        </v-card-text>
        <v-card-actions>
          <v-btn text @click="dialogEditUwagiProduct = false">Cancel</v-btn>
          <v-btn text @click="saveUwagiProduct">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script>
import { ref } from "vue";
import axios from "axios";
import Modal from "../UI/Modal.vue";
import PhotoCapture from "../UI/PhotoCapture.vue";
import ConfirmDlg from "../UI/ConfirmDlg.vue";
import WzkTable from "./UI/WzkTable.vue";
import productHistory from "../client/productHistory.vue";
export default {
  name: "Refund",
  components: {
    ConfirmDlg,
    WzkTable,
    Modal,
    PhotoCapture,
    productHistory,
  },
  data() {
    return {
      loading: false,
      dialog: false,
      dialogProduct: false,
      dialogMessageQty: false,
      dialog_text: "",
      dialog_title: "",

      full: 0,
      ordername: "",
      order: {},
      wz: {},
      warehouses: [],
      IDWarehouse: null,
      products: [],
      changeProducts: [],
      edit: {
        id: 0,
        Nazwa: "",
        qty: 0,
        Uwagi: "",
        max: 1,
        IDWarehouseLocation: null,
      },
      order_mes: "",
      imputCod: "",
      text: "",
      wzk_products: [],
      selectedItem: null,

      locations: [],
      selectedItem: null,
      tab: "products",
      headers_products: [
        { title: "Nazwa", key: "Nazwa" },
        { title: "KodKreskowy", key: "KodKreskowy" },
        { title: "SKU", key: "sku" },
        { title: "Ilosc", key: "Ilosc" },
        { title: "LocationCode", key: "LocationCode" },
        {
          title: "Uwagi",
          key: "Uwagi",
          nowrap: true,
        },
      ],
      photoFiles: [],
      files: null,
      selectedProduct: {},
      dialogEditUwagiDoc: false,
      dialogEditUwagiProduct: false,
    };
  },

  mounted() {
    this.getWarehouse();
  },
  setup() {
    const showModal = ref(false);
    const results = ref([]);
    const message = ref("");

    const openModal = () => {
      showModal.value = true;
      message.value = "";
    };

    const closeModal = () => {
      showModal.value = false;
    };

    const handleResult = (data) => {
      results.value.push(data);
    };

    return {
      showModal,
      results,
      message,
      openModal,
      closeModal,
      handleResult,
      // uploadFiles,
    };
  },
  methods: {
    saveUwagiProduct() {
      const vm = this;
      axios
        .post("/api/saveUwagiProduct", {
          IDElementuRuchuMagazynowego:
            vm.selectedProduct.IDElementuRuchuMagazynowego,
          Uwagi: vm.selectedProduct.Uwagi,
        })
        .then((res) => {
          if (res.status == 200) {
            vm.dialogEditUwagiProduct = false;
          }
        })
        .catch((error) => console.log(error));
    },
    editProductUwagi() {
      this.dialogEditUwagiProduct = true;
    },
    saveUwagiDoc() {
      const vm = this;
      axios
        .post("/api/saveUwagiDoc", {
          IDRuchuMagazynowego: vm.selectedItem.IDRuchuMagazynowego,
          Uwagi: vm.selectedItem.Uwagi,
        })
        .then((res) => {
          if (res.status == 200) {
            vm.dialogEditUwagiDoc = false;
          }
        })
        .catch((error) => console.log(error));
    },
    deleteFile(file_url) {
      const vm = this;

      axios
        .post("/api/deleteFile", {
          file_url: file_url,
        })
        .then((res) => {
          if (res.status == 200) {
            vm.photoFiles = vm.photoFiles.filter((f) => f.url !== file_url);
          }
        })
        .catch((error) => console.log(error));
    },
    splitProduct(product) {
      const vm = this;
      const newProduct = { ...product };

      const foundProductIndex = vm.products.findIndex(
        (p) => p.IDTowaru === product.IDTowaru && p.Quantity == product.Quantity
      );
      const foundProduct =
        foundProductIndex !== -1 ? vm.products[foundProductIndex] : null;
      if (foundProduct) {
        vm.products[foundProductIndex].Quantity -= product.qty;
        vm.products[foundProductIndex].qty = 0;
      }
      newProduct.qty = newProduct.qty;
      newProduct.Quantity = newProduct.qty;

      vm.products.push(newProduct);
    },
    colorRowItem(item) {
      if (
        item.item.IDTowaru != undefined &&
        item.item.IDTowaru == this.selectedProduct.IDTowaru
      ) {
        return { class: "bg-red-darken-4" };
      }
    },
    handleClick(event, row) {
      this.selectedProduct = row.item;
    },
    downloadFile(url, name) {
      axios
        .get(url, {
          responseType: "blob",
        })
        .then((res) => {
          const blobUrl = window.URL.createObjectURL(new Blob([res.data]));
          const link = document.createElement("a");
          link.href = blobUrl;
          link.setAttribute("download", name);
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
        })
        .catch((error) => console.log(error));
    },
    uploadSnapshots(dir) {
      this.uploadFiles(dir, this.results);
    },
    uploadFiles(folder, snapshots) {
      const vm = this;
      let formData = new FormData();
      if (snapshots && snapshots.length) {
        for (let i = 0; i < snapshots.length; i++) {
          formData.append("snapshots[]", snapshots[i].data);
        }
      }
      if (vm.files && vm.files.length) {
        for (let i = 0; i < vm.files.length; i++) {
          formData.append("files[]", vm.files[i]);
        }
      }
      formData.append(
        "IDRuchuMagazynowego",
        vm.selectedItem.IDRuchuMagazynowego
      );
      formData.append("dir", folder);
      axios
        .post("/api/uploadFiles", formData, {
          headers: {
            "Content-Type": "multipart/form-data",
          },
        })
        .then((res) => {
          if (res.status == 200) {
            const a_files = res.data.files;
            if (a_files.length > 0) {
              a_files.forEach((file) => {
                vm.photoFiles.unshift(file);
              });
            }
            vm.files = null;
            vm.results = [];
          }
        })
        .catch((error) => console.log(error));
    },
    getFiles(folder_name) {
      const vm = this;
      vm.photoFiles = [];
      if (!vm.selectedItem) return;
      axios
        .get(
          "/api/getFiles/" +
            vm.selectedItem.IDRuchuMagazynowego +
            "/" +
            folder_name
        )
        .then((res) => {
          if (res.status == 200) {
            vm.photoFiles = res.data.files;
          }
        })
        .catch((error) => console.log(error));
    },
    tabChanged() {
      const vm = this;

      if (vm.photoFiles.length === 0 && vm.tab === "photo") {
        this.getFiles("zworot");
      }
      if (vm.wzk_products.length === 0 && vm.tab === "products") {
        this.get_WZkProducts();
      }
    },
    handleItemSelected(item) {
      this.selectedItem = item;
      this.tab = "products";
      this.products = [];
      this.get_WZkProducts();
    },
    get_WZkProducts() {
      const vm = this;

      if (vm.selectedItem == null) return;
      vm.wzk_products = [];
      vm.photoFiles = [];
      vm.selectedProduct = {};
      vm.loading = true;
      axios
        .post("/api/getWZkProducts", {
          IDRuchuMagazynowego: vm.selectedItem.IDRuchuMagazynowego,
        })
        .then((res) => {
          if (res.status == 200) {
            vm.wzk_products = res.data;
            vm.wzk_products.map((e) => {
              e.Ilosc = parseInt(e.Ilosc);
              e.inLocation = parseInt(e.inLocation);
              e.KodKreskowy = parseInt(e.KodKreskowy);
            });
          }
          vm.loading = false;
        })
        .catch((error) => console.log(error));
    },
    handleKeypress(event) {
      // Check if the Enter key was pressed
      if (event.key === "Enter") {
        // Execute the function with the accumulated input
        this.findProduct();
        // Clear the input field
        this.imputCod = "";
      } else {
        // Append the current keystroke to the input
        this.imputCod += event.key;
      }
    },

    clear() {
      this.order = {};
      this.wz = {};
      this.products = [];
      this.ordername = "";
      this.imputCod = "";
    },
    async ConfirmFullOrder() {
      if (
        await this.$refs.confirm.open(
          "Zwrot jest niekompletny!",
          "Czy zwrot jest na pewno niekompletny?"
        )
      ) {
        await this.doWz();
      } else {
        this.dialogProduct = true;
      }
    },
    async checkFullOrder() {
      let sQty = this.products.reduce((acc, el) => acc + parseInt(el.qty), 0);
      let sQua = this.products.reduce(
        (acc, el) => acc + parseInt(el.Quantity),
        0
      );
      if (sQty != sQua) {
        this.dialogProduct = false;
        this.ConfirmFullOrder();
      } else {
        await this.doWz();
      }
    },
    doWz() {
      const vm = this;
      let data = {};
      let ps = vm.products.filter((e) => e.qty > 0);
      ps = ps.map((t) => {
        return [
          "IDTowaru",
          "CenaJednostkowa",
          "Uwagi",
          "qty",
          "IDWarehouseLocation",
        ].reduce((a, e) => ((a[e] = t[e]), a), {});
      });
      data.magazin = vm.warehouses.filter(
        (m) => m.IDMagazynu == vm.IDWarehouse
      )[0];
      data.wz = vm.wz;
      data.products = ps;
      data.order_id = vm.order.IDOrder;
      data.order_Uwagi = vm.order.Uwagi;
      data.full = vm.full;
      axios
        .post("/api/doWz", data)
        .then((res) => {
          if (res.status == 200) {
            vm.clear();
            vm.order_mes = res.data;
            vm.dialogProduct = false;
          } else {
            vm.order_mes = res.data;
          }
        })
        .catch((error) => console.log(error));
    },
    setLocations() {
      const vm = this;
      const a_locations = vm.warehouses.filter((e) => {
        return e.IDMagazynu == vm.IDWarehouse;
      });

      vm.locations = [
        {
          title: "Zwrot",
          value: a_locations[0].IDLokalizaciiZwrot,
        },
        { title: "Zniszczony", value: a_locations[0].Zniszczony },
        { title: "Naprawa", value: a_locations[0].Naprawa },
      ];
    },
    getWarehouse() {
      const vm = this;
      vm.locations = [];
      axios
        .get("/api/getWarehouse")
        .then((res) => {
          if (res.status == 200) {
            vm.warehouses = res.data;
            if (vm.warehouses.length > 0) {
              vm.IDWarehouse = vm.warehouses[0].IDMagazynu;
              vm.setLocations();
            }
          }
        })
        .catch((error) => console.log(error));
    },
    getOrder() {
      const vm = this;
      if (vm.ordername == "") {
        vm.clear();
        return;
      }
      vm.order_mes = "";
      vm.order = {};
      let data = {};
      data.warehouse = vm.IDWarehouse;
      data.ordername = vm.ordername;
      axios
        .post("/api/getOrder", data)
        .then((res) => {
          if (res.status == 200) {
            if (res.data.wz) {
              vm.order = res.data.order ?? {};
              vm.wz = res.data.wz ?? {};
              vm.products = Object.values(res.data.products) ?? [];
              if (vm.products.length) {
                vm.products.map((e) => {
                  e.qty = 0;
                  e.Uwagi = "";
                  e.IDWarehouseLocation = vm.locations[0].value;
                });
                vm.dialogProduct = true;

                // vm.focusOnProduct();
              } else {
                vm.order_mes = "Nie ma takiej kolejności";
                vm.clear();
              }
            } else {
              vm.order_mes = "Nie WZ";
              vm.clear();
            }
          } else if (res.status == 202) {
            vm.order_mes = res.data;
            vm.clear();
          } else {
            vm.order_mes = "Error getOrder()";
            vm.clear();
          }
        })
        .catch((error) => console.log(error));
    },
    findProduct() {
      // KodKreskowy - штрихкод
      // [_TowarTempString1] - артикул, sku
      const product = this.products.find(
        (e) =>
          e.IDTowaru == this.imputCod ||
          e.KodKreskowy == this.imputCod ||
          e["_TowarTempString1"] == this.imputCod
      );
      if (product) {
        this.edit = product;
        this.changeCounter(product, 1);
      } else {
        this.dialog_text = "Brak produktu!!!";
        this.dialog = true;
      }
    },

    changeCounter: function (item, num) {
      item.qty += +num;
      if (item.qty < 0) item.qty = 0;
      this.$refs.dProduct.focus;
    },
    saveEdit() {
      const vm = this;
      vm.edit.qty = vm.edit.qty < vm.edit.max ? vm.edit.qty : vm.edit.max;
      vm.edit.qty = vm.edit.qty == 0 ? "" : vm.edit.qty;
      vm.edit.Uwagi = vm.edit.qty == 0 ? "" : vm.edit.Uwagi;
      this.products = this.products.map((x) =>
        x.IDTowaru === vm.edit.id
          ? { ...x, qty: vm.edit.qty, Uwagi: vm.edit.Uwagi }
          : x
      );
      // this.products.sort((a.qty, b.qty) => a.qty - b.qty);
      this.edit.id = 0;
      this.imputCod = "";
    },
  },
};
</script>

<style lang="scss">
.qty {
  width: 150px;
}
.active .qty {
  background: #e0e0e0;
}
.error .qty {
  background: #ffcdd2;
}
.vscroll {
  max-height: 70vh;
  overflow-y: auto;
}
</style>
