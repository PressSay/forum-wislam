<script setup>
const props = defineProps({
    count: {
        type: Number,
        default: 1
    }
})
</script>

<template>
    <div
        class="flex flex-col items-start justify-between bg-base-300 rounded-lg min-w-[20rem] max-w-[20rem] min-h-[5.5rem] max-h-[5.5rem] shadow-lg skeleton">
    </div>
</template>