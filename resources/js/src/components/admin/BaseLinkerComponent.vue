<template>
  <div>
    <v-container>
      <v-row>
        <v-col cols="12" md="3">
          <v-select
            v-model="selectedWarehouse"
            :items="warehouses"
            item-text="Nazwa"
            item-value="IDMagazynu"
            label="Select Warehouse"
            @update:modelValue="filterUsers"
          ></v-select>
          <div v-if="selectedWarehouse">
            <v-alert v-if="warehouseToken" type="success"> Token: ok </v-alert>
            <v-alert v-else type="error"> No token</v-alert>
            <v-btn @click="showAddTokenDialog" color="primary" class="ma-2"
              >Change Token</v-btn
            >
          </div>

          <v-row v-if="selectedWarehouse">
            <v-col cols="6">
              <v-text-field
                v-model="intervalMinutes"
                label="Interval Minutes"
                type="number"
              >
                <template v-slot:append>
                  <v-icon @click="saveInterval" class="btn" color="primary"
                    >mdi-content-save</v-icon
                  >
                </template>
              </v-text-field>
            </v-col>
            <v-col cols="6" class="d-flex align-center">
              {{ lastExecutedAt }}
            </v-col>
            <v-col cols="6">
              <v-text-field v-model="orderId" label="id order for import">
                <template v-slot:append>
                  <v-icon
                    @click="importSingleOrder(selectedWarehouse, orderId)"
                    class="btn"
                    color="primary"
                    :loading="loadingOrderImport"
                    :messages="messages"
                  >
                    mdi-content-save
                  </v-icon>
                </template>
              </v-text-field>
            </v-col>
          </v-row>
        </v-col>

        <v-col v-if="selectedWarehouse" cols="12" md="8">
          <v-row>
            <v-col>
              <v-data-table
                :headers="headers"
                :items="filteredUsers"
                item-key="ID"
              >
                <template v-slot:item.userName="{ item }">
                  {{
                    users.find((user) => user.IDUzytkownika === item.key).title
                  }}
                </template>
                <template v-slot:item.actions="{ item }">
                  <v-btn @click="editUser(item)" color="primary">Edit</v-btn>
                  <v-btn @click="deleteUser(item.id)" color="error"
                    >Delete</v-btn
                  >
                </template>
              </v-data-table>

              <v-btn @click="showCreateDialog" color="primary">Add User</v-btn>
            </v-col>
          </v-row>
        </v-col>
      </v-row>
      <v-row v-if="logs.length > 0">
        <v-col cols="12">
          <v-data-table
            ref="logsTable"
            :headers="[
              { title: 'Number BL', value: 'number' },
              { title: 'Message', value: 'message' },
              { title: 'Type', value: 'type' },
              { title: 'Created At', value: 'created_at' },
            ]"
            :items="logs"
            item-value="id"
            class="elevation-1"
            :search="search"
            :items-per-page="itemsPerPage"
            @update:page="getLog"
          >
            <template v-slot:top>
              <v-toolbar flat>
                <v-toolbar-title>
                  <v-text-field
                    v-model="search"
                    label="Search number BL"
                    single-line
                    hide-details
                    width="300"
                    clearable
                    @update:modelValue="getLog(1, search)"
                  ></v-text-field
                ></v-toolbar-title>
                <v-icon @click="getLog(page, search)" color="primary"
                  >mdi-refresh</v-icon
                >
                <v-spacer></v-spacer>
              </v-toolbar>
            </template>
            <template v-slot:bottom>
              <div class="text-center pt-2">
                <v-pagination
                  v-model="page"
                  :length="pageCount"
                  @click="getLog(page, search)"
                ></v-pagination>
              </div>
            </template>
          </v-data-table>
        </v-col>
      </v-row>
      <v-dialog v-model="createDialog" persistent max-width="600px">
        <v-btn icon @click="createDialog = false" class="close-btn">
          <v-icon>mdi-close</v-icon>
        </v-btn>
        <v-card>
          <v-card-title> Add User </v-card-title>
          <v-card-text>
            <v-form @submit.prevent="createUser">
              <v-select
                v-model="newUser.key"
                :items="users"
                item-title="title"
                item-value="IDUzytkownika"
                label="Select User"
              ></v-select>
              <v-text-field
                v-model="newUser.value"
                label="External ID"
              ></v-text-field>
              <v-btn type="submit" color="primary">Create User</v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-dialog>
      <v-dialog v-model="editUserDialog" persistent max-width="600px">
        <v-btn icon @click="editUserDialog = false" class="close-btn">
          <v-icon>mdi-close</v-icon>
        </v-btn>
        <v-card>
          <v-card-title>Edit User <v-spacer></v-spacer> </v-card-title>
          <v-card-text>
            <v-form @submit.prevent="updateUser">
              <v-select
                v-model="editingUser.key"
                :items="users"
                item-title="title"
                item-value="IDUzytkownika"
                label="Select User"
              ></v-select>
              <v-text-field
                v-model="editingUser.value"
                label="External ID"
              ></v-text-field>
              <v-btn type="submit" color="primary">Update User</v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-dialog>
      <v-dialog v-model="addTokenDialog" persistent max-width="600px">
        <v-btn icon @click="addTokenDialog = false" class="close-btn">
          <v-icon>mdi-close</v-icon>
        </v-btn>
        <v-card>
          <v-card-title> Add Token </v-card-title>
          <v-card-text>
            <v-form @submit.prevent="addToken">
              <v-text-field v-model="newToken" label="Token"></v-text-field>
              <v-btn type="submit" color="primary">Add Token</v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-dialog>
    </v-container>
  </div>
