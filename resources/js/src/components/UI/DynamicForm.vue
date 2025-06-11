/* <DynamicForm
  :fields="fields"
  v-model="formValues"
  @save="onSave"
/>
Где:

fields — массив из вашего JSON.
formValues — объект для хранения значений.
onSave — обработчик сохранения.
Редактирование:
Для редактирования просто передавайте в modelValue уже сохранённые значения.

Расширение:
Добавьте обработку package_fields, если нужно, аналогично.
*/
<template>
  <v-form ref="form" v-model="valid">
    <div v-for="field in fields" :key="field.id" class="mb-4">
      <component
        :is="getComponent(field)"
        v-model="formData[field.id]"
        :label="field.name"
        :items="getOptions(field)"
        :multiple="field.type === 'checkbox'"
        :type="field.type === 'text' ? 'text' : undefined"
        :value="formData[field.id]"
        :hint="field.hint"
        persistent-hint
        :rules="field.rules || []"
        :name="field.id"
        :true-value="true"
        :false-value="false"
      ></component>
    </div>
    <v-btn color="primary" @click="save">Сохранить</v-btn>
  </v-form>
</template>

<script setup>
import { ref, reactive, watch, toRefs } from 'vue';

// Пропсы: fields (массив), modelValue (объект для v-model)
const props = defineProps({
  fields: { type: Array, required: true },
  modelValue: { type: Object, default: () => ({}) }
});
const emit = defineEmits(['update:modelValue', 'save']);

const valid = ref(true);
const formData = reactive({ ...props.modelValue });

// Следим за изменениями и пробрасываем наружу
watch(formData, (val) => emit('update:modelValue', { ...val }), { deep: true });

// Определяем компонент по типу поля
function getComponent(field) {
  switch (field.type) {
    case 'select': return 'v-select';
    case 'radio': return 'v-radio-group';
    case 'checkbox': return 'v-checkbox';
    case 'text': return 'v-text-field';
    default: return 'v-text-field';
  }
}

// Преобразуем options в массив для select/radio
function getOptions(field) {
  if (!field.options) return [];
  return Object.entries(field.options).map(([value, text]) => ({
    value, text
  }));
}

// Сохранение формы
function save() {
  emit('save', { ...formData });
}
</script>
