<template>
  <div>
    <div
      class="product_line border my-3"
      v-for="(product, index) in products"
      :key="product.IDTowaru"
    >
      <v-row>
        <v-col>
          <div class="d-flex">
            {{ index + 1 }}.
            <img
              v-if="product.img"
              :src="'data:image/jpeg;base64,' + product.img"
              alt="pic"
              style="height: 3em"
            />
            <span>
              <h5>
                {{ product.Nazwa }}<br />cod: {{ product.KodKreskowy }}, sku:
                {{ product.sku }}
              </h5>
            </span>
          </div>
        </v-col>
        <v-col>
          <div class="d-flex justify-end">
            <div
              v-if="showBtns"
              class="btn border"
              @click="$emit('change-counter', product, -1)"
            >
              -
            </div>
            <div
              :id="product.IDTowaru"
              class="border qty text-h5 text-center"
              :class="{
                'bg-red-darken-4': product.qty > product.ilosc,
                'bg-green-lighten-4': product.qty == product.ilosc,
              }"
            >
              {{ product.qty }} z
              {{ parseInt(product.ilosc) }}
            </div>
            <div
              v-if="showBtns"
              class="btn border"
              @click="$emit('change-counter', product, 1)"
            >
              +
            </div>
          </div>
        </v-col>
      </v-row>
    </div>
    <div v-if="ttn">
      <div
        v-for="(ttnData, ttnNumber) in ttn"
        :key="ttnNumber"
        class="mb-4 bg-grey-lighten-3"
      >
        <v-card class="pa-3 mb-2 bg-grey-lighten-3" outlined max-height="60vh">
          <div class="gap-2 d-flex flex-wrap align-center">
            <strong>TTN:</strong> {{ ttnNumber }}, <strong>Waga:</strong>
            {{ ttnData.weight }}, <strong>Długość:</strong>
            {{ ttnData.length }}, <strong>Szerokość:</strong>
            {{ ttnData.width }}, <strong>Wysokość:</strong>
            {{ ttnData.height }},
            <strong>Date:</strong>
            {{ ttnData.lastUpdate }}
            <v-btn
              v-if="showBtns"
              icon="mdi-file-document-remove-outline"
              @click="$emit('delete-ttn', ttnNumber)"
            >
            </v-btn>
            <v-btn
              icon="mdi-printer-pos-outline"
              @click="$emit('print-ttn', ttnNumber)"
            >
            </v-btn>
          </div>
          <v-row
            class="product_line border my-0"
            v-for="(product, index) in ttnData.products"
            :key="product.IDTowaru"
          >
            <v-col cols="10">
              <div class="d-flex">
                {{ index + 1 }}.
                <img
                  v-if="product.img"
                  :src="'data:image/jpeg;base64,' + product.img"
                  alt="pic"
                  style="height: 3em"
                />
                <span>
                  {{ product.Nazwa }}<br />cod: {{ product.KodKreskowy }}, sku:
                  {{ product.sku }}
                </span>
              </div>
            </v-col>
            <v-col cols="2">
              <div class="d-flex justify-start">
                <div :id="product.IDTowaru" class="text-center">
                  {{ product.qty }}
                </div>
              </div>
            </v-col>
          </v-row>
        </v-card>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "PackProductList",
  props: {
    products: {
      type: Array,
      required: true,
    },
    ttn: {
      type: Object,
      default: null,
    },
    showBtns: {
      type: Boolean,
      default: true,
    },
  },
};
</script>
