<template>
  <v-form ref="form" v-model="valid" @submit.prevent="save">
    <v-row>
      <v-col cols="12" md="6">
        <div
          v-for="field in fields"
          :key="field.id"
          class="mb-1"
          :class="
            field.id === 'size_type' || hidden == 0 ? 'd-block' : 'd-none'
          "
        >
          <template v-if="field.type === 'radio' && field.options">
            <v-label class="mb-2">{{ field.name }}</v-label>
            <v-radio-group
              v-model="fieldsData[field.id]"
              :name="field.id"
              :rules="getFieldRules(field)"
              @keydown.enter.prevent="save"
            >
              <v-radio
                v-for="opt in getOptions(field)"
                :key="opt.value"
                :label="opt.title"
                :value="opt.value"
              />
            </v-radio-group>
          </template>
          <template v-if="field.type === 'checkbox' && field.options">
            <v-label class="mb-2">{{ field.name }}</v-label>
            <div>
              <v-checkbox
                v-for="opt in getOptions(field)"
                :key="opt.value"
                v-model="fieldsData[field.id]"
                :label="opt.title"
                :value="opt.value"
                :name="`${field.id}_${opt.value}`"
                :rules="getFieldRules(field)"
                multiple
                hide-details="auto"
                @keydown.enter.prevent="save"
              />
            </div>
          </template>
          <template v-else-if="field.type === 'date'">
            <v-menu
              v-model="datePickers[field.id]"
              :close-on-content-click="false"
              transition="scale-transition"
              offset-y
              min-width="auto"
            >
              <template #activator="{ props }">
                <v-text-field
                  v-bind="props"
                  v-model="fieldsData[field.id]"
                  :label="field.name"
                  readonly
                  :name="field.id"
                  :rules="getFieldRules(field)"
                  @keydown.enter.prevent="save"
                />
              </template>
              <v-date-picker
                v-model="fieldsData[field.id]"
                @update:modelValue="datePickers[field.id] = false"
              />
            </v-menu>
          </template>
          <template v-else>
            <component
              :is="getComponent(field)"
              v-model="fieldsData[field.id]"
              :label="field.name"
              :items="getOptions(field)"
              :multiple="field.type === 'checkbox'"
              :type="field.type === 'text' ? 'text' : undefined"
              :value="fieldsData[field.id]"
              :hint="field.hint"
              persistent-hint
              :name="`package_${field.id}`"
              :rules="getFieldRules(field)"
              :true-value="true"
              :false-value="false"
              @keydown.enter.prevent="save"
            ></component>
          </template>
        </div>
      </v-col>
      <v-col cols="12" md="6" v-if="packageFields && packageFields.length">
        <div v-for="field in packageFields" :key="field.id" class="mb-1">
          <component
            :is="getComponent(field)"
            v-model="packageFieldsData[field.id]"
            :label="field.name"
            :items="getOptions(field)"
            :multiple="field.type === 'checkbox'"
            :type="field.type === 'text' ? 'text' : undefined"
            :hint="field.hint"
            persistent-hint
            :name="field.id"
            :rules="getFieldRules(field, true)"
            :true-value="true"
            :false-value="false"
            @keydown.enter.prevent="save"
          ></component>
        </div>
      </v-col>
    </v-row>
    <v-btn color="primary" @click="save">odbiór konosamentu</v-btn>
  </v-form>
</template>

<script setup>
defineOptions({ name: "DynamicForm" });
import { ref, reactive, watch, watchEffect } from "vue";

const props = defineProps({
  fields: { type: Array, required: true },
  packageFields: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
  hidden: { type: Number, default: 1 },
});
const emit = defineEmits(["update:modelValue", "save"]);

const fieldsData = reactive({});
const packageFieldsData = reactive({});
const datePickers = reactive({});
const valid = ref(true);

// Инициализация значений из modelValue
props.fields.forEach(
  (f) => (fieldsData[f.id] = props.modelValue?.fields?.[f.id] ?? "")
);
props.packageFields.forEach(
  (f) =>
    (packageFieldsData[f.id] = props.modelValue?.package_fields?.[f.id] ?? "")
);

watchEffect(() => {
  console.log("packageFields:", props.packageFields.length);
});

// Следим за изменениями и пробрасываем наружу
watch(
  [fieldsData, packageFieldsData],
  () => {
    emit("update:modelValue", {
      fields: { ...fieldsData },
      packageFields: { ...packageFieldsData },
    });
  },
  { deep: true }
);

// Определяем компонент по типу поля
function getComponent(field) {
  switch (field.type) {
    case "select":
      return "v-select";
    case "radio":
      return "v-radio-group";
    case "checkbox":
      return "v-checkbox";
    case "text":
      return "v-text-field";
    case "date":
      return "v-text-field"; // для date-picker отдельная обработка
    default:
      return "v-text-field";
  }
}

// Получаем правила валидации для поля
function getFieldRules(field, isFromPackageFields = false) {
  let rules = field.rules || [];

  // Добавляем обязательность для size_type из fields
  if (!isFromPackageFields && field.id === "size_type") {
    rules = [...rules, (v) => !!v || `${field.name} jest wymagany`];
  }

  // Добавляем обязательность для всех полей из packageFields
  if (isFromPackageFields) {
    rules = [...rules, (v) => !!v || `${field.name} jest wymagany`];
  }

  return rules;
}

// Преобразуем options в массив для select/radio/checkbox-group
function getOptions(field) {
  if (!field.options) return [];
  return Object.entries(field.options).map(([value, title]) => ({
    value,
    title,
  }));
}

// Проверка обязательных полей
function validateRequiredFields() {
  const errors = [];

  // Проверяем поле size_type в fields
  const sizeTypeField = props.fields.find((f) => f.id === "size_type");
  if (
    sizeTypeField &&
    (!fieldsData["size_type"] || fieldsData["size_type"] === "")
  ) {
    errors.push(`${sizeTypeField.name} jest wymagany`);
  }

  // Проверяем все поля в packageFields
  props.packageFields.forEach((field) => {
    if (!packageFieldsData[field.id] || packageFieldsData[field.id] === "") {
      errors.push(`${field.name} jest wymagany`);
    }
  });

  return errors;
}

// Сохранение формы
function save() {
  // Проверяем обязательные поля
  const validationErrors = validateRequiredFields();
  if (validationErrors.length > 0) {
    console.error("Błędy walidacji:", validationErrors);
    // Можно показать уведомление пользователю
    alert("Wypełnij wszystkie wymagane pola:\n" + validationErrors.join("\n"));
    return;
  }

  // Проверяем валидность формы
  if (!valid.value) {
    return;
  }

  emit("save", {
    fields: { ...fieldsData },
    packageFields: { ...packageFieldsData },
  });
}
</script>

/*   <DynamicForm
:fields="fields"
:packageFields="packageFields"
v-model="formValues"
@save="onSave"
/>
Где:
Теперь в formValues и в событии @save вы получите структуру:
{
    fields: { ... },
    packageFields: { ... }
  }
fields — массив из вашего JSON.
formValues — объект для хранения значений.
onSave — обработчик сохранения.
Редактирование:
Для редактирования просто передавайте в modelValue уже сохранённые значения.
*/
