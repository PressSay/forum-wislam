<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="btn btn-outline btn-error text-xs uppercase">
        <slot />
    </button>
</template>
