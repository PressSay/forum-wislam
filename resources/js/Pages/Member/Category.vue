<script setup>
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    categories: Object
});

const form = useForm({});
const api = axios.create({
    baseURL: `http://localhost:8000`,
    withCredentials: true
})

const getSubCategories = async ($uuid, callback) => {
    const categories = api.get(route('categories.subCategories', $uuid)).then((response) => {
        // console.log(response.data, typeof(response.data));
        callback(response.data);
    });
};

const subCategories = reactive(
    []
);

props.categories['data'].forEach((element, index) => {
    getSubCategories(element.category_id, (categories) => {
        subCategories[index] = categories;
    });
});
</script>

<template>
    <DefaultLayout title="Explore">
        <template #absolute-body></template>
        <template #search></template>
        <template #body>
            <div class="flex flex-col mb-8">
                <h1 class="text-xl font-semibold">Explore</h1>
            </div>
            <div class="flex flex-col">
                <table class="w-full table-auto border-collapse text-sm">
                    <template v-for="(item_parrent, index_parrent) in categories['data']"
                        :key="item_parrent.category_id">
                        <tr>
                            <th class="border-b border-base-content p-4 py-3 pl-8 text-left font-semibold">
                                {{ item_parrent.title }}</th>
                            <th
                                class="border-b border-base-content p-4 py-3 pl-8 text-left font-semibold max-sm:hidden">
                                Topics</th>
                            <th
                                class="border-b border-base-content p-4 py-3 pl-8 text-left font-semibold max-sm:hidden">
                                Posts</th>
                            <th class="border-b border-base-content p-4 py-3 pl-8 text-left font-semibold">
                                Last post</th>
                        </tr>
                        <tr v-if="subCategories[index_parrent]" v-for="item in subCategories[index_parrent].data"
                            :key="item.category_id">
                            <td class="border-b border-base-content p-4 pl-8">
                                <div class="inline-flex flex-col">
                                    <Link class="link link-info" :href="route('threads.index', item.slug)">{{ item.title
                                    }}</Link>
                                    <p class="font-thin">{{ item.description }}</p>
                                </div>
                            </td>
                            <td class="border-b border-base-content p-4 pl-8 max-sm:hidden">{{ item.topic }}</td>
                            <td class="border-b border-base-content p-4 pl-8 max-sm:hidden">{{ item.post }}</td>
                            <td class="border-b border-base-content p-4 pl-8">
                                <div v-if="item.lastTopic" class="inline-flex flex-col">
                                    <p>{{ item.lastTopic.created_at }}</p>
                                    <p>by {{ item.lastTopic.user.name }}</p>
                                </div>
                                <div v-else class="inline-flex flex-col">
                                    <p>Empty</p>
                                </div>
                            </td>
                        </tr>
                    </template>
                </table>
            </div>
        </template>
    </DefaultLayout>

</template>