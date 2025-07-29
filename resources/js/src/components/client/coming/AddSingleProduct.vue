<template>
  <div>
    <v-snackbar
      v-model="snackbar"
      top
      right
      timeout="5000"
      :color="snackbarColor"
    >
      <v-card-text>
        {{ message }}
      </v-card-text>
      <template v-slot:action="{ attrs }">
        <v-btn color="white" text v-bind="attrs" @click="snackbar = false">
          X
        </v-btn>
      </template>
    </v-snackbar>

    <v-form ref="form" v-model="valid">
      <v-container>
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="product.Nazwa"
              label="Nazwa towaru *"
              required
              :rules="nameRules"
              outlined
              dense
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="product.EAN"
              label="Kod EAN *"
              required
              :rules="eanRules"
              outlined
              dense
            ></v-text-field>
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12" md="6">
            <v-select
              v-model="product.IDGrupyTowarowej"
              label="Grupa towarowa *"
              :items="productGroups"
              item-title="Nazwa"
              item-value="IDGrupyTowarowej"
              required
              :rules="groupRules"
              outlined
              dense
            ></v-select>
          </v-col>
          <v-col cols="12" md="6">
            <v-select
              v-model="product.jednostka"
              label="Jednostka *"
              :items="units"
              item-title="Nazwa"
              item-value="Nazwa"
              required
              :rules="unitRules"
              outlined
              dense
            ></v-select>
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="product.ilosc"
              label="Ilość *"
              type="number"
              step="0.01"
              min="0"
              required
              :rules="quantityRules"
              outlined
              dense
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="product.m3"
              label="Objętość (m³)"
              type="number"
              step="0.001"
              min="0"
              outlined
              dense
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="product.cena"
              label="Cena jednostkowa"
              type="number"
              step="0.01"
              min="0"
              outlined
              dense
            ></v-text-field>
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12">
            <v-textarea
              v-model="product.uwagi"
              label="Uwagi"
              outlined
              dense
              rows="3"
            ></v-textarea>
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12" class="text-center">
            <v-btn
              @click="addProduct"
              :disabled="!valid || loading"
              :loading="loading"
              color="success"
              large
            >
              <v-icon left>mdi-plus</v-icon>
              Dodaj towar do bazy
            </v-btn>
            <v-btn @click="clearForm" class="ml-3" outlined>
              <v-icon left>mdi-refresh</v-icon>
              Wyczyść
            </v-btn>
          </v-col>
        </v-row>
      </v-container>
    </v-form>
  </div>
</template>

<script>
import axios from "axios";

export default {
  name: "AddSingleProduct",
  props: ["IDWarehouse"],

  data() {
    return {
      valid: false,
      loading: false,
      snackbar: false,
      snackbarColor: "success",
      message: "",

      product: {
        Nazwa: "",
        EAN: "",
        IDGrupyTowarowej: null,
        jednostka: "",
        ilosc: "",
        m3: "",
        cena: "",
        uwagi: "",
      },

      productGroups: [],
      units: [],

      nameRules: [
        (v) => !!v || "Nazwa jest wymagana",
        (v) => (v && v.length >= 2) || "Nazwa musi mieć co najmniej 2 znaki",
      ],
      eanRules: [
        (v) => !!v || "Kod EAN jest wymagany",
        (v) => (v && v.length >= 8) || "Kod EAN musi mieć co najmniej 8 znaków",
      ],
      groupRules: [(v) => !!v || "Grupa towarowa jest wymagana"],
      unitRules: [(v) => !!v || "Jednostka jest wymagana"],
      quantityRules: [
        (v) => !!v || "Ilość jest wymagana",
        (v) =>
          (!isNaN(parseFloat(v)) && parseFloat(v) > 0) ||
          "Ilość musi być liczbą większą od 0",
      ],
    };
  },

  mounted() {
    this.loadProductGroups();
    this.loadUnits();
  },

  methods: {
    async loadProductGroups() {
      try {
        const response = await axios.get("/api/getProductGroups");
        if (response.data.status === "success") {
          this.productGroups = response.data.groups;
        }
      } catch (error) {
        console.error("Error loading product groups:", error);
        this.showMessage("Błąd podczas ładowania grup towarowych", "error");
      }
    },

    async loadUnits() {
      try {
        const response = await axios.get("/api/getUnits");
        if (response.data.status === "success") {
          this.units = response.data.units;
        }
      } catch (error) {
        console.error("Error loading units:", error);
        this.showMessage("Błąd podczas ładowania jednostek", "error");
      }
    },

    async addProduct() {
      if (!this.$refs.form.validate()) {
        this.showMessage("Proszę wypełnić wszystkie wymagane pola", "error");
        return;
      }

      this.loading = true;

      try {
        // Prepare data for adding product
        const productData = {
          Nazwa: this.product.Nazwa.trim(),
          EAN: this.product.EAN.trim(),
          IDGrupyTowarowej: this.product.IDGrupyTowarowej,
          jednostka: this.product.jednostka,
          ilosc: parseFloat(this.product.ilosc),
          m3: this.product.m3 ? parseFloat(this.product.m3) : null,
          cena: this.product.cena ? parseFloat(this.product.cena) : null,
          uwagi: this.product.uwagi ? this.product.uwagi.trim() : "",
        };

        console.log("Adding product:", productData);

        const response = await axios.post("/api/addProductToDatabase", {
          IDWarehouse: this.IDWarehouse,
          product: productData,
        });

        if (response.data.status === "success") {
          this.showMessage(
            "Towar został dodany pomyślnie do bazy danych",
            "success"
          );
          this.clearForm();
          this.$emit("product-added", response.data);
        } else {
          this.showMessage(
            response.data.message || "Błąd podczas dodawania towaru",
            "error"
          );
        }
      } catch (error) {
        console.error("Product addition error:", error);
        this.showMessage("Błąd podczas dodawania towaru do bazy", "error");
      } finally {
        this.loading = false;
      }
    },

    clearForm() {
      this.product = {
        Nazwa: "",
        EAN: "",
        IDGrupyTowarowej: null,
        jednostka: "",
        ilosc: "",
        m3: "",
        cena: "",
        uwagi: "",
      };
      this.$refs.form.resetValidation();
    },

    showMessage(msg, color = "success") {
      this.message = msg;
      this.snackbarColor = color;
      this.snackbar = true;
    },
  },
};
</script>

<style scoped>
.v-card {
  margin-bottom: 16px;
}
</style>
