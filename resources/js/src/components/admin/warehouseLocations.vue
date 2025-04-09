<template>
  <v-container>
    <v-row>
      <v-progress-linear
        :active="loading"
        indeterminate
        color="purple"
      ></v-progress-linear>
    </v-row>
    <v-row>
      <v-col cols="12" md="2" lg="2">
        <v-select
          label="Magazyn"
          v-model="IDWarehouse"
          :items="warehouses"
          item-title="Nazwa"
          item-value="IDMagazynu"
          hide-details="auto"
          @update:modelValue="getWarehouseLocations()"
        ></v-select>
      </v-col>
      <v-col cols="12" md="2" lg="2" v-if="IDWarehouse">
        <v-select
          label="Typ Lokalizacji"
          v-model="filterTypLocation"
          :items="[
            { IDTypLocations: 0, Opis: '--wszystkie' },
            { IDTypLocations: null, Opis: '--pusty' },
            ...locationsTypOptions,
          ]"
          @change="filterLocations"
          item-title="Opis"
          item-value="IDTypLocations"
          hide-details
        ></v-select>
      </v-col>
      <v-col cols="12" md="2" lg="2" v-if="IDWarehouse">
        <v-switch
          v-model="filterIsArchive"
          label="Pokaż zarchiwizowane"
          inset
          hide-details
          @change="filterLocations"
        ></v-switch>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12" md="12" lg="12" v-if="filterLocations.length > 0">
        <v-data-table
          :headers="headers"
          :items="filterLocations"
          item-value="IDWarehouseLocation"
          :search="search"
          show-select
          v-model="selected"
          class="elevation-1"
        >
          <template v-slot:top>
            <div class="top_actions">
              <v-text-field
                v-model="search"
                label="Search"
                variant="outlined"
                hide-details
                single-line
                clearable
                style="max-width: 200px"
              ></v-text-field>
              <v-select
                label="Location Type"
                v-model="IDTypLocation"
                :items="locationsTypOptions"
                item-title="Opis"
                item-value="IDTypLocations"
                dense
                hide-details
                style="max-width: 200px"
                clearable
              ></v-select>
              <v-select
                label="M3 Locations"
                v-model="IDLocationsM3"
                :items="locationsM3Options"
                item-title="Opis"
                item-value="IDLocationsM3"
                dense
                hide-details
                style="max-width: 200px"
                clearable
              ></v-select>
              <v-text-field
                label="Priority"
                v-model="Priority"
                dense
                hide-details
                style="max-width: 200px"
              ></v-text-field>
              <v-switch
                v-model="IsArchive"
                label="Archive Status"
                inset
                hide-details
                @change="toggleArchiveStatus"
              ></v-switch>
              <v-btn
                size="x-large"
                color="primary"
                @click="bulkUpdateLocationsTyp"
                :disabled="selected.length === 0"
                style="max-width: 300px"
              >
                {{ selected.length }} Update LocationsTyp
              </v-btn>
            </div>
          </template>
          <template v-slot:item.TypLocations="{ item }">
            {{
              locationsTypOptions.find(
                (loc) => loc.IDTypLocations === item.TypLocations
              )?.Opis
            }}
          </template>

          <template v-slot:item.M3Locations="{ item }">
            {{
              locationsM3Options.find(
                (loc) => loc.IDLocationsM3 === item.M3Locations
              )?.Opis || "N/A"
            }}
          </template>
        </v-data-table>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import axios from "axios";
export default {
  name: "warehouseLocations",
  data() {
    return {
      loading: false,
      IDWarehouse: null,
      warehouses: [],
      locations: [],
      IDTypLocation: null,
      IDLocationsM3: null,
      IsArchive: null,
      locationsTypOptions: [],
      locationsM3Options: [],
      filterIsArchive: false,
      filterTypLocation: null,
      Priority: null,
      headers: [
        { title: "Location Code", key: "LocationCode" },
        { title: "Location Name", key: "LocationName" },
        { title: "Typ Locations", key: "TypLocations" },
        { title: "Priority", key: "Priority" },
        { title: "Is Archive", key: "IsArchive" },
        { title: "M3 Locations", key: "M3Locations" },
      ],
      search: "",
      selected: [],
    };
  },
  computed: {
    filterLocations() {
      return this.locations.filter((location) => {
        return (
          location.IsArchive == this.filterIsArchive &&
          (this.filterTypLocation > 0 || this.filterTypLocation == null
            ? location.TypLocations == this.filterTypLocation
            : true)
        );
      });
    },
  },

  mounted() {
    this.getWarehouse();
    this.getLocationsTyp();
    this.getLocationsM3();
  },
  methods: {
    clear() {
      //   this.IDWarehouse = null;
      this.IDTypLocation = null;
      this.IDLocationsM3 = null;
      this.Priority = null;
      this.filterIsArchive = false;
    },
    getWarehouse() {
      const vm = this;
      vm.locations = [];
      axios
        .get("/api/getWarehouse")
        .then((res) => {
          if (res.status == 200) {
            vm.warehouses = res.data;
          }
        })
        .catch((error) => console.log(error));
    },
    getLocationsTyp() {
      axios
        .get("/api/getLocationsTyp")
        .then((res) => {
          if (res.status === 200) {
            this.locationsTypOptions = res.data;
          }
        })
        .catch((error) => console.log(error));
    },
    getLocationsM3() {
      axios
        .get("/api/getLocationsM3")
        .then((res) => {
          if (res.status === 200) {
            this.locationsM3Options = res.data;
          }
        })
        .catch((error) => console.log(error));
    },
    getWarehouseLocations() {
      this.loading = true;
      axios
        .get("/api/getWarehouseLocations/" + this.IDWarehouse)
        .then((res) => {
          if (res.status === 200) {
            this.locations = res.data;
            this.loading = false;
          }
        })
        .catch((error) => console.log(error));
    },
    bulkUpdateLocationsTyp() {
      let data = {};
      data.IDWarehouseLocation = this.selected;
      if (this.IDTypLocation) {
        data.TypLocations = this.IDTypLocation;
      }
      if (this.IDLocationsM3) {
        data.M3Locations = this.IDLocationsM3;
      }
      if (this.Priority) {
        data.Priority = this.Priority;
      }
      data.IsArchive = this.IsArchive;

      axios
        .post("/api/updateLocationsTyp", data)
        .then(() => {
          this.selected = [];
          this.clear();

          this.getWarehouseLocations();
        })
        .catch((error) => console.log(error));
    },
  },
};
</script>
<style>
.top_actions {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}
@media (max-width: 600px) {
  .top_actions {
    flex-direction: column;
  }
}
</style>
