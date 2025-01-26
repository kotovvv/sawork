<template>
  <v-container class="container align-center px-1">
    <v-col cols="12">
      <v-btn
        @click="dialog = !dialog"
        color="surface-variant"
        text="Nowość"
        variant="flat"
      ></v-btn>
    </v-col>
    <v-card>
      <v-data-table
        :headers="name_headers"
        :items="magazyns"
        mobile-breakpoint="800"
        class="elevation-0"
      >
        <template v-slot:item.actions="{ item }">
          <div class="text-truncate">
            <v-icon
              small
              class="mr-2"
              @click="showEditDialog(item)"
              color="primary"
            >
              mdi-pencil
            </v-icon>
            <v-icon small @click="deleteMagEmail(item)" color="pink">
              mdi-delete
            </v-icon>
          </div>
        </template>
      </v-data-table>

      <!-- this dialog is used for both create and update -->
      <v-dialog max-width="600" v-model="dialog">
        <template v-slot:default="{ isActive }">
          <v-card>
            <v-card-title>
              <span v-if="editedItem.id">Edytuj {{ editedItem.name }}</span>
              <span v-else>Create</span>
            </v-card-title>
            <v-card-text>
              <v-row>
                <v-col>
                  <v-select
                    v-model="editedItem.IDMagazynu"
                    :items="warehouses"
                    item-title="Nazwa"
                    item-value="IDMagazynu"
                    label="Nazwa"
                    persistent-hint
                    single-line
                  ></v-select>
                </v-col>
                <v-col cols="12">
                  <v-text-field
                    v-model="editedItem.eMailAddress"
                    label="eMailAddress"
                  ></v-text-field>
                </v-col>

                <v-col cols="12">
                  <v-select
                    v-model="editedItem.cod"
                    :items="cod"
                    label="Cod"
                    persistent-hint
                    single-line
                  ></v-select>
                </v-col>
                <v-col cols="4">
                  <v-text-field
                    v-model="editedItem.Zwrot"
                    label="Zwrot"
                  ></v-text-field>
                </v-col>
                <v-col cols="4">
                  <v-text-field
                    v-model="editedItem.Zniszczony"
                    label="Zniszczony"
                  ></v-text-field>
                </v-col>
                <v-col cols="4">
                  <v-text-field
                    v-model="editedItem.Naprawa"
                    label="Naprawa"
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-text-field
                    v-model="editedItem.IDKontrahenta"
                    label="IDKontrahenta"
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-autocomplete
                    v-model="editedItem.noklient"
                    clearable
                    label="No client"
                    :items="noclients"
                    multiple
                  ></v-autocomplete>
                </v-col>
              </v-row>
            </v-card-text>

            <v-card-actions>
              <v-spacer></v-spacer>
              <v-btn
                color="blue darken-1"
                text
                @click="
                  isActive.value = false;
                  editedItem = {};
                "
                >Anuluj</v-btn
              >
              <v-btn
                color="blue darken-1"
                text
                @click="saveMagEmail(editedItem)"
                >Zapisz</v-btn
              >
            </v-card-actions>
          </v-card>
        </template>
      </v-dialog>
    </v-card>
  </v-container>
</template>

<script>
import axios from "axios";
export default {
  name: "MagazynEmail",

  data() {
    return {
      magazyns: [],
      name_headers: [
        { title: "IDMagazyn", value: "IDMagazynu" },
        { title: "Zwrot", value: "Zwrot" },
        { title: "Zniszczony", value: "Zniszczony" },
        { title: "Naprawa", value: "Naprawa" },
        { title: "Magazyn", value: "Nazwa" },
        { title: "Details", value: "eMailAddress" },
        { title: "IDKontrahenta", value: "IDKontrahenta" },
        { title: "Dokument Cod", value: "cod", name: "cod", width: "180" },
        { title: "No client", value: "noklient", nowrap: true },
        { title: "Action", value: "actions", sortable: false },
      ],
      magEmail: [],
      dialog: false,
      editedItem: {},
      warehouses: [],
      cod: ["WZk"],
      noclients: [],
    };
  },
  mounted() {
    this.loadMagEmail();
    this.getWarehouse();
    this.getClients();
  },
  methods: {
    getClients() {
      const vm = this;
      axios.get("/api/getClients").then((res) => {
        if (res.status == 200) {
          vm.noclients = res.data;
        }
      });
    },
    loadMagEmail() {
      const self = this;
      axios
        .get("/api/loadMagEmail")
        .then((response) => {
          self.magazyns = response.data;
          self.magazyns.forEach((element) => {
            element.noklient = element.noklient
              ? JSON.parse(element.noklient)
              : [];
          });
        })
        .catch((error) => {
          console.log(error);
        });
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
    showEditDialog(item) {
      this.editedItem = item || {};
      this.dialog = !this.dialog;
    },

    saveMagEmail(item) {
      const self = this;
      let data = {};
      data.id = item.ID;
      data.IDMagazynu = item.IDMagazynu;
      data.eMailAddress = item.eMailAddress;
      data.cod = item.cod;
      data.Zwrot = item.Zwrot;
      data.Zniszczony = item.Zniszczony;
      data.Naprawa = item.Naprawa;
      data.IDKontrahenta = item.IDKontrahenta;
      data.noklient = item.noklient;

      // save the record
      axios
        .post("api/saveMagEmail", data, {
          // headers: {
          // 	'Authorization': 'Bearer ' + apiToken,
          // 	'Content-Type': 'application/json',
          // },
        })
        .then((response) => {
          if (response.data > 0) {
            self.editedItem.Nazwa = self.warehouses.find((f) => {
              return f.IDMagazynu == self.editedItem.IDMagazynu;
            }).Nazwa;
            self.magazyns.push(self.editedItem);
          } else {
            self.editedItem.ID = response.data;
          }
          self.editedItem = {};
          self.dialog = !self.dialog;
        });
    },
    deleteMagEmail(item) {
      let data = {};
      data.ID = item.ID;

      let idx = this.magazyns.findIndex((i) => i.ID === item.ID);
      // save the record
      if (confirm("Czy na pewno chcesz to usunąć??")) {
        axios.post("api/deleteMagEmail", data, {}).then((response) => {
          this.magazyns.splice(idx, 1);
        });
      }
    },
  },
};
</script>
