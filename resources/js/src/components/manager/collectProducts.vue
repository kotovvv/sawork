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
          :items="filterWarehouses"
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
            clear();
            getAllOrders();
            getOrderProducts();
            setTransComany();
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
              <th>IDOrder</th>
              <th>Quantity</th>
              <th>NumberBL</th>
              <th>IDMagazynu</th>
              <th>Uwagi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in productsERROR" :key="item.IDProduct">
              <td>{{ item.IDItem }}</td>
              <td>{{ item.IDOrder }}</td>
              <td>{{ item.qty }}</td>
              <td>{{ item.NumberBL }}</td>
              <td>{{ item.IDMagazynu }}</td>
              <td>{{ item.Uwagi }}</td>
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
              <th>IDWarehouse</th>
              <th>IDOrder</th>
              <th>NumberBL</th>
              <th>Date</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in orderERROR" :key="item.IDProduct">
              <td>{{ item.IDWarehouse }}</td>
              <td>{{ item.IDOrder }}</td>
              <td>{{ item.NumberBL }}</td>
              <td>{{ item.Date }}</td>
              <td>{{ item.Remarks }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-col>
    </v-row>

    <v-row v-if="ordersPropucts.length && showBtn">
      <v-col>
        <v-btn @click="prepareDoc">Zbierać</v-btn>
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
        <v-btn @click="generatePDF()" size="x-large">Pobieranie PDF</v-btn>
      </v-col>
      <v-col>
        <v-data-table
          id="ordersPropucts"
          :items="ordersPropucts"
          :headers="currentHeaders"
        >
          <template v-slot:item.qty="{ item }">
            <span
              :style="{ backgroundColor: item.qty > 1 ? 'grey' : 'white' }"
              >{{ item.qty }}</span
            >
          </template>
        </v-data-table>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import axios from "axios";
// import * as XLSX from "xlsx";
import * as XLSX from "xlsx-js-style";
import _ from "lodash";
// import * as XLSXStyle from "xlsx-style";
import { saveAs } from "file-saver";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

export default {
  name: "FulstorcollectOrders",

  data() {
    return {
      snackbar: false,
      message: "",
      IDsWarehouses: [],
      warehouses: [],
      filterWarehouses: [],
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
      showBtn: false,
      headers: [
        { title: "Nazwa", value: "Nazwa" },
        { title: "SKU", value: "SKU" },
        { title: "EAN", value: "EAN" },
        { title: "location", value: "locationCode" },
        { title: "Ilosc", value: "qty" },
      ],
      headersForGetOrderProducts: [
        { title: "IDWarehouse", value: "IDWarehouse" },
        { title: "NumberBL", value: "NumberBL" },
        { title: "IDOrder", value: "IDOrder" },
        { title: "IDItem", value: "IDItem" },
        { title: "Quantity", value: "Quantity" },
        { title: "locations", value: "locations" },
      ],
      currentFunction: null,
    };
  },

  mounted() {
    this.getWarehouse();
    this.getAllOrders();
    this.generatePDF = this.generatePDF.bind(this);
  },
  computed: {
    currentHeaders() {
      if (this.currentFunction === "prepareDoc") {
        return this.headers;
      } else if (this.currentFunction === "getOrderProducts") {
        return this.headersForGetOrderProducts;
      } else {
        return [];
      }
    },
  },
  methods: {
    generatePDF() {
      const doc = new jsPDF();
      const columns = this.currentHeaders.map((header) => header.title);
      const rows = this.ordersPropucts
        .sort((a, b) => (a.locationCode > b.locationCode ? 1 : -1))
        .map((item) => {
          return this.currentHeaders.map((header) => item[header.value]);
        });

      autoTable(doc, {
        head: [columns],
        body: rows,
        didDrawCell: (data) => {
          // Adjust for Polish language if needed
          if (data.column.index === 0) {
            doc.setFont("helvetica", "bold");
          }
        },
      });

      const pdfBlob = doc.output("blob");
      const pdfUrl = URL.createObjectURL(pdfBlob);
      window.open(pdfUrl, "_blank");
    },
    clear() {
      this.selectedOrders = [];
      this.ordersPropucts = [];
      this.messages = [];
      this.orderERROR = [];
      this.productsERROR = [];
      this.IDsWarehouses = [];
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
      vm.productsERROR = [];
      vm.orderERROR = [];
      vm.loading = true;
      vm.currentFunction = "prepareDoc";
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
            vm.showBtn = false;
          }
        })
        .catch((error) => {
          console.log(error);
          vm.loading = false;
          vm.snackbar = true;
          vm.message = error.response.data.message;
        });
    },
    getOrderProducts() {
      const vm = this;
      vm.showBtn = true;
      vm.ordersPropucts = [];
      vm.selectedOrders = [];
      vm.endParamas = [];
      if (vm.IDsTransCompany.length == 0 || vm.IDsWarehouses.length == 0) {
        return;
      }
      vm.currentFunction = "getOrderProducts";
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
      this.filterWarehouses = this.warehouses.filter((item) =>
        this.ordersWarehauses.includes(item.IDMagazynu)
      );
    },
    prepareXLSX() {
      const wb = XLSX.utils.book_new();
      let ws;
      if (this.currentFunction == "prepareDoc") {
        const filteredData = this.ordersPropucts.map((item) => ({
          Nazwa: item.Nazwa,
          SKU: item.SKU,
          EAN: parseInt(item.EAN),
          locationCode: item.locationCode,
          Ilość: parseInt(item.qty),
        }));
        ws = XLSX.utils.json_to_sheet(filteredData);
      } else {
        ws = XLSX.utils.json_to_sheet(this.ordersPropucts);
      }
      // Apply styles to the "Ilość" column if the value is greater than 1
      const range = XLSX.utils.decode_range(ws["!ref"]);
      for (let row = range.s.r + 1; row <= range.e.r; row++) {
        const cellAddress = XLSX.utils.encode_cell({ r: row, c: 4 }); // Column index 4 corresponds to "Ilość"
        if (!ws[cellAddress]) continue; // Skip if the cell doesn't exist
        if (ws[cellAddress].v > 1) {
          ws[cellAddress].s = {
            fill: {
              patternType: "solid",
              fgColor: { rgb: "D3D3D3" }, // Grey background
            },
          };
        }
      }
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
