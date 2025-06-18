<template>
  <div class="info-pack overflow-auto" style="max-height: 400px">
    <v-alert v-if="error" type="error" dense>{{ error }}</v-alert>

    <p v-if="message">{{ message }}</p>

    <ul v-if="pack">
      <li>
        <strong>Kto zapakował: </strong> {{ pack ? pack.Uzytkownik : "-" }}
      </li>
      <li>
        <strong>Data rozpoczęcia montażu: </strong>
        {{ formatDate(pack ? pack.Date : null) }}
      </li>
      <li>
        <strong>Data rozpoczęcia pakowania: </strong>
        {{ formatDate(pack ? pack.date_pack : null) }}
      </li>
    </ul>
    <PackProductList
      :products="productsOrder[0]?.products || []"
      :ttn="productsOrder.ttn"
      :showBtns="false"
      @change-counter="changeCounter"
      @delete-ttn="deleteTTN"
      @print-ttn="printTTN"
    />
  </div>
</template>

<script>
import axios from "axios";
import PackProductList from "../../manager/UI/PackProductList.vue";
export default {
  name: "InfoPack",
  components: {
    PackProductList,
  },
  props: {
    order: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      pack: null,
      message: "",
      error: null,
      productsOrder: [],
    };
  },
  mounted() {
    this.getPack();
  },
  methods: {
    changeCounter(product) {},
    deleteTTN(ttnNumber) {},
    printTTN(ttnNumber) {},
    getPack() {
      this.message = "";
      this.error = null;

      axios
        .get(`/api/getOrderPack/${this.order.IDOrder}`)
        .then((response) => {
          this.pack = response.data.pack;

          if (!this.pack) {
            this.message = "Zamówienie nie zostało spakowane";
          } else {
            this.getOrderPackProducts(this.order.IDOrder);
          }
        })
        .catch((error) => {
          this.error = "Błąd podczas pobierania informacji o przesyłce";
          console.error("Błąd podczas odbierania przesyłki:", error);
        });
    },
    getOrderPackProducts(id) {
      this.message = "";
      this.error = "";
      this.loading = true;

      axios
        .post("/api/getOrderPackProducts/" + id, { showInOrder: true })
        .then((response) => {
          if (response.data.status == "error") {
            this.error = response.data.message;
          } else {
            this.productsOrder = response.data;
          }
          this.loading = false;
        })
        .catch((error) => {
          console.log(error);
          this.loading = false;
        });
    },
    formatDate(dateStr) {
      if (!dateStr) return "-";
      const date = new Date(dateStr);
      return date.toLocaleString("ru-RU");
    },
  },
};
</script>

<style scoped>
.info-pack {
  border: 1px solid #eee;
  padding: 16px;
  border-radius: 8px;
}

.info-pack ul {
  list-style: none;
  padding: 0;
}

.info-pack li {
  margin-bottom: 8px;
}
</style>