</template>

<script>
import axios from "axios";

export default {
  data() {
    return {
      users: [],
      warehouses: [],
      settings: [],
      usersInWarehouse: [],
      filteredUsers: [],
      selectedWarehouse: null,
      warehouseToken: null,
      newUser: {
        value: null,
        key: "",
      },
      editingUser: null,
      newToken: "",
      editUserDialog: false,
      createDialog: false,
      addTokenDialog: false,
      headers: [
        { title: "User ID", value: "key" },
        { title: "Nazwa", value: "userName" },
        { title: "Stan", value: "value" },
        { title: "Actions", value: "actions", sortable: false },
      ],
      lastLogId: null,
      intervalMinutes: null,
      lastExecutedAt: null,
      logs: [],
      search: "",
      page: 1,
      itemsPerPage: 50,
      pageCount: 0,
      orderId: null,
      loadingOrderImport: false,
      messages: [],
    };
  },

  methods: {
    importSingleOrder(warehouseId, orderId) {
      this.loadingOrderImport = true;
      axios
        .get(`/api/importSingleOrder/${warehouseId}/${orderId}`)
        .then((response) => {
          if (response.data.success) {
            this.orderId = null;
            this.loadingOrderImport = false;
            this.messages = [response.data];
          } else {
            this.$emit("error", response.data.message);
          }
        })
        .catch((error) => {
          console.error("Error importing order:", error);
          this.$emit("error", "Failed to import order");
        });
    },
    async getLog(page = 1, search = "") {
      const params = {
        page,
        search,
        IDWarehouse: this.selectedWarehouse,
        limit: this.itemsPerPage,
      };
      const response = await axios.post("/api/log_orders", params);
      this.logs = response.data.logs;
      this.logs.forEach((log) => {
        log.created_at = new Date(log.created_at).toLocaleString();
      });

      this.pageCount = Math.ceil(response.data.count / params.limit);
    },
    saveInterval() {
      const intervalSetting = {
        for_obj: this.selectedWarehouse,
        value: this.intervalMinutes,
      };
      axios.post("/api/intervalSetting", intervalSetting);
    },
    isToken() {
      const warehouse = this.settings.find(
        (wh) =>
          wh.key === this.selectedWarehouse && wh.obj_name === "sklad_token"
      );

      this.warehouseToken = warehouse ? true : false;
    },
    async fetchSettings() {
      const response = await axios.get("/api/settings");
      this.settings = response.data.settings;
      this.warehouses = response.data.warehouses;
      this.users = response.data.users;
      this.isToken();
    },
    filterUsers() {
      this.page = 1;
      this.logs = [];
      this.search = "";
      this.filteredUsers = this.settings.filter(
        (user) =>
          user.for_obj === this.selectedWarehouse && user.obj_name === "ext_id"
      );
      console.log(this.filteredUsers);
      this.intervalMinutes =
        this.settings.filter(
          (int) =>
            int.for_obj === this.selectedWarehouse &&
            int.obj_name === "interval_minutes"
        )[0]?.value ?? 0;
      this.settings.filter(
        (int) =>
          int.for_obj === this.selectedWarehouse &&
          int.obj_name === "interval_minutes"
      ) ?? 0;
      this.lastExecutedAt =
        this.settings.filter(
          (int) =>
            int.for_obj === this.selectedWarehouse &&
            int.obj_name === "last_executed_at"
        )[0]?.value ?? "";
      this.lastLogId = this.settings.filter(
        (int) =>
          int.for_obj === this.selectedWarehouse &&
          int.obj_name === "last_log_id"
      );
      this.isToken();
      this.getLog(1, this.search);
    },
    async createUser() {
      const newUserSetting = {
        obj_name: "ext_id",
        for_obj: this.selectedWarehouse,
        key: this.newUser.key,
        value: this.newUser.value,
      };
      const response = await axios.post("/api/settings", newUserSetting);

      this.settings.push(response.data);
      this.filterUsers();
      this.newUser = { value: null, key: "" };
      this.createDialog = false;
    },
    editUser(user) {
      this.editingUser = { ...user };
      this.editUserDialog = true;
    },
    async updateUser() {
      const updatedUserSetting = {
        obj_name: "ext_id",
        for_obj: this.selectedWarehouse,
        key: this.editingUser.key,
        value: this.editingUser.value,
      };
      const response = await axios.put(
        `/api/settings/${this.editingUser.id}`,
        updatedUserSetting
      );
      const index = this.settings.findIndex(
        (setting) => setting.key === response.data.id
      );
      this.settings.splice(index, 1, response.data);
      this.filterUsers();
      this.editUserDialog = false;
    },
    async deleteUser(id) {
      await axios.delete(`/api/settings/${id}`);
      this.settings = this.settings.filter((sett) => sett.id !== id);
      this.filterUsers();
    },
    showCreateDialog() {
      this.createDialog = true;
    },
    showAddTokenDialog() {
      this.addTokenDialog = true;
    },
    async addToken() {
      const newTokenSetting = {
        obj_name: "sklad_token",
        for_obj: this.selectedWarehouse,
        key: this.selectedWarehouse,
        value: this.newToken,
      };
      await axios.post("/api/settings", newTokenSetting);
      this.newToken = "";
      this.addTokenDialog = false;
      this.fetchSettings();
    },
  },
  created() {
    this.fetchSettings();
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
