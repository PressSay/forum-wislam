<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col md:flex-row-reverse justify-center items-center pt-6 sm:pt-0 ">
        <div>
            <slot name="logo" />
        </div>

        <div class="flex flex-col justify-center w-full sm:w-[30rem] px-6 py-4 min-h-screen overflow-hidden sm:rounded-lg ">
            <slot />
        </div>
    </div>
</template>
