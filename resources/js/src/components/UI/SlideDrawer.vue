<template>
  <div class="slide-drawer-container">
    <!-- Button for opening/closing the drawer -->
    <v-btn
      v-if="!isOpen && showToggleButton"
      class="drawer-toggle-btn"
      icon
      color="primary"
      size="small"
      @click="toggleDrawer"
    >
      <v-icon>{{ toggleIcon }}</v-icon>
    </v-btn>

    <!-- Drawer panel -->
    <v-navigation-drawer
      v-if="isOpen"
      :model-value="true"
      @update:model-value="updateIsOpen"
      location="right"
      persistent
      class="slide-drawer"
      :style="{ zIndex: zIndex, width: drawerWidth }"
    >
      <!-- Header with close button -->
      <v-toolbar color="primary" dark flat class="drawer-header">
        <v-toolbar-title>{{ title }}</v-toolbar-title>
        <v-spacer></v-spacer>
        <v-btn icon @click="toggleDrawer" size="small">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-toolbar>

      <!-- Drawer content -->
      <div class="drawer-content">
        <slot></slot>
      </div>
    </v-navigation-drawer>

    <!-- Overlay for closing when clicking outside drawer -->
    <v-overlay
      v-if="isOpen && showOverlay"
      :model-value="isOpen"
      @click="closeDrawer"
      class="drawer-overlay"
      :style="{ zIndex: zIndex - 1 }"
    ></v-overlay>
  </div>
</template>

<script>
export default {
  name: "SlideDrawer",
  props: {
    title: {
      type: String,
      default: "Panel",
    },
    width: {
      type: [String, Number],
      default: 400,
    },
    modelValue: {
      type: Boolean,
      default: false,
    },
    showToggleButton: {
      type: Boolean,
      default: true,
    },
    toggleIcon: {
      type: String,
      default: "mdi-chevron-left",
    },
    showOverlay: {
      type: Boolean,
      default: true,
    },
    zIndex: {
      type: Number,
      default: 1001,
    },
  },
  emits: ["update:modelValue"],
  data() {
    return {
      isOpen: this.modelValue,
    };
  },
  mounted() {},
  computed: {
    drawerWidth() {
      return typeof this.width === "number" ? `${this.width}px` : this.width;
    },
  },
  watch: {
    modelValue(newVal) {
      this.isOpen = newVal;
    },
    isOpen(newVal) {
      this.$emit("update:modelValue", newVal);
    },
  },
  methods: {
    updateIsOpen(value) {
      this.isOpen = value;
    },
    toggleDrawer() {
      this.isOpen = !this.isOpen;
    },
    closeDrawer() {
      this.isOpen = false;
    },
  },
};
</script>

<style scoped>
.slide-drawer-container {
  position: relative;
}

.drawer-toggle-btn {
  position: fixed;
  right: 20px;
  top: 50%;
  transform: translateY(-50%);
  z-index: 1000;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.drawer-toggle-btn:hover {
  transform: translateY(-50%) scale(1.1);
  transition: transform 0.2s ease;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
}

.slide-drawer {
  z-index: 1001;
  box-shadow: -4px 0 20px rgba(0, 0, 0, 0.1);
}

.drawer-header {
  height: 64px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.drawer-content {
  padding: 20px;
  height: calc(100vh - 64px);
  overflow-y: auto;
  background: #f8f9fa;
}

.drawer-content h4 {
  color: #333;
  margin-bottom: 16px;
  font-weight: 500;
}

.drawer-content .v-divider {
  margin: 16px 0;
}

.drawer-content .v-text-field,
.drawer-content .v-select {
  margin-bottom: 16px;
}

.drawer-content .v-switch {
  margin-bottom: 16px;
}

.drawer-content .v-btn {
  text-transform: none;
  font-weight: 500;
}

.drawer-overlay {
  z-index: 1000;
  background-color: rgba(0, 0, 0, 0.5);
}

/* Анимация для плавного появления */
.slide-drawer .v-navigation-drawer {
  transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

/* Адаптивные стили */
@media (max-width: 768px) {
  .drawer-toggle-btn {
    right: 10px;
    top: 60%;
  }

  .slide-drawer {
    width: 100% !important;
  }

  .drawer-content {
    padding: 16px;
  }
}

/* Дополнительные стили для лучшего UX */
.drawer-toggle-btn .v-icon {
  color: white !important;
}

.slide-drawer .v-navigation-drawer {
  border-radius: 8px 0 0 8px;
}

.drawer-header .v-toolbar-title {
  font-weight: 600;
}
</style>
