<template>
  <v-container>
    <v-row>
      <!-- Lista rodzajów transportu -->
      <v-col cols="6">
        <v-text-field v-model="search" label="Szukaj" class="mb-4" clearable />
        <v-data-table
          :items="rodzaje"
          :headers="[
            { text: 'Nazwa', value: 'Nazwa' },
            { text: 'Grupa', value: 'IDgroup', sortable: false },
          ]"
          :items-per-page="30"
          item-key="IDRodzajuTransportu"
          item-value="IDRodzajuTransportu"
          v-model="selected"
          :search="search"
          show-select
        >
          <template #item.IDgroup="{ item }">
            {{ getGroupName(item.IDgroup) || "Brak przypisania" }}
          </template>
        </v-data-table>
      </v-col>

      <!-- Lista grup z radio -->
      <v-col cols="6">
        <v-card>
          <v-card-title>Przypisz grupę</v-card-title>
          <v-card-actions v-if="selected">
            <v-btn color="primary" @click="assignGroup">Zapisz</v-btn>
          </v-card-actions>
          <v-card-text v-else>
            <span>Wybierz rodzaj transportu po lewej stronie</span>
          </v-card-text>
          <v-card-text v-if="selected">
            <v-radio-group
              v-model="selectedGroup"
              max-height="80vh"
              style="max-height: 80vh; overflow: auto"
            >
              <v-radio label="Brak grupy" :value="null" />
              <v-radio
                v-for="group in groups"
                :key="group.id"
                :label="group.name"
                :value="group.id"
              />
            </v-radio-group>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import axios from "axios";
export default {
  data() {
    return {
      rodzaje: [], // lista rodzajów transportu
      groups: [], // lista grup
      selected: [],
      selectedGroup: null,
      search: "", // search input for filtering
    };
  },
  methods: {
    getRodzajTransportu() {
      // Pobierz rodzaje transportu z serwera
      axios.get("/api/getRodzajTransportu").then((res) => {
        this.rodzaje = res.data;
        this.groups = res.data.map((item) => ({
          id: item.IDRodzajuTransportu,
          name: item.Nazwa,
        }));
      });
    },
    assignGroup() {
      if (!this.selected) return;
      // Wysyłka żądania do serwera w celu aktualizacji IDgroup
      axios
        .post("/api/setRodzajTransportu", {
          group: this.selected,
          IDgroup: this.selectedGroup,
        })
        .then(() => {
          this.selected = []; // Reset selected after assignment
          this.selectedGroup = null; // Reset selected group after assignment
          this.getRodzajTransportu();
        });
    },
    getGroupName(id) {
      const group = this.groups.find((g) => g.id === id);
      return group ? group.name : null;
    },
  },
  mounted() {
    this.getRodzajTransportu();
  },
  //   computed: {
  //     filteredRodzaje() {
  //       if (!this.search) return this.rodzaje;
  //       const searchLower = this.search.toLowerCase();
  //       return this.rodzaje.filter(
  //         (item) =>
  //           (item.Nazwa && item.Nazwa.toLowerCase().includes(searchLower)) ||
  //           (item.IDgroup &&
  //             String(item.IDgroup).toLowerCase().includes(searchLower))
  //       );
  //     },
  //   },
};
</script>
