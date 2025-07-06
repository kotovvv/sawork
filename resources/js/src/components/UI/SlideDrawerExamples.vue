<template>
  <div>
    <!-- Основной контент страницы -->
    <v-container>
      <h2>Примеры использования SlideDrawer</h2>

      <v-row>
        <v-col cols="12" md="4">
          <v-card>
            <v-card-title>Настройки</v-card-title>
            <v-card-text>
              <v-btn @click="settingsDrawer = true" color="primary">
                Открыть настройки
              </v-btn>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="4">
          <v-card>
            <v-card-title>Фильтры</v-card-title>
            <v-card-text>
              <v-btn @click="filtersDrawer = true" color="secondary">
                Открыть фильтры
              </v-btn>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="4">
          <v-card>
            <v-card-title>Уведомления</v-card-title>
            <v-card-text>
              <v-btn @click="notificationsDrawer = true" color="success">
                Открыть уведомления
              </v-btn>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>

    <!-- Drawer для настроек -->
    <SlideDrawer
      v-model="settingsDrawer"
      title="Настройки приложения"
      :width="500"
      :show-toggle-button="false"
    >
      <v-form>
        <v-text-field
          label="Имя пользователя"
          v-model="settings.username"
          prepend-icon="mdi-account"
          class="mb-4"
        ></v-text-field>

        <v-select
          label="Язык интерфейса"
          v-model="settings.language"
          :items="languages"
          prepend-icon="mdi-translate"
          class="mb-4"
        ></v-select>

        <v-switch
          label="Темная тема"
          v-model="settings.darkTheme"
          color="primary"
          class="mb-4"
        ></v-switch>

        <v-slider
          label="Размер шрифта"
          v-model="settings.fontSize"
          :min="10"
          :max="24"
          step="1"
          thumb-label
          class="mb-4"
        ></v-slider>

        <v-divider class="my-4"></v-divider>

        <v-btn color="primary" block @click="saveSettings">
          Сохранить настройки
        </v-btn>
      </v-form>
    </SlideDrawer>

    <!-- Drawer для фильтров -->
    <SlideDrawer
      v-model="filtersDrawer"
      title="Фильтры поиска"
      :width="400"
      :show-toggle-button="false"
    >
      <v-text-field
        label="Поиск по названию"
        v-model="filters.search"
        prepend-icon="mdi-magnify"
        clearable
        class="mb-4"
      ></v-text-field>

      <v-select
        label="Категория"
        v-model="filters.category"
        :items="categories"
        prepend-icon="mdi-tag"
        clearable
        class="mb-4"
      ></v-select>

      <v-range-slider
        label="Диапазон цен"
        v-model="filters.priceRange"
        :min="0"
        :max="10000"
        step="100"
        thumb-label
        class="mb-4"
      ></v-range-slider>

      <v-checkbox
        label="Только в наличии"
        v-model="filters.inStock"
        class="mb-4"
      ></v-checkbox>

      <v-divider class="my-4"></v-divider>

      <v-btn color="primary" block @click="applyFilters" class="mb-2">
        Применить фильтры
      </v-btn>

      <v-btn color="secondary" block variant="outlined" @click="resetFilters">
        Сбросить фильтры
      </v-btn>
    </SlideDrawer>

    <!-- Drawer для уведомлений -->
    <SlideDrawer
      v-model="notificationsDrawer"
      title="Уведомления"
      :width="450"
      :show-toggle-button="false"
    >
      <v-list>
        <v-list-item
          v-for="notification in notifications"
          :key="notification.id"
          class="mb-2"
        >
          <template v-slot:prepend>
            <v-avatar :color="notification.color">
              <v-icon>{{ notification.icon }}</v-icon>
            </v-avatar>
          </template>

          <v-list-item-content>
            <v-list-item-title>{{ notification.title }}</v-list-item-title>
            <v-list-item-subtitle>{{
              notification.message
            }}</v-list-item-subtitle>
            <v-list-item-subtitle class="text-caption">
              {{ notification.time }}
            </v-list-item-subtitle>
          </v-list-item-content>

          <template v-slot:append>
            <v-btn
              icon
              size="small"
              @click="dismissNotification(notification.id)"
            >
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </template>
        </v-list-item>
      </v-list>

      <v-divider class="my-4"></v-divider>

      <v-btn
        color="primary"
        block
        @click="markAllAsRead"
        :disabled="!notifications.length"
      >
        Отметить все как прочитанные
      </v-btn>
    </SlideDrawer>

    <!-- Автоматический drawer (всегда видна кнопка) -->
    <SlideDrawer
      v-model="quickActionsDrawer"
      title="Быстрые действия"
      :width="350"
      toggle-icon="mdi-lightning-bolt"
    >
      <v-list>
        <v-list-item
          v-for="action in quickActions"
          :key="action.id"
          @click="executeAction(action)"
          class="mb-2"
        >
          <template v-slot:prepend>
            <v-icon :color="action.color">{{ action.icon }}</v-icon>
          </template>

          <v-list-item-content>
            <v-list-item-title>{{ action.title }}</v-list-item-title>
            <v-list-item-subtitle>{{
              action.description
            }}</v-list-item-subtitle>
          </v-list-item-content>
        </v-list-item>
      </v-list>
    </SlideDrawer>
  </div>
