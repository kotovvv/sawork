<template>
  <v-container>
    <h3>Collect Products</h3>
    <v-snackbar v-model="snackbar" timeout="6000" location="top">
      {{ message }}

      <template v-slot:actions>
        <v-btn color="pink" variant="text" @click="snackbar = false">
          Close
        </v-btn>
      </template>
    </v-snackbar>
    <v-progress-linear
      :active="loading"
      indeterminate
      color="purple"
    ></v-progress-linear>
    <v-row>
      <v-col cols="12" md="2">
        <!-- prepend-icon="mdi-swap-horizontal" -->
        <v-select
          label="Magazyn"
          v-model="IDsWarehouses"
          :items="
            warehouses.filter((item) =>
              ordersWarehauses.includes(item.IDMagazynu)
            )
          "
          :item-title="
            (item) =>
              `${item.Nazwa} (${
                groupsOrdersWarehauses[item.IDMagazynu]?.length || 0
              })`
          "
          item-value="IDMagazynu"
          @update:modelValue="
            setTransComany();
            getOrderProducts();
          "
          hide-details="auto"
          multiple
          clearable
        ></v-select>
      </v-col>
      <v-col cols="12" md="2" v-if="IDsWarehouses.length">
        <!-- prepend-icon="mdi-swap-horizontal" -->
        <v-select
          label="Transport"
          v-model="IDsTransCompany"
          :items="transportCompany"
          @update:modelValue="getOrderProducts"
          hide-details="auto"
          multiple
          clearable
          append-icon="mdi-refresh"
          @click:append="
            getAllOrders();
            getOrderProducts();
            setTransComany();
            clear();
          "
        ></v-select>
      </v-col>
      <v-col v-if="IDsWarehouses.length">
        <div class="d-flex flex-wrap">
          <v-text-field
            label="Max towary"
            v-model="maxProducts"
            hide-details="auto"
            max-width="150"
            type="number"
          ></v-text-field>
          <v-text-field
            label="Max m3"
            v-model="maxM3"
            hide-details="auto"
            max-width="150"
            type="number"
          ></v-text-field>
          <v-text-field
            label="Max waga"
            v-model="maxWeight"
            hide-details="auto"
            max-width="150"
            type="number"
          ></v-text-field>
        </div>
      </v-col>
      <v-col v-if="makeOrders && makeOrders.length > 0" cols="12" md="2">
        <v-select
          label="Wybrane zamówienia"
          v-model="selectedMakeOrders"
          :items="makeOrders"
          item-title="NumberBL"
          item-value="IDOrder"
          hide-details="auto"
          multiple
          clearable
        >
          <template v-slot:prepend-item>
            <v-btn @click="checkAll" icon="mdi-check-all" class="ma-1"></v-btn>
            <v-btn
              @click="deleteSelectedMakeOrders"
              icon="mdi-delete"
              class="ma-1"
            ></v-btn>
          </template>
          <template v-slot:selection="{ item, index }">
            <v-chip v-if="index < 2">
              <span>{{ item.title }}</span>
            </v-chip>
            <span
              v-if="index === 2"
              class="text-grey text-caption align-self-center"
            >
              (+{{ selectedMakeOrders.length - 2 }} inni)
            </span>
          </template>
        </v-select>
      </v-col>
    </v-row>
    <!-- ERROR -->
    <v-row v-if="messages.length">
      <v-col class="red">
        <h4>ERROR</h4>
        <v-table>
          <thead>
            <tr>
              <th>Message</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, ix) in messages" :key="ix">
              <td>{{ item }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-col>
    </v-row>

    <v-row v-if="productsERROR.length">
      <v-col class="red">
        <h4>ERROR</h4>
        <v-table>
          <thead>
            <tr>
              <th>Number</th>
              <th>Product</th>
              <th>Quantity</th>
              <th>Weight</th>
              <th>Volume</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in productsERROR" :key="item.IDProduct">
              <td>{{ item.Number }}</td>
              <td>{{ item.Product }}</td>
              <td>{{ item.Quantity }}</td>
              <td>{{ item.Weight }}</td>
              <td>{{ item.Volume }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-col>
    </v-row>
    <v-row v-if="orderERROR.length">
      <v-col class="red">
        <h4>ERROR</h4>
        <v-table>
          <thead>
            <tr>
              <th>IDMagazynu</th>
              <th>IDOrder</th>
              <th>NumberBL</th>
              <th>IDItem</th>
              <th>qty</th>
              <th>Uwagi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in orderERROR" :key="item.IDProduct">
              <td>{{ item.IDMagazynu }}</td>
              <td>{{ item.IDOrder }}</td>
              <td>{{ item.NumberBL }}</td>
              <td>{{ item.IDItem }}</td>
              <td>{{ item.qty }}</td>
              <td>{{ item.Uwagi }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-col>
    </v-row>

    <v-row v-if="ordersPropucts.length">
      <v-col>
        <v-btn @click="prepareDoc" v-if="ordersPropucts.length">Zbierać</v-btn>
      </v-col>
    </v-row>
    <v-row v-if="ordersPropucts.length">
      <v-col>
        <p>
          Orders ({{
            allOrders.filter((item) => selectedOrders.includes(item.IDOrder))
              .length
          }}):
          {{
            allOrders
              .filter((item) => selectedOrders.includes(item.IDOrder))
              .map((item) => item.Number)
              .join(", ")
          }}
        </p>
        <p>
          Paramas: maxProducts {{ endParamas.maxProducts }}, maxM3
          {{ parseFloat(endParamas.maxM3).toFixed(4) }}, maxWeight
          {{ parseFloat(endParamas.maxWeight).toFixed(4) }}
        </p>
      </v-col>
    </v-row>
    <v-row v-if="ordersPropucts.length">
      <v-col cols="12">
        <v-btn @click="prepareXLSX()" size="x-large">pobieranie XLSX</v-btn>
      </v-col>
      <v-col>
        <v-data-table id="ordersPropucts" :items="ordersPropucts">
        </v-data-table>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import axios from "axios";
import _ from "lodash";
import * as XLSX from "xlsx";
import { saveAs } from "file-saver";

export default {
  name: "FulstorcollectOrders",

  data() {
    return {
      snackbar: false,
      message: "",
      IDsWarehouses: [],
      warehouses: [],
      ordersWarehauses: [],
      groupsOrdersWarehauses: [],
      IDsTransCompany: [],
      transportCompany: [],
      ordersTransCompany: [],
      groupTranCompany: [],
      allOrders: [],
      ordersPropucts: [],
      loading: false,
      maxProducts: 30,
      maxWeight: 30,
      maxM3: 0.2,
      selectedOrders: [],
      endParamas: [],
      makeOrders: [],
      selectedMakeOrders: [],
      messages: [],
      createdDoc: [],
      productsERROR: [],
      orderERROR: [],
    };
  },

  mounted() {
    this.getWarehouse();
    this.getAllOrders();
  },

  methods: {
    clear() {
      this.selectedOrders = [];
      this.ordersPropucts = [];
      this.messages = [];
      this.orderERROR = [];
      this.productsERROR = [];
      this.IDsTransCompany = [];
    },
    deleteSelectedMakeOrders() {
      const vm = this;
      axios
        .post("/api/deleteSelectedMakeOrders", {
          selectedOrders: vm.selectedMakeOrders,
        })
        .then((res) => {
          if (res.status == 200) {
            vm.snackbar = true;
            vm.message = res.data.message;
            vm.makeOrders = res.data.makeOrders;
            vm.loading = false;
          }
        })
        .catch((error) => console.log(error));
    },
    checkAll() {
      if (this.selectedMakeOrders.length == this.makeOrders.length) {
        this.selectedMakeOrders = [];
      } else {
        this.selectedMakeOrders = this.makeOrders.map((item) => item.IDOrder);
      }
    },
    prepareDoc() {
      const vm = this;
      vm.loading = true;
      axios
        .post("/api/prepareDoc", {
          IDsWarehouses: vm.IDsWarehouses,
          orders: vm.ordersTransCompany.filter((o) => {
            return vm.selectedOrders.includes(o.IDOrder);
          }),
        })
        .then((res) => {
          if (res.status == 200) {
            vm.snackbar = true;
            vm.message = "Dokumenty przygotowane";

            vm.makeOrders = res.data.listOrders;
            vm.ordersPropucts = res.data.listProductsOK;
            vm.loading = false;
            vm.messages = res.data.messages;
            vm.createdDoc = res.data.createdDoc;
            vm.productsERROR = res.data.productsERROR;
            vm.orderERROR = res.data.orderERROR;
            vm.getAllOrders();
            vm.selectedMakeOrders = [];
            vm.setTransComany();
          }
        })
        .catch((error) => console.log(error));
    },
    getOrderProducts() {
      const vm = this;
      vm.ordersPropucts = [];
      vm.selectedOrders = [];
      vm.endParamas = [];
      if (vm.IDsTransCompany.length == 0 || vm.IDsWarehouses.length == 0) {
        return;
      }
      let IDsOrder = vm.ordersTransCompany
        .filter((item) => vm.IDsTransCompany.includes(item.IDTransport))
        .map((item) => item.IDOrder);
      vm.loading = true;
      axios
        .post("/api/getOrderProducts", {
          IDsOrder: IDsOrder,
          maxProducts: vm.maxProducts,
          maxWeight: vm.maxWeight,
          maxM3: vm.maxM3,
          IDsWarehouses: vm.IDsWarehouses,
        })
        .then((res) => {
          if (res.status == 200) {
            vm.ordersPropucts = res.data.listProducts;
            vm.selectedOrders = res.data.selectedOrders;
            vm.endParamas = res.data.endParamas;
            vm.loading = false;
          }
        })
        .catch((error) => console.log(error));
    },

    setTransComany() {
      this.ordersTransCompany = this.allOrders.filter((item) =>
        this.IDsWarehouses.includes(item.IDWarehouse)
      );
      this.groupTranCompany = _.groupBy(this.ordersTransCompany, "IDTransport");
      this.transportCompany = Object.keys(this.groupTranCompany).map((key) => {
        const item = this.groupTranCompany[key];
        return {
          title:
            item[0].transport_name +
            " (" +
            this.groupTranCompany[key].length +
            ")",
          value: item[0].IDTransport,
        };
      });
    },
    getAllOrders() {
      const vm = this;
      vm.loading = true;
      axios
        .get("/api/getAllOrders")
        .then((res) => {
          if (res.status == 200) {
            vm.allOrders = res.data.allOrders;
            vm.makeOrders = res.data.waiteOrders;
            vm.groupOrdersByWarehouse();
            vm.loading = false;
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
    groupOrdersByWarehouse() {
      this.groupsOrdersWarehauses = _.groupBy(this.allOrders, "IDWarehouse");
      this.ordersWarehauses = Object.keys(this.groupsOrdersWarehauses);
    },
    prepareXLSX() {
      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.json_to_sheet(this.ordersPropucts);
      XLSX.utils.book_append_sheet(wb, ws, "");

      const wbout = XLSX.write(wb, { bookType: "xlsx", type: "array" });
      saveAs(
        new Blob([wbout], { type: "application/octet-stream" }),
        "collect" + ".xlsx"
      );
    },
  },
};
</script>
