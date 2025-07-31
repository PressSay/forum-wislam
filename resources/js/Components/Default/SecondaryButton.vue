<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-primary border border-primary-content rounded-md font-semibold text-xs text-primary-content uppercase tracking-widest shadow-sm hover:bg-primary-content hover:text-primary focus:outline-none focus:ring-2 focus:ring-accent-content focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
