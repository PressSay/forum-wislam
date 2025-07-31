<script setup>
import { ref, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/Default/InputLabel.vue';
import TextInput from '@/Components/Default/TextInput.vue';
import InputError from '@/Components/Default/InputError.vue';
import { debounce } from 'lodash';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import('quill/dist/quill.snow.css');
import { toast } from 'vue3-toastify';


const props = defineProps({
    thread: Object,
    specific_topic: Object
});
const topics = ref(null);
const tags = ref(null);
const api = axios.create({
    baseURL: `http://localhost:8000`,
    withCredentials: true
});
const getTopics = (callback, page = 1, search = '') => {
    let url = route('categories.user');
    if (search != '') {
        url = url + "?search=" + search;
    }
    url = (search != '') ? url + "&page=" + page : url + "?page=" + page;

    api.get(url).then((response) => {
        callback(response.data);
    });
}
const getTags = (callback, page = 1, search = '') => {
    if (search !== '') {
        api.get(route('tags.all') + "?page=" + page).then((response) => {
            callback(response.data);
        });
    } else {
        api.get(route('tags.all') + "?search=" + search).then((response) => {
            callback(response.data);
        })
    }
}
getTopics((data) => {
    topics.value = data;
});
getTags((data) => {
    tags.value = data;
});


const toolbarOptions = [
    ['bold', 'italic', 'underline', 'strike'],        // toggled buttons
    ['blockquote', 'code-block'],
    ['image', 'video'],

    [{ 'header': 1 }, { 'header': 2 }],               // custom button values
    [{ 'list': 'ordered' }],
    [{ 'script': 'sub' }, { 'script': 'super' }],      // superscript/subscript
    [{ 'indent': '-1' }, { 'indent': '+1' }],          // outdent/indent
    [{ 'direction': 'rtl' }],                         // text direction

    [{ 'size': ['small', false, 'large', 'huge'] }],  // custom dropdown
    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],

    [{ 'color': [] }, { 'background': [] }],          // dropdown with defaults from theme
    [{ 'font': [] }],
    [{ 'align': [] }],

    ['clean']                                         // remove formatting button
];

const form = useForm({
    title: '',
    content: '',
    slug_category: '',
    tags: []
});

const titleTopic = ref((props.specific_topic) ? props.specific_topic.title : (props.thread) ? props.thread.category.title : "Select a community");
if (props.thread) {
    form.title = props.thread.title;
    form.slug_category = props.thread.category.slug;
    form.tags = props.thread.tags;
} else if (props.specific_topic) {
    form.slug_category = props.specific_topic.slug;
}

const editor = ref(Object);


onMounted(() => {
    Promise.all([
        import('quill'),
        import('quill-delta-to-html'),
        import('quill-resize-module'),
        import('highlight.js/lib/common')
    ]).then(([QuillModule, QuillDeltaToHtmlConverterModule, QuillResizeModule, highlightModule]) => {
        const Quill = QuillModule.default;
        const QuillResize = QuillResizeModule.default;
        Quill.register('modules/resize', QuillResize);
        const { QuillDeltaToHtmlConverter } = QuillDeltaToHtmlConverterModule;
        const hljs = highlightModule.default;
        const BlockEmbed = Quill.import('blots/block/embed');
        class ImageBlot extends BlockEmbed {
            static blotName = 'image';
            static tagName = 'img';
            static create(value) {
                const node = super.create();
                node.setAttribute('src', value);
                if (value.width) {
                    node.setAttribute('width', value.width);
                }
                if (value.height) {
                    node.setAttribute('height', value.height);
                }
                if (value.class) {
                    node.setAttribute('class', value.class)
                }
                return node;
            }
            static formats(node) {
                // We still need to report unregistered embed formats
                const format = {};
                if (node.hasAttribute('height')) {
                    format.height = node.getAttribute('height');
                }
                if (node.hasAttribute('width')) {
                    format.width = node.getAttribute('width');
                }
                if (node.hasAttribute('class')) {
                    const tmpClass = node.getAttribute('class').split(" ")[0];
                    if (tmpClass !== 'active') {
                        format.alignImg = tmpClass;
                    } else {
                        delete format.alignImg;
                    }
                }
                return format;
            }
            static value(node) {
                let objSrc = { image: node.getAttribute('src') };
                ImageBlot.src = objSrc.image;
                return objSrc.image;
            }
            format(name, value) {
                // Handle unregistered embed formats
                if (name === 'height' || name === 'width' || name === 'alignImg') {
                    if (value) {
                        this.domNode.setAttribute(name == 'alignImg' ? 'class' : name, value);
                    } else {
                        this.domNode.removeAttribute(name, value);
                    }
                } else {
                    super.format(name, value);
                }
            }
        }
        Quill.register('formats/image', ImageBlot, true);
        if (props.thread) {
            try {
                const normalized = normalizeDelta(props.thread.content);
                const converter = new QuillDeltaToHtmlConverter(normalized.ops, {
                    customCssClasses: (op) => {
                        if (op.insert.type == 'image') {
                            if (op.attributes.alignImg) {
                                return op.attributes.alignImg
                            }
                        }
                    },
                });
                document.getElementById("editor").innerHTML = converter.convert();
            } catch (e) {
                console.error('Delta parsing error:', e);
            }
        }
        editor.value = new Quill('#editor', {
            theme: 'snow',
            modules: {
                syntax: { hljs },
                toolbar: toolbarOptions,
                resize: {
                    modules: ['Resize', 'DisplaySize', 'Toolbar']
                }
            },
        });
        editor.value.on("text-change", function (v) {
            var delta = editor.value.getContents();
            var qdc = new QuillDeltaToHtmlConverter(delta.ops, {
                customCssClasses: (op) => {
                    if (op.insert.type == 'image') {
                        if (op.attributes.alignImg) {
                            return op.attributes.alignImg
                        }
                    }
                },
            });
            var html = qdc.convert();
            document.getElementById("converted-view").innerHTML = html;
        });
    });
});

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

    return parsed;
}

