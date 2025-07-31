<script setup>
import { ref } from 'vue';

defineProps({
    modelValue: String,
});

const input = ref(null);

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <textarea :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" ref="input" placeholder=""
        class="textarea textarea-md"></textarea>
</template>