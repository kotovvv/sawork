<template>
  <div class="slide-drawer-container">
    <!-- Кнопка для открытия/закрытия drawer - минималистичная версия -->
    <v-btn
      v-if="!isOpen && showToggleButton"
      class="drawer-toggle-btn-mini"
      icon
      color="primary"
      size="small"
      @click="toggleDrawer"
      elevation="4"
    >
      <v-icon size="20">{{ toggleIcon }}</v-icon>
    </v-btn>

    <!-- Drawer панель -->
    <v-navigation-drawer
      v-model="isOpen"
      location="right"
      temporary
      :width="drawerWidth"
      class="slide-drawer-mini"
      :style="{ zIndex: zIndex }"
    >
      <!-- Минималистичный заголовок -->
      <div class="drawer-header-mini">
        <h4>{{ title }}</h4>
        <v-btn icon @click="toggleDrawer" size="small" variant="text">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </div>

      <!-- Контент drawer -->
      <div class="drawer-content-mini">
        <slot></slot>
      </div>
    </v-navigation-drawer>

    <!-- Overlay -->
    <v-overlay
      v-if="isOpen && showOverlay"
      :value="isOpen"
      @click="closeDrawer"
      class="drawer-overlay"
      :style="{ zIndex: zIndex - 1 }"
    ></v-overlay>
  </div>
</template>

<script>
export default {
  name: "SlideDrawerMini",
  props: {
    title: {
      type: String,
      default: "Панель",
    },
    width: {
      type: [String, Number],
      default: 300,
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
      default: "mdi-menu",
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

.drawer-toggle-btn-mini {
  position: fixed;
  right: 15px;
  top: 20px;
  z-index: 1000;
  background: white;
  border: 2px solid #e0e0e0;
}

.drawer-toggle-btn-mini:hover {
  transform: scale(1.1);
  transition: transform 0.2s ease;
}

.slide-drawer-mini {
  z-index: 1001;
  box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
}

.drawer-header-mini {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #e0e0e0;
  background: white;
}

.drawer-header-mini h4 {
  margin: 0;
  color: #333;
  font-size: 1.1rem;
  font-weight: 500;
}

.drawer-content-mini {
  padding: 20px;
  height: calc(100vh - 64px);
  overflow-y: auto;
  background: white;
}

.drawer-overlay {
  z-index: 1000;
  background-color: rgba(0, 0, 0, 0.3);
}

/* Анимация */
.slide-drawer-mini .v-navigation-drawer {
  transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

/* Адаптивные стили */
@media (max-width: 768px) {
  .drawer-toggle-btn-mini {
    right: 10px;
    top: 15px;
  }

  .slide-drawer-mini {
    width: 100% !important;
  }
}
</style>
