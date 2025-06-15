<template>
  <v-form ref="form" v-model="valid">
    <v-row>
      <v-col cols="12" md="6">
        <h4 class="mb-2">Pola</h4>
        <div v-for="field in fields" :key="field.id" class="mb-1">
          <template v-if="field.type === 'radio' && field.options">
            <v-label class="mb-2">{{ field.name }}</v-label>
            <v-radio-group
              v-model="fieldsData[field.id]"
              :rules="field.rules || []"
              :name="field.id"
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
                :rules="field.rules || []"
                :name="field.id"
                multiple
                hide-details="auto"
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
                  :rules="field.rules || []"
                  :name="field.id"
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
              :rules="field.rules || []"
              :name="field.id"
              :true-value="true"
              :false-value="false"
            ></component>
          </template>
        </div>
      </v-col>
      <v-col cols="12" md="6">
        <div v-if="packageFields && packageFields.length">
          <h4 class="mb-2">Pola działek</h4>
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
              :rules="field.rules || []"
              :name="field.id"
              :true-value="true"
              :false-value="false"
            ></component>
          </div>
        </div>
      </v-col>
    </v-row>
    <v-btn color="primary" @click="save">odbiór konosamentu</v-btn>
  </v-form>
</template>

<script setup>
import { ref, reactive, watch, watchEffect } from "vue";

const props = defineProps({
  fields: { type: Array, required: true },
  packageFields: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
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
    (packageFieldsData[f.id] = props.modelValue?.packageFields?.[f.id] ?? "")
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

// Преобразуем options в массив для select/radio/checkbox-group
function getOptions(field) {
  if (!field.options) return [];
  return Object.entries(field.options).map(([value, title]) => ({
    value,
    title,
  }));
}

// Сохранение формы
function save() {
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
