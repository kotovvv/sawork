<template>
  <div>
    <h3>User Location</h3>
    <v-select
      v-model="selectedLocation"
      :items="locations"
      item-text="name"
      item-value="id"
      label="Select Location"
    ></v-select>
  </div>
</template>

<script>
export default {
  name: "FulstorUserLocation",

  data() {
    return {};
  },

  mounted() {},

  methods: {},
};
</script>