const submit = () => {
    // console.log(editor.value.getContents());
    form.content = JSON.stringify(editor.value.getContents());
    if (props.thread) {
        form.transform(data => ({
            ...data,
        })).put(route('threads.update', props.thread.thread_id), {
            errorBag: 'updateThread',
            preserveScroll: true,
            onSuccess: () => {
                toast('Upload Successfully', {
                    autoClose: 500,
                    "theme": "auto",
                    "type": "success",
                    "dangerouslyHTMLString": true
                });
            },
        });
    } else {
        form.transform(data => ({
            ...data,
        })).post(route('threads.store'), {
            errorBag: 'storeThread',
            preserveScroll: true,
            onSuccess: () => {
                toast('Upload Successfully', {
                    autoClose: 500,
                    "theme": "auto",
                    "type": "success",
                    "dangerouslyHTMLString": true
                });
            },
        });
    }
}

const valueSearchUserCategories = ref('');
const searchUserCategoriesDebounced = debounce(function (value) {
    valueSearchUserCategories.value = value;
    getTopics((data) => {
        topics.value = data;
    }, 1, value);
}, 500);


const isSelectCategory = ref(false);
const selectCategories = () => {
    isSelectCategory.value = !isSelectCategory.value;
}
const searchUserCategories = (value) => {
    searchUserCategoriesDebounced(value);
}
const selectTopic = (topic) => {
    titleTopic.value = topic.title;
    form.slug_category = topic.slug;
    clearTimeout(blurTimeout.value);
    isSelectCategory.value = false;
    searchUserCategoriesDebounced("");
}
const blurTimeout = ref(null);
const handleBlur = () => {
    // Đặt một timeout để trì hoãn việc ẩn danh sách
    blurTimeout.value = setTimeout(() => {
        isSelectCategory.value = false;
        searchUserCategoriesDebounced("");
    }, 150); // Điều chỉnh giá trị 150ms theo nhu cầu của bạn
};
const refSearchCategory = ref(null);
const maxPage = ref(0);
const nextUserCategories = () => {
    clearTimeout(blurTimeout.value);
    refSearchCategory.value.focus();
    const page = (topics.value['current_page'] < maxPage.value || maxPage.value == 0) ? topics.value['current_page'] + 1 : topics.value['current_page'];
    
    if (page == topics.value['current_page'] + 1 && page != maxPage.value) {
        getTopics((data) => {
            if (data['data'].length == 0) {
                maxPage.value = data['current_page'];
            } else {
                topics.value = data;
            }
        }, page, valueSearchUserCategories.value ?? '');

    }
}
const previousUserCategories = () => {
    clearTimeout(blurTimeout.value);
    refSearchCategory.value.focus();
    const page = (topics.value['current_page'] - 1) <= 0 ? 0 : topics.value['current_page'] - 1;
    
    if (page > 0) {
        getTopics((data) => {
            topics.value = data;
        }, page, valueSearchUserCategories.value ?? '')
    }
}

const checkTag = (slug_tag, name) => {
    for (let index = 0; index < form.tags.length; index++) {
        const slug = form.tags[index].slug;
        if (slug == slug_tag) {
            form.tags = form.tags.filter((item) => item.slug != slug);
            return;
        }
    }
    form.tags.push({
        name: name,
        slug: slug_tag
    });
}


const haveTagInForm = (slug_tag) => {
    const tag = form.tags.filter(item => item.slug == slug_tag);
    return tag.length > 0;
}
</script>

