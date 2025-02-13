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
          @update:modelValue="setTransComany"
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
        ></v-select>
      </v-col>
    </v-row>
    <v-row v-if="ordersPropucts.length">
      <v-col>
        <v-data-table :items="ordersPropucts"> </v-data-table>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import axios from "axios";
import _ from "lodash";

export default {
  name: "FulstorCollectProducts",

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
    };
  },

  mounted() {
    this.getWarehouse();
    this.getAllOrders();
  },

  methods: {
    getOrderProducts() {
      const vm = this;
      let IDsOrder = vm.ordersTransCompany
        .filter((item) => vm.IDsTransCompany.includes(item.IDTransport))
        .map((item) => item.IDOrder);
      vm.loading = true;
      axios
        .post("/api/getOrderProducts", { IDsOrder: IDsOrder })
        .then((res) => {
          if (res.status == 200) {
            vm.ordersPropucts = res.data;
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
            vm.allOrders = res.data;
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
  },
};
</script>
