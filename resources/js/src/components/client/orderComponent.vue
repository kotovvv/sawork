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
                    <!-- <v-btn icon size="small" class="me-2">
                            <v-icon>mdi-cached</v-icon>
                            </v-btn> -->
                    <!-- <v-btn size="small" variant="outlined" prepend-icon="mdi-pencil"
                        >Edytuj wpłatę</v-btn
                    > -->
                  </div>

                  <!-- Main data edit/view -->
                  <v-card class="pa-3 mb-3" variant="outlined">
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-subtitle-1">Dane podstawowe</span>
                      <v-btn
                        v-if="!editingMainData"
                        icon
                        size="small"
                        variant="text"
                        @click="startEdit('maindata')"
                      >
                        <v-icon>mdi-pencil</v-icon>
                      </v-btn>
                    </div>

                    <!-- View mode -->
                    <template v-if="!editingMainData">
                      <v-row>
                        <v-col cols="6">
                          <div>
                            <b>client (login):</b>
                            {{
                              order._OrdersTempString9 || client.Nazwa || "..."
                            }}
                          </div>
                          <div><b>E-mail:</b> {{ client.Email || "..." }}</div>
                          <div>
                            <b>Telefon:</b> {{ client.Telefon || "..." }}
                          </div>
                          <div>
                            <b>Źródło:</b> {{ order._OrdersTempString7 || "" }}
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
                          <div>
                            <b>Status:</b>
                            {{
                              statuses.find(
                                (s) => s.value == order.IDOrderStatus
                              )?.title || ""
                            }}
                          </div>
                        </v-col>
                      </v-row>
                      <v-row>
                        <v-col cols="12">
                          <div><b>Uwagi:</b> {{ order.Remarks || "" }}</div>
                        </v-col>
                      </v-row>
                    </template>

                    <!-- Edit mode -->
                    <template v-else>
                      <v-row>
                        <v-col cols="6">
                          <v-text-field
                            v-model="editMainData.email"
                            label="E-mail"
                            density="compact"
                            variant="outlined"
                            class="mb-2"
                          />
                          <v-text-field
                            v-model="editMainData.telefon"
                            label="Telefon"
                            density="compact"
                            variant="outlined"
                            class="mb-2"
                          />
                          <v-text-field
                            v-model="editMainData.zrodlo"
                            label="Źródło"
                            density="compact"
                            variant="outlined"
                            class="mb-2"
                          />
                        </v-col>
                        <v-col cols="6">
                          <v-text-field
                            v-model="editMainData.delivery_method"
                            label="Sposób wysyłki"
                            density="compact"
                            variant="outlined"
                            class="mb-2"
                          />
                          <v-text-field
                            v-model="editMainData.payment_method"
                            label="Sposób płatności"
                            density="compact"
                            variant="outlined"
                            class="mb-2"
                          />
                          <v-select
                            v-model="editMainData.status"
                            label="Status"
                            :items="statuses"
                            density="compact"
                            variant="outlined"
                            class="mb-2"
                          />
                        </v-col>
                      </v-row>
                      <v-row>
                        <v-col cols="12">
                          <v-textarea
                            v-model="editMainData.remarks"
                            label="Uwagi"
                            density="compact"
                            variant="outlined"
                            rows="3"
                            class="mb-3"
                          />
                        </v-col>
                      </v-row>

                      <div class="d-flex gap-2">
                        <v-btn
                          color="primary"
                          size="small"
                          @click="saveEdit('maindata')"
                        >
                          Zapisz
                        </v-btn>
                        <v-btn
                          color="grey"
                          size="small"
                          variant="outlined"
                          @click="cancelEdit('maindata')"
                        >
                          Anuluj
                        </v-btn>
                      </div>
                    </template>
                  </v-card>
                </v-col>
                <v-col cols="12" md="5">
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
                    <b>Data złożenia:</b>
                    {{ order.Created ? order.Created.substring(0, 16) : "" }}
                  </div>
                  <div class="mb-2">
                    <b>Data WZ:</b>
                    {{ wz.Data ? wz.Data.substring(0, 16) : "" }}
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
                  <div class="mb-2" v-if="order._OrdersTempString10">
                    <b>Numer Zwrotu:</b>
                    {{ order._OrdersTempString10 }}
                  </div>
                  <div class="mb-2" v-if="order._OrdersTempString4">
                    <b>Nr. Zwrotny BL:</b>
                    {{ order._OrdersTempString4 }}
                  </div>
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
                      <v-btn
                        v-if="!editingDelivery"
                        icon
                        size="small"
                        variant="text"
                        @click="startEdit('delivery')"
                      >
                        <v-icon>mdi-pencil</v-icon>
                      </v-btn>
                    </div>

                    <!-- View mode -->
                    <template v-if="!editingDelivery">
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
                        <b>Województwo:</b>
                        {{ delivery.delivery_state || "..." }}
                      </div>
                      <div>
                        <b>Kraj:</b> {{ delivery.delivery_country || "..." }}
                      </div>
                    </template>

                    <!-- Edit mode -->
                    <template v-else>
                      <v-text-field
                        v-model="editDeliveryData.delivery_fullname"
                        label="Imię i nazwisko"
                        density="compact"
                        variant="outlined"
                        class="mb-2"
                      />
                      <v-text-field
                        v-model="editDeliveryData.delivery_company"
                        label="Firma"
                        density="compact"
                        variant="outlined"
                        class="mb-2"
                      />
                      <v-text-field
                        v-model="editDeliveryData.delivery_address"
                        label="Adres"
                        density="compact"
                        variant="outlined"
                        class="mb-2"
                      />
                      <v-row class="mb-2">
                        <v-col cols="6">
                          <v-text-field
                            v-model="editDeliveryData.delivery_postcode"
                            label="Kod pocztowy"
                            density="compact"
                            variant="outlined"
                          />
                        </v-col>
                        <v-col cols="6">
                          <v-text-field
                            v-model="editDeliveryData.delivery_city"
                            label="Miasto"
                            density="compact"
                            variant="outlined"
                          />
                        </v-col>
                      </v-row>
                      <v-text-field
                        v-model="editDeliveryData.delivery_state"
                        label="Województwo"
                        density="compact"
                        variant="outlined"
                        class="mb-2"
                      />
                      <v-text-field
                        v-model="editDeliveryData.delivery_country"
                        label="Kraj"
                        density="compact"
                        variant="outlined"
                        class="mb-3"
                      />

                      <div class="d-flex gap-2">
                        <v-btn
                          color="primary"
                          size="small"
                          @click="saveEdit('delivery')"
                        >
                          Zapisz
                        </v-btn>
                        <v-btn
                          color="grey"
                          size="small"
                          variant="outlined"
                          @click="cancelEdit('delivery')"
                        >
                          Anuluj
                        </v-btn>
                      </div>
                    </template>
                  </v-card>
                </v-col>
                <v-col cols="12" md="4">
                  <v-card class="pa-3" variant="outlined">
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-subtitle-1">Dane do faktury</span>
                      <v-btn
                        v-if="!editingInvoice"
                        icon
                        size="small"
                        variant="text"
                        @click="startEdit('invoice')"
                      >
                        <v-icon>mdi-pencil</v-icon>
                      </v-btn>
                    </div>

                    <!-- View mode -->
                    <template v-if="!editingInvoice">
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
                    </template>

                    <!-- Edit mode -->
                    <template v-else>
                      <v-text-field
                        v-model="editInvoiceData.invoice_fullname"
                        label="Imię i nazwisko"
                        density="compact"
                        variant="outlined"
                        class="mb-2"
                      />
                      <v-text-field
                        v-model="editInvoiceData.invoice_company"
                        label="Firma"
                        density="compact"
                        variant="outlined"
                        class="mb-2"
                      />
                      <v-text-field
                        v-model="editInvoiceData.invoice_address"
                        label="Adres"
                        density="compact"
                        variant="outlined"
                        class="mb-2"
                      />
                      <v-row class="mb-2">
                        <v-col cols="6">
                          <v-text-field
                            v-model="editInvoiceData.invoice_postcode"
                            label="Kod pocztowy"
                            density="compact"
                            variant="outlined"
                          />
                        </v-col>
                        <v-col cols="6">
                          <v-text-field
                            v-model="editInvoiceData.invoice_city"
                            label="Miasto"
                            density="compact"
                            variant="outlined"
                          />
                        </v-col>
                      </v-row>
                      <v-text-field
                        v-model="editInvoiceData.invoice_nip"
                        label="NIP"
                        density="compact"
                        variant="outlined"
                        class="mb-2"
                      />
                      <v-text-field
                        v-model="editInvoiceData.invoice_country"
                        label="Kraj"
                        density="compact"
                        variant="outlined"
                        class="mb-3"
                      />

                      <div class="d-flex gap-2">
                        <v-btn
                          color="primary"
                          size="small"
                          @click="saveEdit('invoice')"
                        >
                          Zapisz
                        </v-btn>
                        <v-btn
                          color="grey"
                          size="small"
                          variant="outlined"
                          @click="cancelEdit('invoice')"
                        >
                          Anuluj
                        </v-btn>
                      </div>
                    </template>
                  </v-card>
                </v-col>
                <v-col cols="12" md="4">
                  <v-card class="pa-3" variant="outlined">
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-subtitle-1">Odbiór w punkcie</span>
                      <v-btn
                        v-if="!editingPickup"
                        icon
                        size="small"
                        variant="text"
                        @click="startEdit('pickup')"
                      >
                        <v-icon>mdi-pencil</v-icon>
                      </v-btn>
                    </div>

                    <!-- View mode -->
                    <template v-if="!editingPickup">
                      <div>
                        <b>Nazwa:</b>
                        {{ delivery.delivery_point_name || "..." }}
                      </div>
                      <div><b>ID:</b> {{ delivery.delivery_point_id }}</div>
                      <div>
                        <b>Adres:</b>
                        {{ delivery.delivery_point_address || "" }}
                      </div>
                      <div>
                        <b>Kod i miasto:</b>
                        {{ delivery.delivery_point_postcode || "" }},
                        {{ delivery.delivery_point_city || "" }}
                      </div>
                    </template>

                    <!-- Edit mode -->
                    <template v-else>
                      <v-text-field
                        v-model="editPickupData.delivery_point_name"
                        label="Nazwa"
                        density="compact"
                        variant="outlined"
                        class="mb-2"
                      />
                      <v-text-field
                        v-model="editPickupData.delivery_point_id"
                        label="ID"
                        density="compact"
                        variant="outlined"
                        class="mb-2"
                      />
                      <v-text-field
                        v-model="editPickupData.delivery_point_address"
                        label="Adres"
                        density="compact"
                        variant="outlined"
                        class="mb-2"
                      />
                      <v-row class="mb-3">
                        <v-col cols="6">
                          <v-text-field
                            v-model="editPickupData.delivery_point_postcode"
                            label="Kod pocztowy"
                            density="compact"
                            variant="outlined"
                          />
                        </v-col>
                        <v-col cols="6">
                          <v-text-field
                            v-model="editPickupData.delivery_point_city"
                            label="Miasto"
                            density="compact"
                            variant="outlined"
                          />
                        </v-col>
                      </v-row>

                      <div class="d-flex gap-2">
                        <v-btn
                          color="primary"
                          size="small"
                          @click="saveEdit('pickup')"
                        >
                          Zapisz
                        </v-btn>
                        <v-btn
                          color="grey"
                          size="small"
                          variant="outlined"
                          @click="cancelEdit('pickup')"
                        >
                          Anuluj
                        </v-btn>
                      </div>
                    </template>
                  </v-card>
                </v-col>
              </v-row>
            </v-tabs-window-item>
            <v-tabs-window-item value="products">
              <div class="pa-4 overflow-auto" style="height: 400px">
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
      // Edit states
      editingDelivery: false,
      editingInvoice: false,
      editingPickup: false,
      editingMainData: false,
      // Edit data copies
      editDeliveryData: {},
      editInvoiceData: {},
      editPickupData: {},
      editMainData: {},
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

        // Reset edit states
        this.editingDelivery = false;
        this.editingInvoice = false;
        this.editingPickup = false;
        this.editingMainData = false;

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

    startEdit(section) {
      if (section === "delivery") {
        this.editingDelivery = true;
        this.editDeliveryData = {
          delivery_fullname: this.delivery.delivery_fullname || "",
          delivery_company: this.delivery.delivery_company || "",
          delivery_address: this.delivery.delivery_address || "",
          delivery_postcode: this.delivery.delivery_postcode || "",
          delivery_city: this.delivery.delivery_city || "",
          delivery_state: this.delivery.delivery_state || "",
          delivery_country: this.delivery.delivery_country || "",
        };
      } else if (section === "invoice") {
        this.editingInvoice = true;
        this.editInvoiceData = {
          invoice_fullname: this.delivery.invoice_fullname || "",
          invoice_company: this.delivery.invoice_company || "",
          invoice_address: this.delivery.invoice_address || "",
          invoice_postcode: this.delivery.invoice_postcode || "",
          invoice_city: this.delivery.invoice_city || "",
          invoice_nip: this.delivery.invoice_nip || "",
          invoice_country: this.delivery.invoice_country || "",
        };
      } else if (section === "pickup") {
        this.editingPickup = true;
        this.editPickupData = {
          delivery_point_name: this.delivery.delivery_point_name || "",
          delivery_point_id: this.delivery.delivery_point_id || "",
          delivery_point_address: this.delivery.delivery_point_address || "",
          delivery_point_postcode: this.delivery.delivery_point_postcode || "",
          delivery_point_city: this.delivery.delivery_point_city || "",
        };
      } else if (section === "maindata") {
        this.editingMainData = true;
        this.editMainData = {
          email: this.client.Email || "",
          telefon: this.client.Telefon || "",
          zrodlo: this.order._OrdersTempString7 || "",
          delivery_method: this.delivery.delivery_method || "",
          payment_method: this.delivery.payment_method || "",
          remarks: this.order.Remarks || "",
          status: this.order.IDOrderStatus || "",
        };
      }
    },

    cancelEdit(section) {
      if (section === "delivery") {
        this.editingDelivery = false;
        this.editDeliveryData = {};
      } else if (section === "invoice") {
        this.editingInvoice = false;
        this.editInvoiceData = {};
      } else if (section === "pickup") {
        this.editingPickup = false;
        this.editPickupData = {};
      } else if (section === "maindata") {
        this.editingMainData = false;
        this.editMainData = {};
      }
    },

    async saveEdit(section) {
      try {
        let dataToSave = {};

        if (section === "delivery") {
          dataToSave = this.editDeliveryData;
        } else if (section === "invoice") {
          dataToSave = this.editInvoiceData;
        } else if (section === "pickup") {
          dataToSave = this.editPickupData;
        } else if (section === "maindata") {
          dataToSave = this.editMainData;
        }

        const params = {
          IDOrder: this.orderId,
          IDWarehouse: this.orderWarehouse,
          section: section,
          data: dataToSave,
        };

        // Call the API to save data
        const response = await axios.post("/api/saveDataOrder", params);

        if (response.data.success) {
          // Update local data on successful save
          if (section === "maindata") {
            // Update multiple objects for main data
            if (this.client) {
              this.client.Email = dataToSave.email;
              this.client.Telefon = dataToSave.telefon;
            }
            if (this.order) {
              this.order._OrdersTempString7 = dataToSave.zrodlo;
              this.order.Remarks = dataToSave.remarks;
              this.order.IDOrderStatus = dataToSave.status;
            }
            if (this.delivery) {
              this.delivery.delivery_method = dataToSave.delivery_method;
              this.delivery.payment_method = dataToSave.payment_method;
            }
          } else {
            Object.assign(this.delivery, dataToSave);
          }

          // Reset edit state
          this.cancelEdit(section);

          console.log(`Saved ${section} data:`, dataToSave);

          // Optionally show success message
          // this.$emit('show-message', 'Dane zostały zapisane pomyślnie');
        } else {
          throw new Error(response.data.error || "Unknown error occurred");
        }
      } catch (err) {
        console.error("Błąd podczas zapisywania:", err);
        this.error = "Błąd zapisywania danych: " + (err.message || err);
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
