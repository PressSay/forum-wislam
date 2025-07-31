<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import { QuillDeltaToHtmlConverter } from 'quill-delta-to-html';
import ActionMessage from '@/Components/Default/ActionMessage.vue';


const props = defineProps({
    favorites: Object,
});

const shortenQuillDeltaWithImmediateCheck = (delta, maxLength = 50, maxOps = 5) => {
    const shortenedOps = [];
    let totalLength = 0;
    let isUnchanged = true; // Default is same as original

    for (let i = 0; i < delta.ops.length && shortenedOps.length < maxOps; i++) {
        const op = delta.ops[i];
        const newOp = { ...op };

        if (op.insert && op.insert.image) {
            isUnchanged = false;
            continue;
        }

        if (typeof op.insert === 'string' && !op.attributes?.['code-block']) {
            if (op.insert.length > maxLength) {
                newOp.insert = op.insert.substring(0, maxLength) + '...';
                isUnchanged = false;
            }
        }

        if (op.insert === '\n' && i === delta.ops.length - 1 && !op.attributes) {
            isUnchanged = false;
            continue;
        }

        shortenedOps.push(newOp);
        totalLength += typeof newOp.insert === 'string' ? newOp.insert.length : 0;


        if (totalLength > maxLength * maxOps) {
            isUnchanged = false;
            break;
        }
    }

    if (shortenedOps.length < delta.ops.length) {
        isUnchanged = false;
    }
    return { ops: shortenedOps, isUnchanged };
}

const normalizeDelta = (raw) => {

    const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
    parsed.ops = parsed.ops.map((op) => {
        if (op.insert == null && op.attributes === undefined) {
            return { insert: '\n' }
        }
        if ((op.insert === null || op.insert === undefined) && op.attributes) {
            return { ...op, insert: '\n' };
        }
        return op;
    });
    const result = shortenQuillDeltaWithImmediateCheck(parsed);
    return { ops: result.ops };
}

const renderContent = (delta) => {
    if (delta == '')
        return '';
    try {
        const normalized = normalizeDelta(delta);
        const converter = new QuillDeltaToHtmlConverter(normalized.ops, {
            customCssClasses: (op) => {
                if (op.insert.type == 'image') {
                    if (op.attributes.alignImg) {
                        return op.attributes.alignImg
                    }
                }
            },
            multiLineCodeblock: true
        });
        converter.afterRender(function (_, htmlString) {
            if (htmlString.search(/pre/) !== -1) {
                htmlString = htmlString.replace(
                    /<pre data-language="(\w+)">([\s\S]*?)<\/pre>/g,
                    '<pre class="p-0 max-w-[19rem] sm:max-w-[35rem] md:max-w-[41rem] lg:max-w-[51rem] xl:max-w-full"><code class="language-$1">$2</code></pre>'
                );
                return htmlString;
            }

            return htmlString;
        });
        return converter.convert();
    } catch (e) {
        console.error('Delta parsing error:', e);
        return '<p>Error loading content</p>';
    }
};

const form = useForm({});
const thread_id_deleted = ref('');
const deleteFavorite = () => {
    if (thread_id_deleted.value != '') {
        form.delete(route('favorites.destroy', thread_id_deleted.value));
    }
}
const assignKeyThreadToDelete = (thread_id) => {
    thread_id_deleted.value = thread_id;
}

</script>

<template>
    <DefaultLayout>
        <template #absolute-body>
            <dialog id="modal_delete_thread" class="modal">
                <div class="modal-box">
                    <div class="flex flex-col items-center">
                        <h3 class="font-bold text-lg mb-4">Delete Favorite</h3>
                        <p>Are you sure you want to delete this Favorite?</p>
                    </div>
                    <div class="modal-action inline-flex items-center justify-center w-full">
                        <button class="btn btn-sm btn-soft btn-error me-2" @click="deleteFavorite"
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
        <template #search></template>
        <template #search-mobile></template>
        <template #body>
            <div v-if="props.favorites"
                class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 mx-auto mb-4">
                <div v-for="favorite in props.favorites['data']"
                    class="flex flex-col items-start justify-between bg-base-300 rounded-lg min-w-[20rem] max-w-[20rem] shadow-lg">
                    <div class="flex flex-col items-start justify-between w-full">
                        <div class="w-full min-h-[2.5rem] max-h-[2.5rem] p-2 rounded-t-lg bg-info overflow-scroll">
                            <h2 class="font-semibold text-info-content">{{ favorite.title }}</h2>
                        </div>
                        <div class="w-full min-h-[7rem] max-h-[7rem] overflow-scroll p-2"
                            v-html="renderContent(favorite['content'])">
                        </div>

                        <div class="flex justify-center w-full">
                            <div class="carousel carousel-vertical rounded-box min-h-[14rem] max-h-[14rem] mb-2">
                                <div v-for="image in favorite.images" class="carousel-item h-full">
                                    <img class="object-cover" :src="image.full_url" />
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between w-full p-2 rounded-b-lg bg-base-200">
                            <button @click="assignKeyThreadToDelete(favorite.thread_id)"
                                onclick="modal_delete_thread.showModal()"
                                class="btn btn-sm btn-soft btn-error">Delete</button>
                            <Link :href="route('threads.detail', [favorite.slug_category, favorite.slug_thread])"
                                class="btn btn-sm btn-primary">
                            Detail
                            </Link>
                        </div>
                    </div>
                </div>
            </div>




            <!-- <Suspense>
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
</Suspense> -->
        </template>
    </DefaultLayout>
</template>