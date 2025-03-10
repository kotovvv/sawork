<template>
  <div>
    <v-container>
      <v-row>
        <v-col>
          <v-data-table :headers="headers" :items="users" item-key="ID">
            <template v-slot:item.actions="{ item }">
              <!-- <v-btn @click="editUser(item)" color="warning">Edit</v-btn> -->
              <v-btn @click="deleteUser(item.ID)" color="error">Delete</v-btn>
            </template>
          </v-data-table>
        </v-col>
      </v-row>
      <v-row>
        <v-col>
          <v-btn @click="showCreateDialog" color="primary">Add User</v-btn>
        </v-col>
      </v-row>
      <v-dialog v-model="createDialog" persistent max-width="600px">
        <v-card>
          <v-card-title>Add User</v-card-title>
          <v-card-text>
            <v-form @submit.prevent="createUser">
              <v-select
                v-model="newUser.ID"
                :items="uzytkownicy"
                item-value="IDUzytkownika"
                label="Select User"
              ></v-select>
              <v-text-field
                v-model="newUser.token"
                label="Token"
              ></v-text-field>
              <v-text-field
                v-model="newUser.bl_id"
                label="BL ID"
              ></v-text-field>
              <v-btn type="submit" color="primary">Create User</v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-dialog>
      <v-dialog v-model="editingUser" persistent max-width="600px">
        <v-card>
          <v-card-title>Edit User</v-card-title>
          <v-card-text>
            <v-form @submit.prevent="updateUser">
              <v-text-field
                v-model="editingUser.token"
                label="Token"
              ></v-text-field>
              <v-text-field
                v-model="editingUser.bl_id"
                label="BL ID"
              ></v-text-field>
              <v-btn type="submit" color="primary">Update User</v-btn>
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
      uzytkownicy: [],

      newUser: {
        ID: null,
        token: "",
        bl_id: null,
      },
      editingUser: null,
      createDialog: false,
      headers: [
        { text: "ID", value: "ID" },
        { text: "Token", value: "token" },
        { text: "BL ID", value: "bl_id" },
        { text: "User Name", value: "NazwaUzytkownika" },
        { text: "Actions", value: "actions", sortable: false },
      ],
    };
  },
  methods: {
    async fetchUsers() {
      const response = await axios.get("/api/users");
      this.users = response.data;
    },
    async fetchUzytkownicy() {
      const response = await axios.get("/api/uzytkownicy");
      this.uzytkownicy = response.data;
    },
    async createUser() {
      const response = await axios.post("/api/users", this.newUser);
      this.users = [...this.users, response.data];
      this.newUser = { ID: null, token: "", bl_id: null };
      this.createDialog = false;
    },
    editUser(user) {
      this.editingUser = { ...user };
    },
    async updateUser() {
      const response = await axios.put(
        `/api/users/${this.editingUser.ID}`,
        this.editingUser
      );
      const index = this.users.findIndex(
        (user) => user.ID === response.data.ID
      );
      this.users.splice(index, 1, response.data);
      this.editingUser = null;
    },
    async deleteUser(id) {
      await axios.delete(`/api/users/${id}`);
      this.users = this.users.filter((user) => user.ID !== id);
    },
    showCreateDialog() {
      this.createDialog = true;
    },
  },
  created() {
    this.fetchUsers();
    this.fetchUzytkownicy();
  },
};
</script>
