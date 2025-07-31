<script setup>
const props = defineProps({
    count: {
        type: Number,
        default: 1
    }
})
</script>

<template>
<div v-for="i in count" class="flex flex-col items-start justify-between min-w-[20rem] max-w-[20rem] ">
        <div class="flex flex-col items-start justify-between min-h-[19rem] w-full">
            <div class="w-full min-h-[2.5rem] max-h-[2.5rem] bg-accent rounded-none rounded-t-lg skeleton">
            </div>
            <div class="w-full min-h-[2.5rem] max-h-[2.5rem] rounded-none bg-base-200 skeleton">
            </div>
            <div class="w-full grow rounded-none skeleton">
            </div>
            <div class="w-full min-h-[3rem] bg-base-200 rounded-none rounded-b-lg skeleton">
            </div>
        </div>
        </div>
</template>