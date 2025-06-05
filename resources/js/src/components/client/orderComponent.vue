<template>
  <v-dialog v-model="localDialog" max-width="1600" persistent>
    <v-alert v-if="error" type="error" dense>{{ error }}</v-alert>
    <v-btn
      icon
      variant="text"
      @click="closeDialog"
      style="position: absolute; right: 16px; top: 16px; z-index: 2"
    >
      <v-icon>mdi-close</v-icon>
    </v-btn>

    <div class="pa-4">
      <template v-if="loading">
        <v-skeleton-loader type="article" />
      </template>
      <v-card v-else-if="order" class="pa-0" color="#232b32" dark>
        <v-card-title>
          <span class="text-h6"
            >Zamawianie
            {{
              order.Number + " (" + parseInt(order._OrdersTempDecimal2) + ")" ||
              "..."
            }}
          </span>
          <v-spacer />
          <v-btn
            icon
            variant="text"
            @click="closeDialog"
            style="position: absolute; right: 16px; top: 16px; z-index: 1"
          >
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </v-card-title>
        <v-card-text>
          <v-tabs v-model="tab" background-color="primary" dark>
            <v-tab value="order">Zamawianie</v-tab>
            <v-tab value="products">Produkty</v-tab>
            <v-tab value="pack">Opakowanie</v-tab>
          </v-tabs>
          <v-tabs-window v-model="tab">
            <v-tabs-window-item value="order">
              <!-- Top section -->
              <v-row v-if="order">
                <v-col cols="12" md="7">
                  <div class="d-flex align-center mb-2">
                    <span class="me-2">Zapłacono:</span>
                    <v-chip color="red" class="me-2" label
                      >{{ delivery.payment_done || "..." }}
                      {{ delivery.currency || "" }}</v-chip
                    >
                    <span class="me-2"
                      >z {{ sumDoc }} {{ delivery.currency || "" }}</span
                    >
                    <v-btn icon size="small" class="me-2">
                      <v-icon>mdi-cached</v-icon>
                    </v-btn>
                    <!-- <v-btn size="small" variant="outlined" prepend-icon="mdi-pencil"
                >Edytuj wpłatę</v-btn
              > -->
                  </div>
                  <v-row>
                    <v-col cols="6">
                      <div>
                        <b>client (login):</b>
                        {{ order._OrdersTempString9 || client.Nazwa || "..." }}
                      </div>
                      <div><b>E-mail:</b> {{ client.Email || "..." }}</div>
                      <div><b>Telefon:</b> {{ client.Telefon || "..." }}</div>
                      <div>
                        <b>Źródło:</b> {{ client._OrdersTempString7 || "" }}
                      </div>
                    </v-col>
                    <v-col cols="6">
                      <div>
                        <b>Sposób wysyłki:</b>
                        {{ delivery.delivery_method || "..." }}
                      </div>
                      <!-- <div><b>Koszt wysyłki:</b> ??? PLN</div> -->
                      <div>
                        <b>Sposób płatności:</b>
                        {{ delivery.payment_method || "..." }}
                      </div>
                    </v-col>
                  </v-row>
                  <v-row>
                    <v-col cols="4">
                      <div><b>Pole dodatkowe 1</b><br />...</div>
                      <div><b>Pole dodatkowe 2</b><br />...</div>
                      <div><b>Stan</b><br />...</div>
                    </v-col>
                    <v-col cols="8"
                      ><b>Uwagi</b><br />{{ order.Remarks || "" }}</v-col
                    >
                  </v-row>
                </v-col>
                <v-col cols="12" md="5">
                  <div class="d-flex align-center mb-2">
                    <b
                      >Status:
                      {{
                        statuses.find((s) => s.value == order.IDOrderStatus)
                          .title || ""
                      }}</b
                    >
                    <v-select
                      class="ms-2"
                      density="compact"
                      hide-details
                      style="max-width: 180px"
                      :items="statuses"
                      v-model="status"
                      variant="outlined"
                      color="primary"
                    />
                  </div>

                  <div class="mb-2">
                    <b>Faktura: {{ order._OrdersTempString1 || "" }}</b>
                    <!-- <v-btn size="small" class="ms-2" variant="outlined"
                >WYSTAW FAKTURĘ</v-btn
              > -->
                    <!-- <v-btn size="small" class="ms-2" variant="outlined"
                >PRO FORMA</v-btn
              > -->
                  </div>
                  <div class="mb-2">
                    <b>Data złożenia:</b> {{ order.Created || "" }}
                  </div>
                  <div class="mb-2">
                    <b>Data WZ:</b> {{ wz.Data || "" }}
                    {{ wz.NrDokumentu || "" }}
                  </div>
                  <!-- <div class="mb-2">
              <b>Stany magazynowe: ???</b>
              <span class="text-success ms-2">✔ Zrealizowane (ściągnięte)</span>
            </div> -->
                  <!-- <div class="mb-2">
              <v-btn variant="text" color="info" size="small"
                >???? Więcej informacji o zamówieniu</v-btn
              >
            </div> -->
                </v-col>
              </v-row>
              <v-row v-else>
                <v-col>
                  <v-skeleton-loader type="article" />
                </v-col>
              </v-row>
              <!-- Bottom section -->
              <v-row class="mt-2">
                <v-col cols="12" md="4">
                  <v-card class="pa-3" variant="outlined">
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-subtitle-1">Adres dostawy</span>
                      <v-btn icon size="small" variant="text"
                        ><v-icon>mdi-pencil</v-icon></v-btn
                      >
                    </div>
                    <div>
                      <b>Imię i nazwisko:</b>
                      {{ delivery.delivery_fullname || "..." }}
                    </div>
                    <div>
                      <b>Firma:</b> {{ delivery.delivery_company || "..." }}
                    </div>
                    <div>
                      <b>Adres:</b> {{ delivery.delivery_address || "..." }}
                    </div>
                    <div>
                      <b>Kod i miasto:</b>
                      {{ delivery.delivery_postcode || "..." }}
                      {{ delivery.delivery_city || "..." }}
                    </div>
                    <div>
                      <b>Województwo:</b> {{ delivery.delivery_state || "..." }}
                    </div>
                    <div>
                      <b>Kraj:</b> {{ delivery.delivery_country || "..." }}
                    </div>
                  </v-card>
                </v-col>
                <v-col cols="12" md="4">
                  <v-card class="pa-3" variant="outlined">
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-subtitle-1">Dane do faktury</span>
                      <v-btn icon size="small" variant="text"
                        ><v-icon>mdi-pencil</v-icon></v-btn
                      >
                    </div>
                    <div>
                      <b>Imię i nazwisko:</b>
                      {{ delivery.invoice_fullname || "..." }}
                    </div>
                    <div>
                      <b>Firma:</b> {{ delivery.invoice_company || "..." }}
                    </div>
                    <div>
                      <b>Adres:</b> {{ delivery.invoice_address || "..." }}
                    </div>
                    <div>
                      <b>Kod i miasto:</b>
                      {{ delivery.invoice_postcode || "..." }}
                      {{ delivery.invoice_city || "" }}
                    </div>
                    <div><b>NIP:</b> {{ delivery.invoice_nip || "" }}</div>
                    <div>
                      <b>Kraj:</b> {{ delivery.invoice_country || "..." }}
                    </div>
                  </v-card>
                </v-col>
                <v-col cols="12" md="4">
                  <v-card class="pa-3" variant="outlined">
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-subtitle-1">Odbiór w punkcie</span>
                      <v-btn icon size="small" variant="text"
                        ><v-icon>mdi-pencil</v-icon></v-btn
                      >
                    </div>
                    <div>
                      <b>Nazwa:</b> {{ delivery.delivery_point_name || "..." }}
                    </div>
                    <div><b>ID:</b> {{ delivery.delivery_point_id }}</div>
                    <div>
                      <b>Adres:</b> {{ delivery.delivery_point_address || "" }}
                    </div>
                    <div>
                      <b>Kod i miasto:</b>
                      {{ delivery.delivery_point_postcode || "" }},
                      {{ delivery.delivery_point_city || "" }}
                    </div>
                  </v-card>
                </v-col>
              </v-row>
            </v-tabs-window-item>
            <v-tabs-window-item value="products">
              <div class="pa-4">
                <v-row class="product_line border my-0">
                  <v-col cols="6">
                    <div class="d-flex">
                      <span> Tytuł </span>
                    </div>
                  </v-col>

                  <v-col cols="2">
                    <div class="d-flex justify-start">
                      <div class="text-center">Ilosc</div>
                    </div>
                  </v-col>
                  <v-col cols="2">
                    <div class="d-flex justify-start">
                      <div class="text-center">PriceGross</div>
                    </div>
                  </v-col>
                </v-row>
                <v-row
                  class="product_line border my-0"
                  v-for="(product, index) in products"
                  :key="product.IDTowaru"
                >
                  <v-col cols="6">
                    <div class="d-flex">
                      {{ index + 1 }}.
                      <img
                        v-if="product.img"
                        :src="'data:image/jpeg;base64,' + product.img"
                        alt="pic"
                        style="height: 3em"
                      />
                      <span>
                        {{ product.Nazwa }}
                        <div v-if="product.Usluga == '0'">
                          cod: {{ product.KodKreskowy }}, sku: {{ product.sku }}
                        </div>
                      </span>
                    </div>
                  </v-col>

                  <v-col cols="2">
                    <div class="d-flex justify-start">
                      <div :id="product.IDTowaru" class="text-right">
                        {{ product.ilosc || "0" }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="2">
                    <div class="d-flex justify-start">
                      <div class="text-right">
                        {{ product.PriceGross || "0.00" }}
                      </div>
                    </div>
                  </v-col>
                </v-row>
              </div>
            </v-tabs-window-item>
            <v-tabs-window-item value="pack">
              <div class="pa-4">
                <InfoPack :order="order" />
              </div>
            </v-tabs-window-item>
          </v-tabs-window>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn color="primary" @click="closeDialog">Zamknij</v-btn>
        </v-card-actions>
      </v-card>
    </div>
  </v-dialog>
</template>

<script>
import axios from "axios";
import InfoPack from "./UI/infoPack.vue";
export default {
  name: "FulstorOrderComponent",
  components: {
    InfoPack,
  },
  props: {
    dialog: Boolean,
    orderId: [Number, String],
    orderWarehouse: [Number, String],
  },

  data() {
    return {
      localDialog: this.dialog,
      order: null,
      status: null,
      statuses: [],
      client: null,
      delivery: {},
      products: [],
      loading: false,
      error: null,
      tab: "order",
      sumDoc: 0,
      wz: { Data: "", NrDokumentu: "" },
    };
  },
  mounted() {
    if (this.dialog && this.orderId) {
      this.loadData();
    }
  },
  watch: {
    dialog(val) {
      console.log("Dialog changed:", val);
      console.log("Order ID:", this.orderId);
      this.localDialog = val;
      if (val && this.orderId) {
        this.loadData();
      }
    },
    orderId(val) {
      console.log("Order ID changed:", val);
      if (this.dialog && val) {
        this.loadData();
      }
    },
    localDialog(val) {
      this.$emit("update:dialog", val);
    },
  },

  methods: {
    closeDialog() {
      this.localDialog = false;
      this.$emit("update:dialog", false);
    },

    async loadData() {
      this.loading = true;
      this.error = null;
      this.order = null;
      this.client = null;

      try {
        let params = {
          IDWarehouse: this.orderWarehouse,
          IDOrder: this.orderId,
        };
        const orderRes = await axios.post("/api/getOrder", params);

        this.order = orderRes.data.order;
        this.wz = orderRes.data.wz || { Data: "", NrDokumentu: "" };
        this.delivery = orderRes.data.delivery;
        this.client = orderRes.data.client;
        this.statuses = orderRes.data.statuses || [];
        this.products = orderRes.data.products || [];
        this.sumDoc = this.products.reduce((acc, product) => {
          return acc + (product.PriceGross || 0) * (product.ilosc || 0);
        }, 0);
        // if (!this.order || !this.delivery) {
        //   this.localDialog = false;
        // }
      } catch (err) {
        console.error("Błąd podczas pobierania:", err);
        this.error = "Błąd ładowania danych: " + (err.message || err);
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>


<style scoped>
.text-success {
  color: #4caf50 !important;
}
</style>