</template>

<script>
import SlideDrawer from "./SlideDrawer.vue";

export default {
  name: "SlideDrawerExamples",
  components: {
    SlideDrawer,
  },
  data() {
    return {
      settingsDrawer: false,
      filtersDrawer: false,
      notificationsDrawer: false,
      quickActionsDrawer: false,

      settings: {
        username: "",
        language: "ru",
        darkTheme: false,
        fontSize: 14,
      },

      filters: {
        search: "",
        category: null,
        priceRange: [0, 5000],
        inStock: false,
      },

      languages: [
        { title: "Русский", value: "ru" },
        { title: "English", value: "en" },
        { title: "Español", value: "es" },
      ],

      categories: ["Электроника", "Одежда", "Книги", "Спорт", "Дом и сад"],

      notifications: [
        {
          id: 1,
          title: "Новое сообщение",
          message: "У вас есть новое сообщение от пользователя",
          time: "5 минут назад",
          icon: "mdi-email",
          color: "primary",
        },
        {
          id: 2,
          title: "Обновление системы",
          message: "Доступна новая версия приложения",
          time: "1 час назад",
          icon: "mdi-update",
          color: "success",
        },
        {
          id: 3,
          title: "Предупреждение",
          message: "Низкий уровень заряда батареи",
          time: "2 часа назад",
          icon: "mdi-battery-low",
          color: "warning",
        },
      ],

      quickActions: [
        {
          id: 1,
          title: "Создать заказ",
          description: "Быстрое создание нового заказа",
          icon: "mdi-plus-circle",
          color: "primary",
        },
        {
          id: 2,
          title: "Экспорт данных",
          description: "Экспорт данных в Excel",
          icon: "mdi-file-export",
          color: "success",
        },
        {
          id: 3,
          title: "Печать отчета",
          description: "Печать текущего отчета",
          icon: "mdi-printer",
          color: "info",
        },
      ],
    };
  },
  methods: {
    saveSettings() {
      // Логика сохранения настроек
      console.log("Сохранены настройки:", this.settings);
      this.settingsDrawer = false;
    },

    applyFilters() {
      // Логика применения фильтров
      console.log("Применены фильтры:", this.filters);
      this.filtersDrawer = false;
    },

    resetFilters() {
      this.filters = {
        search: "",
        category: null,
        priceRange: [0, 5000],
        inStock: false,
      };
    },

    dismissNotification(id) {
      this.notifications = this.notifications.filter((n) => n.id !== id);
    },

    markAllAsRead() {
      this.notifications = [];
    },

    executeAction(action) {
      console.log("Выполнено действие:", action.title);
      this.quickActionsDrawer = false;
    },
  },
};
</script>

<style scoped>
.v-card {
  height: 100%;
}
</style>
