<template>
  <div>
    <v-btn @click="showModal = true" icon="mdi-barcode-scan"></v-btn>
    <Modal v-if="showModal" @close="closeModal">
      <QrCodeScanner @result="handleResult" @close="closeModal" />
    </Modal>
  </div>
</template>

<script>
import Modal from "../UI/Modal.vue";
import QrCodeScanner from "../UI/QrCodeScanner.vue";
export default {
  name: "FulstorGetQrCode",
  components: {
    Modal,
    QrCodeScanner,
  },
  data() {
    return {
      showModal: false,
    };
  },

  mounted() {},

  methods: {
    closeModal() {
      this.showModal = false;
    },
    handleResult(result) {
      this.$emit("result", result);
      this.closeModal();
    },
  },
};
</script>
