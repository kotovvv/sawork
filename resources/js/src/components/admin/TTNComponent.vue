<template>
  <v-container>
    <v-card>
      <v-card-title> TTN Table </v-card-title>
      <v-text-field
        v-model="search"
        append-icon="mdi-magnify"
        label="Search"
        single-line
        hide-details
        class="mb-4"
        max-width="400"
      />
      <v-data-table
        :headers="headers"
        :items="rows"
        :search="search"
        :loading="loading"
        class="elevation-1"
        item-key="id"
        dense
        @click:row="clickRow"
      >
        <template #item.actions="{ item }">
          <v-btn icon @click="editRow(item)">
            <v-icon>mdi-pencil</v-icon>
          </v-btn>
          <v-btn icon @click="deleteRow(item)">
            <v-icon color="red">mdi-delete</v-icon>
          </v-btn>
        </template>
      </v-data-table>
    </v-card>

    <!-- Dialog for Add/Edit -->
    <v-dialog v-model="dialog" max-width="600px">
      <v-card>
        <v-card-title>
          <span class="headline"
            >{{ editedIndex === -1 ? "Add" : "Edit" }} Row</span
          >
        </v-card-title>
        <v-card-text>
          <v-form ref="form">
            <v-text-field
              v-model="editedItem.api_service_id"
              label="API Service ID"
              type="number"
              required
            />
            <v-text-field
              v-model="editedItem.id_warehouse"
              label="Warehouse ID"
              type="number"
              required
              readonly
            />
            <v-text-field
              v-model="editedItem.delivery_method"
              label="Delivery Method"
              required
              readonly
            />
            <v-text-field
              v-model="editedItem.order_source"
              label="Order Source"
              required
              readonly
            />
            <v-text-field
              v-model="editedItem.order_source_id"
              label="Order Source ID"
              type="number"
              required
              readonly
            />
            <v-text-field
              v-model="editedItem.order_source_name"
              label="Order Source Name"
              required
              readonly
            />

            <v-autocomplete
              v-model="editedItem.courier_code"
              :items="codesBL"
              label="Courier Code"
              item-title="name"
              item-value="code"
              required
              clearable
              @update:model-value="
                getAccountsFromBL(
                  editedItem.courier_code,
                  editedItem.id_warehouse
                )
              "
            />
            <v-select
              v-model="editedItem.account_id"
              :items="accountsBL"
              label="Account ID"
              item-title="name"
              item-value="id"
              required
              clearable
              :error-messages="accountSelectError"
            />
            <v-btn
              class="mt-2"
              color="primary"
              @click="
                getAccountsFromBL(
                  editedItem.courier_code,
                  editedItem.id_warehouse
                )
              "
            >
              Refresh Accounts
            </v-btn>
            <v-select
              v-if="editedItem.courier_code"
              v-model="editedItem.service"
              :items="getServices()"
              label="Service"
            >
            </v-select>
            <v-textarea
              v-model="editedItem.info_account"
              label="Info Account (JSON)"
            />
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="blue darken-1" text @click="closeDialog()"
            >Cancel</v-btn
          >
          <v-btn color="blue darken-1" text @click="saveRow()">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script>
import axios from "axios";

export default {
  name: "FulstorTTNComponent",
  data() {
    return {
      loading: false,
      dialog: false,
      editedIndex: -1,
      rows: [],
      editedItem: {
        api_service_id: "",
        id_warehouse: "",
        delivery_method: "",
        order_source: "",
        order_source_id: "",
        order_source_name: "",
        courier_code: [],
        account_id: [],
        service: "",
        info_account: "",
      },
      codesBL: [],
      accountsBL: [],
      accountSelectError: "",
      headers: [
        { title: "ID", value: "id" },
        { title: "API Service ID", value: "api_service_id" },
        { title: "Warehouse ID", value: "id_warehouse" },
        { title: "Warehouse", value: "symbol", sortable: true },
        { title: "Delivery Method", value: "delivery_method", sortable: true },
        { title: "Order Source", value: "order_source", sortable: true },
        { title: "Order Source ID", value: "order_source_id" },
        {
          title: "Order Source Name",
          value: "order_source_name",
          sortable: true,
        },
        { title: "Courier Code", value: "courier_code", sortable: true },
        { title: "Account ID", value: "account_id" },
        { title: "Service", value: "service" },
        { title: "Info Account", value: "info_account", sortable: true },
        // { title: "Created At", value: "created_at" },
        // { title: "Updated At", value: "updated_at" },
        { title: "Actions", value: "actions", sortable: false },
      ],
      search: "",
    };
  },
  mounted() {
    this.fetchRows();
  },
  methods: {
    getServices() {
      // Check if editedItem.form exists and is an array
      console.log(this.editedItem);
      if (
        this.editedItem.fields.form &&
        Array.isArray(this.editedItem.fields.form)
      ) {
        const serviceField = this.editedItem.form.find(
          (f) => f.id === "service" && f.options
        );
        if (serviceField) {
          // Convert options object to array of { name, value }
          return Object.entries(serviceField.options).map(([value, title]) => ({
            title,
            value,
          }));
        }
      }
    },
    fetchRows() {
      this.loading = true;
      axios.get("/api/for-ttn").then((res) => {
        this.rows = res.data;
        this.loading = false;
      });
    },
    openDialog() {
      this.editedIndex = -1;
      this.editedItem = {
        api_service_id: "",
        id_warehouse: "",
        delivery_method: "",
        order_source: "",
        order_source_id: "",
        order_source_name: "",
        courier_code: "",
        account_id: "",
        service: "",
        info_account: "",
      };
      this.dialog = true;
    },
    closeDialog() {
      this.dialog = false;
    },
    getCodesFromBL(id_warehouse) {
      axios
        .get("/api/for-ttn/get-codes-from-bl/" + id_warehouse)
        .then((res) => {
          this.codesBL = res.data;
        });
    },
    getAccountsFromBL(courier_code, id_warehouse) {
      axios
        .get(
          "/api/for-ttn/get-accounts-from-bl/" +
            id_warehouse +
            "/" +
            courier_code
        )
        .then((res) => {
          this.accountsBL = res.data;
        })
        .catch((error) => {
          if (
            error.response &&
            error.response.data &&
            error.response.data.error
          ) {
            this.accountSelectError = error.response.data.error;
            this.accountsBL = [];
          } else {
            this.accountSelectError = "Unknown error";
            this.accountsBL = [];
          }
        });
    },

    clickRow(event, row) {
      this.getCodesFromBL(row.item.id_warehouse);
      this.editRow(row.item);
    },
    editRow(item) {
      this.editedIndex = this.rows.findIndex((row) => row.id === item.id);
      this.editedItem = Object.assign({}, item);
      this.dialog = true;
    },
    saveRow() {
      if (this.editedIndex === -1) {
        // Create
        axios.post("/api/for-ttn", this.editedItem).then(() => {
          this.fetchRows();
          this.closeDialog();
        });
      } else {
        // Update
        axios
          .put(`/api/for-ttn/${this.editedItem.id}`, this.editedItem)
          .then(() => {
            this.fetchRows();
            this.closeDialog();
          });
      }
    },
    deleteRow(item) {
      if (confirm("Are you sure you want to delete this row?")) {
        axios.delete(`/api/for-ttn/${item.id}`).then(() => {
          this.fetchRows();
        });
      }
    },
  },
};
</script>
