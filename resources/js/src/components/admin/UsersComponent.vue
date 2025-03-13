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
          <v-btn @click="showAddTokenDialog" color="primary">Add Token</v-btn>
        </v-col>

        <v-col cols="3" md="1">
          <v-alert v-if="warehouseToken" type="success"> Token: ok </v-alert>
          <v-alert v-else type="error"> No token</v-alert>
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
    };
  },

  methods: {
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
      this.filteredUsers = this.settings.filter(
        (user) =>
          user.for_obj === this.selectedWarehouse && user.obj_name === "ext_id"
      );
      this.isToken();
    },
    async createUser() {
      const newUserSetting = {
        obj_name: "ext_id",
        for_obj: this.selectedWarehouse,
        key: this.newUser.key,
        value: this.newUser.value,
      };
      const response = await axios.post("/api/settings", newUserSetting);
      console.log(response.data);
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
