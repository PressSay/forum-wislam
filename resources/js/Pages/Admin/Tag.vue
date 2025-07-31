<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';

import InputLabel from '@/Components/Default/InputLabel.vue';
import TextInput from '@/Components/Default/TextInput.vue';
import Textarea from '@/Components/Default/Textarea.vue';
import InputError from '@/Components/Default/InputError.vue';
import ActionMessage from '@/Components/Default/ActionMessage.vue';
import CardSkeleton from '@/Components/Admin/CardSkeleton.vue';
// import { route } from 'vendor/tightenco/ziggy/src/js';


import TagCard from '@/Components/Admin/Tag/TagCard.vue'; // test below then turn off this line code
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
// import { ref, defineAsyncComponent } from 'vue';
// const TagCard = defineAsyncComponent(() =>
//     Promise.all([
//         import('@/Components/Admin/Tag/TagCard.vue'),
//         // Giả lập độ trễ 1 giây
//         new Promise(resolve => setTimeout(resolve, 1000)),
//     ]).then(([module]) => module)
// );

const form = useForm({
    name: '',
    description: '',
    slug: '',
});

const searchForm = useForm({
    search: '',
});

const submit = () => {
    form.transform(data => ({
        ...data,
    })).post(route('tags.store'), {
        errorBag: 'storeTag',
        preserveScroll: true,
        onSuccess: () => { },
    });
};

const props = defineProps({
    tags: Object,
});

const tagId = ref('');
const closeModelDeleteTag = ref(null);

const accessKey = (uuid) => {
    tagId.value = uuid;
}

const deleteTag = () => {
    form.delete(route('tags.destroy', tagId.value), {
        errorBag: 'deleteTag',
        preserveScroll: true,
        onSuccess: () => { },
    });
    tagId.value = '';
    closeModelDeleteTag.value.click();
}

const searchTerm = ref('');

const performSearch = () => {
    console.log(searchTerm.value.toLowerCase());
    searchForm.get(route('tags.index'), {
        preserveScroll: true,
        onSuccess: () => { },
    });
}

const clearSearch = () => {
    searchTerm.value = '';
    searchForm.search = '';
}

</script>

<template>
    <DefaultLayout title="Admin-Category">
        <template #search>
            <form @submit.prevent="performSearch">
                <label class="input ms-6 sm:inline-flex hidden">
                    <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none"
                            stroke="currentColor">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </g>
                    </svg>
                    <input v-model="searchForm.search" type="search" class="grow" placeholder="Search for Tags" />
                    <!-- <kbd class="kbd kbd-sm">⌘</kbd>
                    <kbd class="kbd kbd-sm">K</kbd> -->
                    <span @click="clearSearch" class="cursor-pointer"><i
                            class="text-base-content hover:text-primary-content fa-solid fa-delete-left"></i></span>
                    <input type="submit" value="" class="hidden">
                </label>
            </form>

        </template>
        <template #search-mobile>
            <span
                class="cursor-pointer px-2 py-1 md:hover:bg-primary hover:text-primary-content rounded flex items-center mb-5 max-sm:inline-block hidden">
                <i class="w-8 bg-base-100 rounded-full p-2 fa-brands fa-searchengin"></i>
                <span class="mx-2 inline-block sm:hidden lg:inline-block">Search</span>
            </span>
        </template>
        <template #absolute-body>
            <dialog id="modal_create_tag" class="modal">
                <div class="modal-box">
                    <form @submit.prevent="submit">
                        <div class="mb-4">
                            <InputLabel for="name" value="Tag Name" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required
                                autofocus autocomplete="name" />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>
                        <div class="mb-4">
                            <InputLabel for="slug" value="Slug" />
                            <TextInput id="slug" v-model="form.slug" type="text" class="mt-1 block w-full" required
                                autocomplete="slug" />
                            <InputError class="mt-2" :message="form.errors.slug" />
                        </div>
                        <div class="mb-4">
                            <InputLabel for="description" value="Tag Description" />
                            <Textarea id="description" v-model="form.description" type="text" class="mt-1 block w-full" autocomplete="description" />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>
                        <div class="inline-flex items-center">
                            <button class="btn" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Submit
                            </button>
                            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                                Saved.
                            </ActionMessage>
                        </div>

                    </form>
                    <div class="modal-action">
                        <form method="dialog">
                            <!-- if there is a button in form, it will close the modal -->
                            <button class="btn">Close</button>
                        </form>
                    </div>
                </div>
            </dialog>
            <dialog id="modal_delete_tag" class="modal">
                <div class="modal-box">
                    <div class="flex flex-col items-center">
                        <h3 class="font-bold text-lg mb-4">Delete Tag</h3>
                        <p>Are you sure you want to delete this Tag?</p>
                    </div>
                    <div class="modal-action inline-flex items-center justify-center w-full">
                        <button class="btn btn-sm btn-soft btn-error me-2" @click="deleteTag"
                            :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Delete
                        </button>
                        <form method="dialog">
                            <!-- if there is a button in form, it will close the modal -->
                            <button type="submit" class="btn btn-sm btn-soft"
                                ref="closeModelDeleteTag">Close</button>
                        </form>
                        <ActionMessage :on="form.recentlySuccessful" class="me-3">
                            Deleted.
                        </ActionMessage>
                    </div>
                </div>
            </dialog>

        </template>

        <template #body>
            <div class="flex items-center justify-between mb-4 bg-base-200 rounded-md">
                <div class="p-2">
                    <h2 class="text-2xl font-semibold">Tags</h2>
                </div>
                <div class="p-2">
                    <button onclick="modal_create_tag.showModal()" class="btn">Add</button>
                </div>
            </div>
            <!-- {{ $page['props']['categories'] }} -->
            <!-- {{ categories }} -->
            <Suspense>
                <template #default>
                    <div v-if="tags['data']?.length"
                        class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 mx-auto mb-4">
                        <TagCard v-for="item in tags['data']" :key="item.tag_id" :titleProp="item.name"
                            :descriptionProp="item.description" :idProp="item.tag_id"
                             :slugProp="item.slug"
                            @accessKey="accessKey(item.tag_id)" />
                    </div>
                    <div v-else class="text-center">
                        No tags found.
                    </div>
                </template>
                <template #fallback>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 mx-auto mb-4">
                        <CardSkeleton :count="12" />
                    </div>
                </template>
            </Suspense>

            <div class="join mb-4 mx-auto">

                <Link :class="{ invisible: !tags.prev_page_url }" class="join-item btn"
                    :href="route('tags.index')"
                    :active="route().current('tags.index', tags['current_page'] - 1)" method="get"
                    :data="{ page: tags['current_page'] - 1 }">
                «
                </Link>
                <button class="join-item btn">Page {{ tags['current_page'] }}</button>

                <Link :class="{ invisible: !tags.next_page_url }" class="join-item btn"
                    :href="route('tags.index')"
                    :active="route().current('tags.index', tags['current_page'] + 1)" method="get"
                    :data="{ page: tags['current_page'] + 1 }">
                »
                </Link>
            </div>

        </template>
    </DefaultLayout>
</template>