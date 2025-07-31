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


import CategroyCard from '@/Components/Admin/Category/CategroyCard.vue'; // test below then turn off this line code
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
// import { ref, defineAsyncComponent } from 'vue';
// const CategroyCard = defineAsyncComponent(() =>
//     Promise.all([
//         import('@/Components/Admin/Category/CategroyCard.vue'),
//         // Giả lập độ trễ 1 giây
//         new Promise(resolve => setTimeout(resolve, 7000)),
//     ]).then(([module]) => module)
// );

const form = useForm({
    title: '',
    description: '',
    slug: '',
    slug_parrent: '',
});

const searchForm = useForm({
    search: '',
});

const submit = () => {
    form.transform(data => ({
        ...data,
    })).post(route('categories.store'), {
        errorBag: 'storeCategory',
        preserveScroll: true,
        onSuccess: () => { },
    });
};

const props = defineProps({
    categories: Object,
});

const categoryId = ref('');
const closeModelDeleteCategory = ref(null);

const addCategories = (slug_parrent) => {
    form.slug_parrent = slug_parrent
    form.title = '';
    form.description = '';
    form.slug = '';
}

const accessKey = (uuid) => {
    categoryId.value = uuid;
}

const deleteCategory = () => {
    form.delete(route('categories.destroy', categoryId.value), {
        errorBag: 'deleteCategory',
        preserveScroll: true,
        onSuccess: () => { },
    });
    categoryId.value = '';
    closeModelDeleteCategory.value.click();
}

const searchTerm = ref('');

const performSearch = () => {
    console.log(searchTerm.value.toLowerCase());
    searchForm.get(route('categories.index'), {
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
                    <input v-model="searchForm.search" type="search" class="grow" placeholder="Search for Categories" />
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
            <dialog id="modal_create_category" class="modal">
                <div class="modal-box">
                    <form @submit.prevent="submit">
                        <div class="mb-4">
                            <InputLabel for="slug-parrent" value="Slug Parrent" />
                            <TextInput id="slug-parrent" v-model="form.slug_parrent" type="text"
                                class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.slug_parrent" />
                        </div>
                        <div class="mb-4">
                            <InputLabel for="title" value="Genre Name" />
                            <TextInput id="title" v-model="form.title" type="text" class="mt-1 block w-full" required
                                autofocus autocomplete="title" />
                            <InputError class="mt-2" :message="form.errors.title" />
                        </div>
                        <div class="mb-4">
                            <InputLabel for="slug" value="Slug" />
                            <TextInput id="slug" v-model="form.slug" type="text" class="mt-1 block w-full" required
                                autocomplete="slug" />
                            <InputError class="mt-2" :message="form.errors.slug" />
                        </div>
                        <div class="mb-4">
                            <InputLabel for="description" value="Genre Description" />
                            <Textarea id="description" v-model="form.description" type="text" class="mt-1 block w-full"
                                required autofocus autocomplete="description" />
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
            <dialog id="modal_delete_category" class="modal">
                <div class="modal-box">
                    <div class="flex flex-col items-center">
                        <h3 class="font-bold text-lg mb-4">Delete Category</h3>
                        <p>Are you sure you want to delete this category?</p>
                    </div>
                    <div class="modal-action inline-flex items-center justify-center w-full">
                        <button class="btn btn-sm btn-soft btn-error me-2" @click="deleteCategory"
                            :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Delete
                        </button>
                        <form method="dialog">
                            <!-- if there is a button in form, it will close the modal -->
                            <button type="submit" class="btn btn-sm btn-soft"
                                ref="closeModelDeleteCategory">Close</button>
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
                    <h2 class="text-2xl font-semibold">Categories</h2>
                </div>
                <div class="p-2">
                    <button onclick="modal_create_category.showModal()" class="btn">Add</button>
                </div>
            </div>
            <!-- {{ $page['props']['categories'] }} -->
            <!-- {{ categories }} -->
            <Suspense>
                <template #default>
                    <div v-if="categories['data']?.length"
                        class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 mx-auto mb-4">
                        <CategroyCard v-for="item in categories['data']" :key="item.category_id" :titleProp="item.title"
                            :descriptionProp="item.description" :idProp="item.category_id"
                            :slugParrentProp="item.slug_parrent" :slugProp="item.slug"
                            @accessKey="accessKey(item.category_id)" @addCategories="addCategories(item.slug)" />
                    </div>
                    <div v-else class="text-center">
                        No categories found.
                    </div>
                </template>
                <template #fallback>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 mx-auto mb-4">
                        <CardSkeleton :count="12" />
                    </div>
                </template>
            </Suspense>


            <div class="join mb-4 mx-auto">

                <Link :class="{ invisible: !categories.prev_page_url }" class="join-item btn"
                    :href="route('categories.index')"
                    :active="route().current('categories.index', categories['current_page'] - 1)" method="get"
                    :data="{ page: categories['current_page'] - 1 }">
                «
                </Link>
                <button class="join-item btn">Page {{ categories['current_page'] }}</button>

                <Link :class="{ invisible: !categories.next_page_url }" class="join-item btn"
                    :href="route('categories.index')"
                    :active="route().current('categories.index', categories['current_page'] + 1)" method="get"
                    :data="{ page: categories['current_page'] + 1 }">
                »
                </Link>
            </div>

        </template>
    </DefaultLayout>
</template>