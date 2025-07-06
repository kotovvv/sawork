# SlideDrawer Component

Компонент SlideDrawer предоставляет выдвижную панель справа с кнопкой для открытия/закрытия.

## Использование

```vue
<template>
    <div>
        <!-- Основной контент -->
        <SlideDrawer
            v-model="drawerOpen"
            title="Настройки"
            :width="500"
            :show-toggle-button="true"
            toggle-icon="mdi-menu"
        >
            <!-- Содержимое drawer -->
            <div>
                <h4>Ваш контент здесь</h4>
                <v-text-field label="Поле ввода"></v-text-field>
                <v-btn @click="handleAction">Действие</v-btn>
            </div>
        </SlideDrawer>
    </div>
</template>

<script>
import SlideDrawer from "./components/UI/SlideDrawer.vue";

export default {
    components: {
        SlideDrawer,
    },
    data() {
        return {
            drawerOpen: false,
        };
    },
    methods: {
        handleAction() {
            // Ваша логика
            this.drawerOpen = false; // Закрыть drawer
        },
    },
};
</script>
```

## Props

| Prop               | Тип           | По умолчанию         | Описание                           |
| ------------------ | ------------- | -------------------- | ---------------------------------- |
| `modelValue`       | Boolean       | `false`              | Состояние открытия/закрытия drawer |
| `title`            | String        | `'Панель'`           | Заголовок drawer                   |
| `width`            | String/Number | `400`                | Ширина drawer                      |
| `showToggleButton` | Boolean       | `true`               | Показывать ли кнопку переключения  |
| `toggleIcon`       | String        | `'mdi-chevron-left'` | Иконка для кнопки переключения     |
| `showOverlay`      | Boolean       | `true`               | Показывать ли overlay для закрытия |
| `zIndex`           | Number        | `1001`               | Z-index для drawer                 |

## События

| Событие             | Описание                                   |
| ------------------- | ------------------------------------------ |
| `update:modelValue` | Срабатывает при изменении состояния drawer |

## Особенности

1. **Автоматическое позиционирование**: Кнопка фиксируется справа по центру экрана
2. **Адаптивность**: На мобильных устройствах drawer занимает всю ширину
3. **Overlay**: Клик вне drawer закрывает его
4. **Анимация**: Плавное появление/исчезновение
5. **Слоты**: Полная кастомизация содержимого

## Стили

Компонент использует встроенные стили для:

-   Позиционирования кнопки
-   Анимации появления
-   Адаптивного дизайна
-   Hover эффектов

## Интеграция с существующим проектом

В вашем проекте Laravel + Vue + Vuetify:

1. Импортируйте компонент
2. Зарегистрируйте его в components
3. Используйте v-model для управления состоянием
4. Добавьте свой контент через slot

## Примеры использования

### Настройки приложения

```vue
<SlideDrawer v-model="settingsOpen" title="Настройки" :width="600">
  <v-form>
    <v-text-field label="Имя пользователя"></v-text-field>
    <v-select label="Язык" :items="languages"></v-select>
    <v-switch label="Темная тема"></v-switch>
  </v-form>
</SlideDrawer>
```

### Фильтры и поиск

```vue
<SlideDrawer v-model="filtersOpen" title="Фильтры" :width="400">
  <v-text-field label="Поиск"></v-text-field>
  <v-select label="Категория" :items="categories"></v-select>
  <v-range-slider label="Цена"></v-range-slider>
</SlideDrawer>
```

### Корзина покупок

```vue
<SlideDrawer v-model="cartOpen" title="Корзина" :width="500">
  <v-list>
    <v-list-item v-for="item in cart" :key="item.id">
      <v-list-item-content>
        <v-list-item-title>{{ item.name }}</v-list-item-title>
        <v-list-item-subtitle>{{ item.price }}₽</v-list-item-subtitle>
      </v-list-item-content>
    </v-list-item>
  </v-list>
  <v-btn color="primary" block>Оформить заказ</v-btn>
</SlideDrawer>
```
