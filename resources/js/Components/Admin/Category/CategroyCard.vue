<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import TextInput from '@/Components/Default/TextInput.vue';
import Textarea from '@/Components/Default/Textarea.vue';
import InputError from '@/Components/Default/InputError.vue';
import ActionMessage from '@/Components/Default/ActionMessage.vue';

const prop = defineProps({
    idProp: String,
    titleProp: String,
    descriptionProp: String,
    slugProp: String,
    slugParrentProp: String
});
defineEmits(['accessKey', 'addCategories']);

const form = useForm({
    title: prop.titleProp,
    description: prop.descriptionProp,
    slug: prop.slugProp,
    slug_parrent: prop.slugParrentProp
});

const isEditing = ref(false);
const submit = () => {
    form.transform(data => ({
        ...data,
    })).put(route('categories.update', prop.idProp), {
        errorBag: 'updateCategory',
        preserveScroll: true,
        onSuccess: () => { isEditing.value = false },
    });
};


</script>

<template>
    <div class="flex flex-col items-start justify-between bg-base-300 rounded-lg min-w-[20rem] max-w-[20rem] min-h-[19rem] max-h-[19rem] shadow-lg">
        <div v-if="!isEditing" class="flex flex-col items-start justify-between h-full w-full">
            <div class="w-full min-h-[2.5rem] max-h-[2.5rem] p-2 rounded-t-lg bg-accent overflow-scroll">
                <h2 class="font-semibold text-accent-content">{{ titleProp }}</h2>
            </div>
            <div class="w-full min-h-[2.5rem] max-h-[2.5rem] p-2 overflow-scroll bg-base-200">
                <p class="text-sm font-thin"><span class="text-xs font-semibold">slug_parrent:</span> {{ slugParrentProp }}</p>
            </div>
            <div class="w-full min-h-[2.5rem] max-h-[2.5rem] p-2 overflow-scroll bg-base-200">
                <p class="text-sm font-thin"><span class="text-xs font-semibold">Slug:</span> {{ slugProp }}</p>
            </div>
            <div class="w-full grow overflow-scroll p-2">
                <p class="text-sm font-thin">{{ descriptionProp }}</p>
            </div>
            <div class="flex items-center justify-between w-full p-2 rounded-b-lg bg-base-200">
                <button onclick="modal_delete_category.showModal()" @click="$emit('accessKey')" class="btn btn-sm btn-soft btn-error">Delete</button>
                <button onclick="modal_create_category.showModal()" @click="$emit('addCategories')" class="btn btn-sm btn-soft">
                    Add
                </button>
                <Link :href="route('threads.indexAdmin', slugProp)" class="btn btn-sm btn-soft">
                    Detail
                </Link>
                <button @click="isEditing = !isEditing" class="btn btn-sm btn-soft">Edit</button>
            </div>
        </div>

        <form v-if="isEditing" @submit.prevent="submit" class="flex flex-col items-start justify-between h-full w-full">
            <div class="w-full min-h-[2.5rem] rounded-t-lg bg-accent-content overflow-scroll p-2">
                <TextInput id="title" v-model="form.title" type="text" class="mt-1 block w-full input-sm" required
                    autofocus autocomplete="title" />
                <InputError class="mt-2" :message="form.errors.title" />
            </div>
            <div class="w-full min-h-[2.5rem]  overflow-scroll p-2">
                <TextInput id="slug_parrent" v-model="form.slug_parrent" type="text" class="mt-1 block w-full input-sm"
                    autocomplete="slug_parrent" />
                <InputError class="mt-2" :message="form.errors.slug_parrent" />
            </div>
            <div class="w-full min-h-[2.5rem]  overflow-scroll p-2">
                <TextInput id="slug" v-model="form.slug" type="text" class="mt-1 block w-full input-sm" required
                    autocomplete="slug" />
                <InputError class="mt-2" :message="form.errors.slug" />
            </div>
            <div class="w-full grow overflow-scroll p-2">
                <Textarea id="description" v-model="form.description" type="text" class="mt-1 block w-full" required
                    autocomplete="description" />
                <InputError class="mt-2" :message="form.errors.description" />
            </div>
            <div class="inline-flex items-center p-2">
                <button type="submit" class="btn btn-sm btn-soft" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Submit
                </button>
                <ActionMessage :on="form.recentlySuccessful" class="me-3">
                    Saved.
                </ActionMessage>
                <button @click="isEditing = !isEditing" class="btn btn-sm btn-soft">Cancel</button>
            </div>
        </form>
    </div>
</template>