<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import ActionMessage from '@/Components/Default/ActionMessage.vue';
import ThreadCard from '@/Components/Admin/Thread/ThreadCard.vue';
import ThreadCardSkeleton from '@/Components/Admin/Thread/ThreadCardSkeleton.vue';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';

const form = useForm({
    title: '',
    description: '',
    slug: '',
    slug_parrent: '',
});

const searchForm = useForm({
    search: '',
});

const props = defineProps({
    threads: Object,
    category: Object
});

const threadId = ref('');
const closeModelDeleteThread = ref(null);

const accessKey = (uuid) => {
    threadId.value = uuid;
}

const deleteThread = () => {
    form.delete(route('threads.destroy', threadId.value), {
        errorBag: 'deleteThread',
        preserveScroll: true,
        onSuccess: () => { },
    });
    threadId.value = '';
    closeModelDeleteThread.value.click();
}


const performSearch = () => {
    console.log(searchForm.search.toLowerCase());
    searchForm.get(route('threads.indexAdmin', props.category.slug), {
        preserveScroll: true,
        onSuccess: () => { },
    });
}

const clearSearch = () => {
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
                    <input v-model="searchForm.search" type="search" class="grow" placeholder="Search for Threads" />
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
            <dialog id="modal_delete_thread" class="modal">
                <div class="modal-box">
                    <div class="flex flex-col items-center">
                        <h3 class="font-bold text-lg mb-4">Delete Thread</h3>
                        <p>Are you sure you want to delete this Thread?</p>
                    </div>
                    <div class="modal-action inline-flex items-center justify-center w-full">
                        <button class="btn btn-sm btn-soft btn-error me-2" @click="deleteThread"
                            :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Delete
                        </button>
                        <form method="dialog">
                            <!-- if there is a button in form, it will close the modal -->
                            <button type="submit" class="btn btn-sm btn-soft"
                                ref="closeModelDeleteThread">Close</button>
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
                    <h2 class="text-2xl font-semibold">{{ category.title }}</h2>
                </div>
            </div>
            <!-- {{ $page['props']['categories'] }} -->
            <!-- {{ categories }} -->
            <Suspense>
                <template #default>
                    <div v-if="threads['data']?.length"
                        class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 mx-auto mb-4">
                        <ThreadCard v-for="item in threads['data']" :key="item.thread_id" :titleProp="item.title" :slugParrentProp="category.slug"
                            :contentProp="item.content" :idProp="item.thread_id" :slugProp="item.slug"
                            @accessKey="accessKey(item.thread_id)" :isLocked="item.is_locked" :isPinned="item.is_pinned"/>
                    </div>
                    <div v-else class="text-center mb-4">
                        No Threads found.
                    </div>
                </template>
                <template #fallback>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 mx-auto mb-4">
                        <ThreadCardSkeleton :count="12" />
                    </div>
                </template>
            </Suspense>


            <div class="join mb-4 mx-auto">

                <Link :class="{ invisible: !threads.prev_page_url }" class="join-item btn"
                    :href="route('threads.indexAdmin', category.category_id)"
                    :active="route().current('threads.indexAdmin', threads['current_page'] - 1)" method="get"
                    :data="{ page: threads['current_page'] - 1 }">
                «
                </Link>
                <button class="join-item btn">Page {{ threads['current_page'] }}</button>

                <Link :class="{ invisible: !threads.next_page_url }" class="join-item btn"
                    :href="route('threads.indexAdmin', category.category_id)"
                    :active="route().current('threads.indexAdmin', threads['current_page'] + 1)" method="get"
                    :data="{ page: threads['current_page'] + 1 }">
                »
                </Link>
            </div>

        </template>
    </DefaultLayout>
</template>