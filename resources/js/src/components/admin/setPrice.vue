<template>
  <div>
    <v-snackbar v-model="snackbar" timeout="6000" location="top">
      {{ message }}

      <template v-slot:actions>
        <v-btn color="pink" variant="text" @click="snackbar = false">
          Close
        </v-btn>
      </template>
    </v-snackbar>
    <v-btn @click="setCenaWZkFromWZ">Set price WZk from WZ</v-btn>
    <v-btn @click="setCenaZLfromPZ">Set price ZL from PZ</v-btn>
  </div>
</template>

<script>
import axios from "axios";
export default {
  name: "FulstorSetPrice",

  data() {
    return {
      snackbar: false,
      message: "",
    };
  },

  mounted() {},

  methods: {
    setCenaWZkFromWZ() {
      this.message = "";
      axios
        .get("/api/setCenaWZkFromWZ")
        .then((response) => {
          this.message = "Updated WZk docs: ";
          this.message += response.data.updatedRows;
          console.log("documents:");
          console.log(response.data.documents);
          console.log("Prices:");
          console.log(response.data.prices);
          this.snackbar = true;
        })
        .catch((error) => {
          console.log(error);
        });
    },
    setCenaZLfromPZ() {
      this.message = "";
      axios
        .get("/api/setCenaZLfromPZ")
        .then((response) => {
          this.message = "Updated ZL docs: ";
          this.message += response.data.updatedRows;

          this.snackbar = true;
        })
        .catch((error) => {
          console.log(error);
        });
    },
  },
};
</script>