<template>
    <DefaultLayout>
        <template #absolute-body>
            <dialog id="tags_modal" class="modal">
                <div class="modal-box">
                    <form method="dialog">
                        <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                    </form>
                    <h3 class="text-lg font-bold mb-4">Tags</h3>
                    <div class="flex flex-col items-center gap-5">
                        <div v-if="tags" v-for="item in tags['data']" class="flex justify-between items-center w-full">
                            <div class="flex items-center gap-2">
                                <div class="flex flex-col">
                                    <span v-for="(desc, index) in item.description.split('\n')"
                                        :class="{ 'text-xs': (index == 1) }">{{ desc }}</span>
                                </div>
                            </div>
                            <input :checked="haveTagInForm(item.slug)" @click="checkTag(item.slug, item.name)"
                                type="checkbox" class="toggle" />
                        </div>
                    </div>
                </div>
            </dialog>
        </template>
        <template #search></template>
        <template #body>
            <h1 class="text-lg font-semibold mb-4">{{ thread ? 'Edit Thread' : 'New Thread' }}</h1>
            <form @submit.prevent="submit" class="mb-4">
                <div class="flex flex-col items-start">
                    <div class="mb-4 flex flex-col">
                        <InputLabel for="slug-topic" value="Topic" class="mb-1" />
                        <div class="relative flex flex-col ">
                            <div v-if="!isSelectCategory" class="btn btn-outline" @click="selectCategories">
                                <span>{{ titleTopic }}</span>
                                <svg rpl="" class="ml-xs" fill="currentColor" height="20" icon-name="caret-down-outline"
                                    viewBox="0 0 20 20" width="20" xmlns="http://www.w3.org/2000/svg">
                                    <!--?lit$673517024$--><!--?lit$673517024$-->
                                    <path
                                        d="M10 13.02a.755.755 0 0 1-.53-.22L4.912 8.242A.771.771 0 0 1 4.93 7.2a.771.771 0 0 1 1.042-.018L10 11.209l4.028-4.027a.771.771 0 0 1 1.042.018.771.771 0 0 1 .018 1.042L10.53 12.8a.754.754 0 0 1-.53.22Z">
                                    </path><!--?-->
                                </svg>
                            </div>
                            <label v-if="isSelectCategory" class="input">
                                <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none"
                                        stroke="currentColor">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <path d="m21 21-4.3-4.3"></path>
                                    </g>
                                </svg>
                                <TextInput ref="refSearchCategory" @input="searchUserCategories($event.target.value)"
                                    @propertychange="searchUserCategories($event.target.value)" @blur="handleBlur"
                                    autofocus type="Search" class="grow" :isCustomize="true" />
                            </label>
                            <div v-if="isSelectCategory"
                                class="absolute z-1 mt-4 p-4 top-10 bg-base-100 shadow-sm rounded-box flex flex-col max-h-[25.75rem] overflow-scroll">
                                <div class="join">
                                    <div @click="previousUserCategories" class="join-item btn btn-sm">«</div>
                                    <div class="join-item btn btn-sm">{{ topics['current_page'] }}</div>
                                    <div @click="nextUserCategories" class="join-item btn btn-sm">»</div>
                                </div>
                                <div v-for="item in topics['data']" :key="item.category_id"
                                    class="btn btn-outline mt-2 w-[18rem]" @click="selectTopic(item)">
                                    {{ item.title }}</div>
                            </div>
                        </div>
                        <InputError class="mt-2" :message="form.errors.slug_category" />
                    </div>
                    <div class="flex items-center gap-2 mb-4">
                        <div v-for="item in form.tags" class="badge badge-primary">
                            {{ item.name }}
                        </div>
                    </div>
                    <div class="mb-4">
                        <InputLabel for="title" value="Title" class="mb-1 w-[21rem]" />
                        <TextInput id="title" v-model="form.title" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.title" />
                        <InputError class="mt-2" :message="form.errors.slug" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="tag" value="Tags" class="mb-1 w-[21rem]" />
                        <div onclick="tags_modal.showModal()" class="btn btn-outline">Add tags</div>
                        <InputError class="mt-2" :message="form.errors.title" />
                        <InputError class="mt-2" :message="form.errors.slug" />
                    </div>
                </div>
                <div class="mb-1">
                    <div id="toolbar">
                    </div>
                    <div id="editor">
                    </div>
                </div>
                <InputError :message="form.errors.content" />
                <div class="inline-flex items-center mt-4">
                    <button class="btn btn-outline" type="submit">Submit</button>
                    <!-- <ActionMessage :on="form.recentlySuccessful" class="ms-3">
                        Saved.
                    </ActionMessage> -->
                </div>

            </form>
            <div class="inline-flex flex-col mb-4">
                <h1 class="text-lg font-semibold mb-2">Review</h1>
                <div class="h-full border-b"></div>
            </div>
            <div id="converted-view" class="nakedScope"></div>
        </template>
    </DefaultLayout>
</template>