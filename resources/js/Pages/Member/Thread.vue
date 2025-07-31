<script setup>
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    topic: Object,
    threads: Object,
    isFollow: Boolean
});

const api = axios.create({
    baseURL: `http://localhost:8000`,
    withCredentials: true
});

const isFollow = ref(props.isFollow);

const toggleFollow = () => {
    api.post(route('categories.follow', props.topic.slug)).then(res => isFollow.value = !isFollow.value).catch();
}

</script>

<template>
    <DefaultLayout :title="topic.slug">
        <template #absolute-body></template>
        <template #search></template>
        <template #body>
            <div class="flex items-center mt-2 mb-4">
                <h1 class="text-lg font-semibold">{{ topic.title }}</h1>
                <form @submit.prevent="toggleFollow">
                    <button class="btn btn-soft ms-4 btn-sm">{{ (isFollow) ? 'Unfollow' : 'Follow' }}</button>
                </form>
            </div>
            <table class="w-full table-auto border-collapse text-sm">
                <tbody>
                    <tr>
                        <th class="border-b border-base-content p-4 py-3 pl-8 text-left font-semibold">
                            Topic</th>
                        <th class="border-b border-base-content p-4 py-3 pl-8 text-left font-semibold max-sm:hidden">
                            Replies</th>
                        <th class="border-b border-base-content p-4 py-3 pl-8 text-left font-semibold max-sm:hidden">
                            Views</th>
                        <th class="border-b border-base-content p-4 py-3 pl-8 text-left font-semibold">
                            Last post</th>
                    </tr>
                    <tr v-for="item in threads.data" :key="item.thread_id">
                        <td class="border-b border-base-content p-4 pl-8">
                            <div class="inline-flex flex-col lg:flex-row items-start gap-2">
                                <Link class="link link-info max-w-[12rem] max-h-[2.5rem] overflow-scroll"
                                    :href="route('threads.detail', [topic.slug, item.slug])">{{
                                        item.title
                                    }}</Link>
                                <div class="flex gap-1"><span v-for="tag in item.tags" class="badge badge-primary">{{
                                        tag.name }} </span></div>
                            </div>
                        </td>
                        <td class="border-b border-base-content p-4 pl-8 max-sm:hidden">{{ item.totalReply }}</td>
                        <td class="border-b border-base-content p-4 pl-8 max-sm:hidden">{{ item.views }}</td>
                        <td class="border-b border-base-content p-4 pl-8">
                            <div v-if="item.lastPost" class="inline-flex flex-col">
                                <p>{{ item.lastPost.created_at }}</p>
                                <p>by {{ item.lastPost.user.name }}</p>
                            </div>
                            <div v-else class="inline-flex flex-col">
                                <p>Empty</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

        </template>
    </DefaultLayout>

</template>