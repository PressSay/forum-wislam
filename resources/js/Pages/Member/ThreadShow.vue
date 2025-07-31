<script setup>
import('vue3-toastify/dist/index.css');
import('quill/dist/quill.snow.css');
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import { onMounted, ref, nextTick } from 'vue';
import { QuillDeltaToHtmlConverter } from 'quill-delta-to-html';
import InputError from '@/Components/Default/InputError.vue';
import { OrderedMap } from 'js-sdsl';
import PostCard from '@/Components/Member/Thread/PostCard.vue';
import { toast } from 'vue3-toastify';
import ActionMessage from '@/Components/Default/ActionMessage.vue';
import Textarea from '@/Components/Default/Textarea.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    topic: Object,
    thread: Object,
    isFollow: Boolean,
    pagePost: Number
});
const shortenQuillDeltaWithImmediateCheck = (delta, maxLength = 50, maxOps = 5) => {
    const shortenedOps = [];
    let totalLength = 0;
    let isUnchanged = true; // Default is same as original

    for (let i = 0; i < delta.ops.length && shortenedOps.length < maxOps; i++) {
        const op = delta.ops[i];
        const newOp = { ...op };

        // If insert is text and exceeds maxLength, truncate
        if (typeof op.insert === 'string' && !op.attributes?.['code-block']) {
            if (op.insert.length > maxLength) {
                newOp.insert = op.insert.substring(0, maxLength) + '...';
                isUnchanged = false; // Có cắt ngắn, không giống gốc
            }
        }

        // Ignore ops that only contain '\n' at the end if not needed
        if (op.insert === '\n' && i === delta.ops.length - 1 && !op.attributes) {
            isUnchanged = false; // Có op bị bỏ, không giống gốc
            continue;
        }

        shortenedOps.push(newOp);
        totalLength += typeof newOp.insert === 'string' ? newOp.insert.length : 0;

        // Stop if total length exceeds limit
        if (totalLength > maxLength * maxOps) {
            isUnchanged = false; // Vượt maxOps, không giống gốc
            break;
        }
    }

    // If the number of ops is less than the original, not the same as the original
    if (shortenedOps.length < delta.ops.length) {
        isUnchanged = false;
    }

    return { ops: shortenedOps, isUnchanged };
}
const normalizeDelta = (raw, isComment, post) => {
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

    if (isComment == 1) {
        const result = shortenQuillDeltaWithImmediateCheck(parsed, 255, 10);
        if (!result.isUnchanged) {
            return { ops: result.ops };
        } else {
            if (post) {
                post.showMore = 2;
            }
        }
    }

    return parsed;
}
const renderContent = (delta, isComment = 0, post = null) => {
    if (delta == '')
        return '';
    try {
        const normalized = normalizeDelta(delta, isComment, post);
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
const api = axios.create({
    baseURL: `http://localhost:8000`,
    withCredentials: true
})
const isFollow = ref(props.isFollow);
const toggleFollow = () => {
    api.post(route('threads.follow', props.thread.thread_id)).then(res => isFollow.value = !isFollow.value).catch();
}
const toolbarOptions = [
    ['bold', 'italic', 'underline', 'strike'],        // toggled buttons
    ['blockquote', 'code-block'],

    [{ 'header': 1 }, { 'header': 2 }],               // custom button values
    [{ 'list': 'ordered' }],
    [{ 'script': 'sub' }, { 'script': 'super' }],      // superscript/subscript
    [{ 'indent': '-1' }, { 'indent': '+1' }],          // outdent/indent
    [{ 'direction': 'rtl' }],                    // text direction

    [{ 'color': [] }, { 'background': [] }],          // dropdown with defaults from theme
    [{ 'font': [] }],
    [{ 'align': [] }],

    ['clean']                                         // remove formatting button
];
const parrentPostContent = ref(null);
const isReply = ref(false);
const quillOnMounted = ref(null);
const hljsOnMounted = ref(null);
const getPosts = (callback, page) => {
    api.get(route('posts.index', [props.thread.thread_id]) + '?page=' + page).then((response) => {
        callback(response.data);
    })
}
const posts = ref(null);
const contentOfPosts = ref([]);
const isHighlightPage = ref(false);
getPosts((data) => {
    if (isHighlightPage) {
        isHighlightPage.value = true
        posts.value = data;
        for (let index = 0; index < data['data'].length; index++) {
            const element = data['data'][index];
            contentOfPosts.value.push(element.content);
        }
    }
}, props.pagePost ?? 1);
const quillPosts = ref(new OrderedMap());

const editPost = (post, index, contentRef, editorRef, cancel = false) => {
    if (post.showMore == 3 || post.showMore == 4) {
        const isUnchanged = {
            showMore: 1
        };
        if (!cancel) {
            const newContent = quillPosts.value.getElementByKey(index).getContents();
            const newContentString = JSON.stringify(newContent)
            contentOfPosts.value[index] = newContent;
            contentRef.innerHTML = renderContent(newContent, 1, isUnchanged);
            api.put(route('posts.update', [props.thread.thread_id, post.post_id]), {
                'content': newContentString
            }).then((response) => {
                getPosts((data) => {
                    clearPostsAndReasign(posts, contentOfPosts, data);
                    toast('Upload Successfully', {
                        autoClose: 1000,
                        "theme": "auto",
                        "type": "success",
                        "dangerouslyHTMLString": true
                    });
                }, posts.value['current_page']);
            });
            if (hljsOnMounted.value) {
                const hljs = hljsOnMounted.value;
                const codes = contentRef.getElementsByTagName('code');
                for (let index = 0; index < codes.length; index++) {
                    const element = codes[index];
                    hljs.highlightElement(element);
                }
            }
            post.showMore = isUnchanged.showMore == 1 ? 1 : 2;
        } else {
            post.showMore = post.showMore == 4 ? 2 : 1;
        }
        if (editorRef) {
            editorRef.innerHTML = "";
        }
        return;
    }

    if (editorRef && quillOnMounted.value && hljsOnMounted.value && contentOfPosts.value) {
        const contentHtml = renderContent(contentOfPosts.value[index]);
        editorRef.innerHTML = "<div>" + contentHtml + "</div>";
        post.showMore = post.showMore == 2 ? 4 : 3;
        const hljs = hljsOnMounted.value;
        const quill = new quillOnMounted.value(editorRef.lastChild, {
            theme: 'snow',
            modules: {
                syntax: { hljs },
                toolbar: toolbarOptions,
            },
        });

        quillPosts.value.setElement(index, quill);
    }
}
const showMorePost = (post, index, contentRef) => {
    post.showMore = (post.showMore == 1) ? post.showMore - 1 : post.showMore + 1;
    contentRef.innerHTML = renderContent(contentOfPosts.value[index], post.showMore, post);
    if (hljsOnMounted.value) {
        const hljs = hljsOnMounted.value;
        const preTags = contentRef.getElementsByTagName('code');
        for (let index = 0; index < preTags.length; index++) {
            const element = preTags[index];
            hljs.highlightElement(element);
        }
    }
}
const postParrent = ref(null);
const closeModelDeleteCategory = ref(null);
const destroyPost = () => {
    if (postParrent.value) {
        if (postParrent.value.post_id) {
            api.delete(route('posts.destroy', [props.thread.thread_id, postParrent.value.post_id])).then((response) => {
                const data = response.data;
                clearPostsAndReasign(posts, contentOfPosts, data);
                closeModelDeleteCategory.value.click();
                isReply.value = false;
                postParrent.value = null;
                parrentPostContent.value.innerHTML = '';
                toast('Deleted !', {
                    autoClose: 1000,
                    "theme": "auto",
                    "type": "success",
                    "dangerouslyHTMLString": true
                });
            });
        }
    }
}
const accessKey = (post) => {
    postParrent.value = post;
}
const reply = (post, index) => {
    isReply.value = true;
    accessKey(post);
    parrentPostContent.value.innerHTML = renderContent(contentOfPosts.value[index], 1);
    if (hljsOnMounted.value) {
        const hljs = hljsOnMounted.value;
        const codes = parrentPostContent.value.getElementsByTagName('code');
        for (let index = 0; index < codes.length; index++) {
            const element = codes[index];
            hljs.highlightElement(element);
        }
    }
}
const cancelReply = () => {
    isReply.value = false;
    postParrent.value = null;
    parrentPostContent.value.innerHTML = '';
}
onMounted(() => {
    Promise.all([
        import('quill'),
        import('highlight.js/lib/common')
    ]).then(([QuillModule, highlightModule]) => {
        quillOnMounted.value = QuillModule.default;
        hljsOnMounted.value = highlightModule.default;
        const hljs = hljsOnMounted.value;
        editor.value = new quillOnMounted.value('#editor', {
            theme: 'snow',
            modules: {
                syntax: { hljs },
                toolbar: toolbarOptions,
            },
        });
        hljs.highlightAll();
        setInterval(() => {
            getPosts((data) => {
                clearPostsAndReasign(posts, contentOfPosts, data);
            }, posts.value['current_page']);
        }, 60000);
    });
});

const validationErrors = ref({
    content: null,
    parrent: null
})
const submit = () => {
    const contentJson = JSON.stringify(editor.value.getContents());
    api.post(route('posts.store', props.thread.thread_id), {
        content: contentJson,
        parrent_id: (postParrent.value) ? postParrent.value.post_id : null
    }).then((response) => {
        getPosts((data) => {
            isReply.value = false;
            postParrent.value = null;
            parrentPostContent.value.innerHTML = '';
            editor.value.setContents([{ insert: '\n' }]);
            clearPostsAndReasign(posts, contentOfPosts, data);
        }, posts.value['current_page']);
        toast('Upload Successfully', {
            autoClose: 1000,
            "theme": "auto",
            "type": "success",
            "dangerouslyHTMLString": true
        });
        validationErrors.value.content = null;
    }).catch(function (error) {
        validationErrors.value.content = error.response.data.errors.content[0]
    });
}
const wantSetCurrentPage = ref(false);
const pageUserWant = ref(0)
const maxPage = ref(0);
const nextPost = () => {
    const page = (posts.value['current_page'] < maxPage.value || maxPage.value == 0) ? posts.value['current_page'] + 1 : posts.value['current_page'];
    if (page == posts.value['current_page'] + 1 && page != maxPage.value) {
        getPosts((data) => {
            if (data['data'].length == 0) {
                maxPage.value = data['current_page'];
            } else {
                clearPostsAndReasign(posts, contentOfPosts, data);
            }
        }, page)
    }
}
const previousPost = () => {
    const page = (posts.value['current_page'] - 1) <= 0 ? 0 : posts.value['current_page'] - 1;
    if (page > 0) {
        getPosts((data) => {
            clearPostsAndReasign(posts, contentOfPosts, data);
        }, page)
    }
}
const setPagePost = () => {
    if (pageUserWant.value != posts.value['current_page'] && !isNaN(Number(pageUserWant.value)) && Number(pageUserWant.value) != 0) {
        getPosts((data) => {
            clearPostsAndReasign(posts, contentOfPosts, data);
        }, pageUserWant.value <= 0 ? 1 : pageUserWant.value);
    }
    wantSetCurrentPage.value = !wantSetCurrentPage.value;
}
const clearPostsAndReasign = (posts, contentOfPosts, data) => {
    contentOfPosts.value.length = 0;
    posts.value.length = 0;
    posts.value['data'].length = 0;
    posts.value['current_page'] = data['current_page'];
    posts.value['first_page_url'] = data['first_page_url'];
    posts.value['next_page_url'] = data['next_page_url'];
    for (let index = 0; index < data['data'].length; index++) {
        const element = data['data'][index];
        posts.value['data'].push(element);
        contentOfPosts.value.push(element.content);
    }
}

const isVisible = ref(false);
const isMouseOverTrigger = ref(false);
const isMouseOverPopover = ref(false);
const showPopover = async () => {
    isMouseOverTrigger.value = true;
    if (!isVisible.value) { // Chỉ hiển thị nếu nó chưa hiển thị
        isVisible.value = true;
        await nextTick(); // Chờ popover được render vào DOM

    }
};
const handleMouseLeaveTrigger = () => {
    isMouseOverTrigger.value = false;
    // Nếu chuột không còn ở trên trigger và không ở trên popover, thì ẩn
    setTimeout(() => { // Dùng setTimeout để cho phép di chuyển chuột giữa trigger và popover
        if (!isMouseOverTrigger.value && !isMouseOverPopover.value) {
            isVisible.value = false;
        }
    }, 100); // Một khoảng thời gian nhỏ (ms)
};
const handleMouseEnterPopover = () => {
    isMouseOverPopover.value = true;
};
const handleMouseLeavePopover = () => {
    isMouseOverPopover.value = false;
    // Nếu chuột không còn ở trên trigger và không ở trên popover, thì ẩn
    setTimeout(() => { // Dùng setTimeout để cho phép di chuyển chuột giữa trigger và popover
        if (!isMouseOverTrigger.value && !isMouseOverPopover.value) {
            isVisible.value = false;
        }
    }, 100); // Một khoảng thời gian nhỏ (ms)
};

const like = (threadId) => {
    const url = route('likes.threads', threadId);
    api.post(url).then((res) => {
        props.thread.isLikedByUser = !props.thread.isLikedByUser
        if (props.thread.isLikedByUser)
            props.thread.totalLikes += 1;
        else
            props.thread.totalLikes -= 1;
    }).catch((error) => {
        props.thread.totalLikes -= 1;
    });
}


const reason = ref('');
const submitReport = () => {
    console.log(reason.value, props.thread.thread_id);
    api.post(route('reporst.store'), {
        reason: reason.value,
        thread_id: props.thread.thread_id
    }).then((res) => {
        console.log(res);
        toast(res.data.message, {
            autoClose: 250,
            "theme": "auto",
            "type": "success",
            "dangerouslyHTMLString": true
        });
    }).catch((err) => {
        console.log(err);
        toast(err.response.data.message, {
            autoClose: 250,
            "theme": "auto",
            "type": "error",
            "dangerouslyHTMLString": true
        });
    });
}

</script>

<template>
    <DefaultLayout :title="thread.slug">
        <template #absolute-body>
            <dialog id="modal_delete_post" class="modal">
                <div class="modal-box">
                    <div class="flex flex-col items-center">
                        <h3 class="font-bold text-lg mb-4">Delete Post</h3>
                        <p>Are you sure you want to delete this Post?</p>
                    </div>
                    <div class="modal-action inline-flex items-center justify-center w-full">
                        <button class="btn btn-sm btn-soft btn-error me-2" @click="destroyPost">
                            Delete
                        </button>
                        <form method="dialog">
                            <!-- if there is a button in form, it will close the modal -->
                            <button type="submit" class="btn btn-sm btn-soft"
                                ref="closeModelDeleteCategory">Close</button>
                        </form>
                    </div>
                </div>
            </dialog>
            <dialog id="modal_report" class="modal">
                <div class="modal-box">
                    <div class="flex flex-col items-center">
                        <h3 class="font-bold text-lg mb-4">Report</h3>
                        <div class="flex flex-col items-start justify-between h-full w-full">
                            <div class="w-full grow overflow-scroll p-2">
                                <p>Reason</p>
                                <Textarea id="reason" v-model="reason" type="text" class="mt-1 block w-full" required
                                    autocomplete="reason" />
                            </div>
                            <div class="inline-flex items-center p-2">
                                <button @click="submitReport" type="submit" class="btn btn-sm btn-soft">
                                    Submit
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-action inline-flex items-center justify-center w-full">
                        <form method="dialog">
                            <!-- if there is a button in form, it will close the modal -->
                            <button type="submit" class="btn btn-sm btn-soft">Close</button>
                        </form>
                    </div>
                </div>
            </dialog>
        </template>
        <template #search></template>
        <template #body>
            <div class="breadcrumbs text-sm inline-flex items-center justify-between">
                <ul>
                    <li><a>Explore</a></li>
                    <li><a>{{ topic.title }}</a></li>
                </ul>

                <button @click="toggleFollow" class="btn btn-soft btn-sm">
                    {{ isFollow ? 'Unfavorite' : 'Favorite' }}
                </button>
            </div>
            <div class="flex flex-col p-2 bg-base-200 text-base-content relative">
                <div class="w-6 inline-flex items-start">
                    <img @mouseenter="showPopover" @mouseleave="handleMouseLeaveTrigger"
                        class="rounded-full hover:border cursor-pointer" alt="avatar"
                        :src="thread.user.profile_photo_path" />
                    <div class="inline-flex flex-col ms-2">
                        <div @mouseenter="showPopover" @mouseleave="handleMouseLeaveTrigger"
                            class="font-semibold cursor-pointer hover:underline underline-offset-1">{{ thread.user.name
                            }}</div>
                        <p class="text-xs">{{ thread.updated_at }}</p>
                    </div>
                </div>
                <div v-show="isVisible && $page.props.auth.user" @mouseenter="handleMouseEnterPopover"
                    @mouseleave="handleMouseLeavePopover"
                    class="absolute bg-base-200 border border-gray-300 rounded-lg shadow-xl p-4 z-100 transition-opacity duration-200 top-10">
                    <div class="flex gap-2 items-center cursor-pointer">
                        <img :src="thread.user.profile_photo_path" alt="avatar"
                            class="w-10 h-10 rounded-full hover:border curosr-pointer">
                        <h3 class="font-bold text-lg mb-2">{{ thread.user.name }}</h3>
                    </div>
                    <p class="text-sm mt-2">Joined on</p>
                    <p class="text-sm">{{ thread.user.created_at }}</p>
                    <button class="btn btn-sm mt-3">Send Message</button>
                </div>
                <div v-if="$page.props.auth.user != null && $page.props.auth.user.user_id == thread.user.user_id" class="mt-2">
                    <Link :href="route('threads.edit', thread.thread_id)" class="btn btn-sm btn-primary">
                    Edit
                    </Link>
                </div>
                <div class="m-2 font-bold text-lg">
                    <span>{{ thread.title }}</span>
                </div>
                <div class="flex gap-2">
                    <span v-for="tag in thread.tags" class="badge badge-primary">{{ tag.name }}</span>
                </div>
                <div class="nakedScope mx-2 mt-2" v-html="renderContent(thread.content)"></div>
                <div class="flex flex-col justify-center items-start gap-2 mt-4">
                    <button @click="like(thread.thread_id)" :class="{ 'btn-soft': !thread.isLikedByUser }"
                        class="btn btn-sm btn-primary">Like</button>
                    <div class="text-xs">{{ thread.totalLikes }} Likes</div>
                </div>
                <form id="comment" @submit.prevent="submit" class="flex flex-col" action="">
                    <div :class="{ 'p-2': isReply, 'border': isReply }" class="flex flex-col my-4 gap-2">
                        <div v-if="isReply && postParrent" class="flex items-start">
                            <img class="rounded-full hover:border max-w-10" alt="Tailwind CSS Navbar component"
                                :src="postParrent.user.profile_photo_path" />
                            <div class="inline-flex flex-col ms-2 ">
                                <span class="font-semibold text-sm max-w-[10rem] max-h-[1.25rem] overflow-x-auto">{{
                                    postParrent.user.name
                                }}</span>
                                <p class="text-xs">{{ postParrent.created_at }}</p>
                            </div>
                        </div>
                        <div class="nakedScope" ref="parrentPostContent">
                        </div>
                    </div>

                    <div class="mb-2">
                        <div id="editor">
                        </div>
                        <InputError class="mt-2" :message="validationErrors.content" />
                    </div>
                    <div class="flex justify-between">
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-sm">
                                Comment
                            </button>
                            <span @click="cancelReply" v-if="isReply" class="btn btn-sm btn-error">
                                Cancel
                            </span>
                        </div>
                        <div>
                            <span onclick="modal_report.showModal()" class="btn btn-sm btn-error">
                                Report
                            </span>
                        </div>
                    </div>

                </form>
            </div>
            <!-- <div v-if="posts">
                {{ posts['data'] }}
            </div> -->

            <div class="flex flex-col gap-4 mt-4">
                <PostCard :post="post" v-for="(post, index) in (posts && posts['data']) ? posts['data'] : []"
                    :hljs="hljsOnMounted" @editPost="editPost" @accessKey="accessKey(post)"
                    :contentHtml="renderContent(post.content, 1, post)"
                    :contentParrentHtml="renderContent((post.parrent) ? post.parrent.content : '', 1)"
                    @showMorePost="showMorePost" :index="index" @reply="reply" />
            </div>

            <div class="mt-4 mb-2 flex justify-center items-center gap-2" :class="{ 'hidden': !wantSetCurrentPage }">
                <input type="text" placeholder="Page" class="input input-sm w-20" v-model="pageUserWant" />
                <button @click="setPagePost" class="btn btn-sm btn-accent">Confirm</button>
            </div>
            <div :class="{ 'mt-4': !wantSetCurrentPage }" class="flex justify-center">
                <div v-if="(posts && posts['data'])" class="join">
                    <button @click="previousPost" class="join-item btn">«</button>
                    <button @click="wantSetCurrentPage = !wantSetCurrentPage" class="join-item btn">{{
                        posts['current_page'] }}</button>
                    <button @click="nextPost" class="join-item btn">»</button>
                </div>
            </div>

        </template>
    </DefaultLayout>
</template>
