<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import TextInput from '@/Components/Default/TextInput.vue';
import Textarea from '@/Components/Default/Textarea.vue';
import InputError from '@/Components/Default/InputError.vue';
import ActionMessage from '@/Components/Default/ActionMessage.vue';

const props = defineProps({
    idProp: String,
    titleProp: String,
    slugProp: String,
    slugParrentProp: String,
    isPinned: Boolean,
    isLocked: Boolean
});
defineEmits(['accessKey']);

const api = axios.create({
    baseURL: `http://localhost:8000`,
    withCredentials: true
})

const isPinned = ref(props.isPinned);
const togglePin = () => {
    api.put(route('threads.update', props.idProp), {
        'pin': !isPinned.value
    }).then(res => isPinned.value = !isPinned.value).catch();
}

const isLocked = ref(props.isLocked);
const toggleLock = () => {
    api.put(route('threads.update', props.idProp), {
        'lock': !isLocked.value
    }).then(res => isLocked.value = !isLocked.value).catch();
}

</script>

<template>
    <div
        class="flex flex-col items-start justify-between bg-base-300 rounded-lg min-w-[20rem] max-w-[20rem] min-h-[5.5rem] max-h-[5.5rem] shadow-lg">
        <h2 class="inline-flex justify-center items-center w-full p-2 font-semibold">{{ titleProp }}</h2>
        <div class="flex justify-between item-center w-full p-2 ">
            <Link :href="route('threads.detail', [slugParrentProp, slugProp])" class="btn btn-soft btn-sm">View</Link>
            <button class="btn btn-soft btn-warning btn-sm" @click="toggleLock">{{ isLocked ? 'Unlock' : 'Lock' }}</button>
            <button class="btn btn-soft btn-info btn-sm" @click="togglePin">{{ isPinned ? 'Unpin' : 'Pin' }}</button>
            <button onclick="modal_delete_thread.showModal()" @click="$emit('accessKey')"
                class="btn btn-soft btn-error btn-sm">Delete</button>
        </div>
    </div>
</template>