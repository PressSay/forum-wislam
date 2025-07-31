<script setup>
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import { ref, onBeforeMount, onMounted, onUnmounted, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import { io } from 'socket.io-client';
import('vue3-toastify/dist/index.css');
import { debounce } from 'lodash';

const socket = io('http://192.168.1.6:3000', {
    withCredentials: true,
});

const props = defineProps({
    messages: Object,
    conversation: Object,
    user: Object,
    isBlocked: Boolean
});

const currentPageMessage = ref(1);
const isBlocked = ref(props.isBlocked);
const messageContainer = ref(null);
const isLoadingMoreMessages = ref(false); // New: Add a loading flag
const messages = ref(props.messages);
const showChatNavigator = ref(false);
const isDesktop = ref(true);
const isUserOnline = ref(false); // Thêm biến trạng thái online
const hasMoreMessages = ref(true); // New: Track if there are more messages to load


const checkScreenSize = () => {
    if (window.innerWidth < 768) {
        isDesktop.value = false; // Mobile
    } else {
        isDesktop.value = true;  // Desktop
    }
};

onBeforeMount(() => {
    checkScreenSize();
})
onMounted(() => {
    window.addEventListener('resize', checkScreenSize);
    // Join conversation room when component is mounted
    if (props.conversation?.conversation_id) {
        socket.emit('join_conversation', props.conversation.conversation_id);
        socket.emit('mark_as_read', props.conversation.conversation_id);
        socket.emit('check_online', {
            conversation_id: props.conversation.conversation_id,
            user_id: props.user.user_id
        });
    }

    // Listen for new messages from Socket.IO
    socket.on('new_message', (message) => {
        if (message.conversation_id === props.conversation?.conversation_id) {
            messages.value.push({
                user_id: message.user_id,
                content: message.content,
                conversation_id: message.conversation_id,
                updated_at: message.created_at,
                profile_photo_path: message.profile_photo_path,
                is_read: message.is_read
            });
            // Cập nhật trạng thái online từ online_users
            isUserOnline.value = message.online_users.includes(props.user.user_id);
        }
        nextTick(() => {
            messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
        });
    });

    socket.on('messages_read', ({ conversation_id }) => {
        if (conversation_id === props.conversation?.conversation_id) {
            messages.value = messages.value.map(message => ({
                ...message,
                is_read: message.user_id !== props.user.user_id ? true : message.is_read
            }));
        }
    });

    socket.on('user_status', ({ user_id, online, online_users }) => {
        isUserOnline.value = online_users.includes(props.user.user_id);
    });

    messageContainer.value.scrollTop = messageContainer.value.scrollHeight;

    // New: Add scroll event listener
    if (messageContainer.value) {
        messageContainer.value.addEventListener('scroll', handleScroll);
    }
});
onUnmounted(() => {
    window.removeEventListener('resize', checkScreenSize);
    // Disconnect Socket.IO
    socket.disconnect();
    // New: Remove scroll event listener
    if (messageContainer.value) {
        messageContainer.value.removeEventListener('scroll', handleScroll);
    }
});


const api = axios.create({
    baseURL: `http://localhost:8000`,
    withCredentials: true
});

const getUsers = (callback, page = 1, search = '') => {
    const url = route('conversations.users') + '?page=' + page + '&search=' + search;
    api.get(url).then((response) => {
        callback(response.data);
    });
}

const getMoreMessages = () => {
    if (isLoadingMoreMessages.value || !hasMoreMessages.value) return;
    isLoadingMoreMessages.value = true; // Set loading flag

    const oldScrollHeight = messageContainer.value.scrollHeight;

    api.get(route('conversations.show', props.conversation.conversation_id) + '?page=' + (currentPageMessage.value + 1)) // Increment current page
        .then((response) => {
            const newMessages = response.data;
            if (newMessages.length > 0) {
                // Prepend new messages to the existing ones
                messages.value = newMessages.concat(messages.value);
                currentPageMessage.value += 1; // Increment page only if new messages are loaded

                nextTick(() => {
                    // Maintain scroll position after loading more messages
                    messageContainer.value.scrollTop = messageContainer.value.scrollHeight - oldScrollHeight;
                });
            } else {
                // If no more messages are loaded, set hasMoreMessages to false
                hasMoreMessages.value = false;
            }
        })
        .catch(error => {
            console.error("Error fetching more messages:", error);
            toast('Error loading more messages', {
                autoClose: 300,
                theme: "auto",
                type: "error",
                dangerouslyHTMLString: true
            });
        })
        .finally(() => {
            isLoadingMoreMessages.value = false; // Reset loading flag
        });
}

// New: Handle scroll event to load more messages
const handleScroll = () => {
    if (messageContainer.value.scrollTop === 0 && !isLoadingMoreMessages.value) {
        getMoreMessages();
    }
};

const valueSearchUser = ref('');
const searchUserChatDebounce = debounce(function (value) {
    getUsers((data) => {
        if (selfUser.value == null)
            selfUser.value = data.selfUser;
        users.value = data.otherUser;
        valueSearchUser.value = value;
    }, 1, value);
}, 500);

const searchUserChat = (value) => {
    searchUserChatDebounce(value);
}

const users = ref(null);
const selfUser = ref(null);

getUsers((data) => {
    if (selfUser.value == null)
        selfUser.value = data.selfUser;
    users.value = data.otherUser;
});
const getConversation = (user) => {
    if (user.conversation_id == '') {
        api.post(route('conversations.store'), {
            'user_id': user.user_id
        }).then((response) => {
            if (window.location.href != route('conversations.show', user.conversation_id))
                router.visit(route('conversations.show', response.data.conversation_id));
        });
    } else if (window.location.href != route('conversations.show', user.conversation_id))
        router.visit(route('conversations.show', user.conversation_id));
}

const content = ref('');
const submit = () => {
    const objectSubmit = {
        'conversation_id': props.conversation.conversation_id,
        'content': content.value,
        'user_id': props.user.user_id
    };
    api.post(route('conversations.store', objectSubmit)).then((_) => {
        toast('Upload Successfully', {
            autoClose: 300,
            "theme": "auto",
            "type": "success",
            "dangerouslyHTMLString": true
        });
        content.value = '';
    }).catch((error) => {
        toast('Error sending message', {
            autoClose: 300,
            theme: "auto",
            type: "error",
            dangerouslyHTMLString: true
        });
    });
}
const closeModelDeleteCategory = ref(null);
const blockConversation = () => {
    api.post(route('conversations.toggleBlock'), {
        'blocked_user_id': props.user.user_id
    }).then((_) => {
        toast('Upload Successfully', {
            autoClose: 300,
            "theme": "auto",
            "type": "success",
            "dangerouslyHTMLString": true
        });
        isBlocked.value = !isBlocked.value;
        closeModelDeleteCategory.value.click();
    }).catch((error) => {
        toast('Error in processing', {
            autoClose: 300,
            theme: "auto",
            type: "error",
            dangerouslyHTMLString: true
        });
    });
}

const currentPageUser = ref(1);
const maxPage = ref(0);
const nextUserChat = () => {
    const page = (currentPageUser.value < maxPage.value || maxPage.value == 0) ? currentPageUser.value + 1 : currentPageUser.value;

    if (page == currentPageUser.value + 1 && page != maxPage.value) {
        getUsers((data) => {
            if (data.otherUser['data'].length == 0) {
                maxPage.value = page - 1;
            } else {
                if (selfUser.value == null)
                    selfUser.value = data.selfUser;
                users.value = data.otherUser;
                currentPageUser.value = page;
            }
        }, page, valueSearchUser.value);
    }
}
const previousUserChat = () => {
    const page = (currentPageUser.value - 1) <= 0 ? 0 : currentPageUser.value - 1;
    if (page > 0) {
        currentPageUser.value = page;
        getUsers((data) => {
            if (selfUser.value == null)
                selfUser.value = data.selfUser;
            users.value = data.otherUser;
        }, page, valueSearchUser.value);
    }
}
</script>

<template>
    <DefaultLayout>
        <template #absolute-body>
            <dialog id="modal_block_conversation" class="modal">
                <div class="modal-box">
                    <div class="flex flex-col items-center">
                        <h3 class="font-bold text-lg mb-4">Block Conversation</h3>
                        <p>Are you sure you want to delete this Conversation?</p>
                    </div>
                    <div class="modal-action inline-flex items-center justify-center w-full">
                        <button class="btn btn-sm me-2" :class="{ 'btn-error': !isBlocked, 'btn-primary': isBlocked }"
                            @click="blockConversation">
                            {{ isBlocked ? 'Unblock' : 'Block' }}
                        </button>
                        <form method="dialog">
                            <!-- if there is a button in form, it will close the modal -->
                            <button type="submit" class="btn btn-sm btn-success"
                                ref="closeModelDeleteCategory">Cancel</button>
                        </form>
                    </div>
                </div>
            </dialog>
        </template>
        <template #search></template>
        <template #search-mobile></template>
        <template #body>
            <div class="flex flex-row-reverse w-full h-[90vh] justify-between gap-2 mt-4">
                <div v-show="isDesktop || showChatNavigator"
                    class="w-fit flex flex-col gap-2 w-full md:max-w-[17rem] md:min-w-[17rem] pt-4 pb-10 px-2 bg-base-200 text-left capitalize font-medium shadow-lg top-0 right-0 overflow-scroll">
                    <div v-show="!isDesktop" class="p-2 bg-base-300">
                        <button @click="showChatNavigator = !showChatNavigator"
                            class="btn btn-sm btn-accent btn-outline rounded-full w-8 h-8">
                            <i class="fa-solid fa-backward"></i>
                        </button>
                    </div>
                    <label class="input w-full">
                        <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none"
                                stroke="currentColor">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.3-4.3"></path>
                            </g>
                        </svg>
                        <input @input="searchUserChat($event.target.value)" type="search" class="grow"
                            placeholder="Search" />
                    </label>
                    <div class="flex justify-center">
                        <div class="join">
                            <button @click="previousUserChat" class="join-item btn btn-sm">«</button>
                            <button class="join-item btn btn-sm">{{ currentPageUser }}</button>
                            <button @click="nextUserChat" class="join-item btn btn-sm">»</button>
                        </div>
                    </div>
                    <div v-if="selfUser"
                        class="flex gap-2 bg-base-300 p-2 border rounded-sm cursor-pointer hover:bg-base-200"
                        @click="getConversation(selfUser)">
                        <div class="min-w-10 min-h-10">
                            <img class="w-10 h-10 rounded-full object-cover" :src="selfUser.profile_photo_path"
                                alt="704513">
                        </div>
                        <div class="flex flex-col gap-1 grow">
                            <div class="flex justify-between w-full">
                                <span class="font-bold text-sm max-w-[10rem]">{{ selfUser.name }}</span>
                            </div>
                            <div class="font-thin text-xs max-h-[1.25rem] overflow-hidden">
                                <span>{{ selfUser.last_message_content }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="users" v-for="userItem in users['data']"
                        class="flex gap-2 bg-base-300 p-2 border rounded-sm cursor-pointer hover:bg-base-200"
                        @click="getConversation(userItem)">
                        <div class="min-w-10 min-h-10">
                            <img class="w-10 h-10 rounded-full object-cover" :src="userItem.profile_photo_path"
                                alt="704513">
                        </div>
                        <div class="flex flex-col gap-1 grow">
                            <div class="flex justify-between w-full">
                                <span class="font-bold text-sm max-w-[10rem]">{{ userItem.name }}</span>
                            </div>
                            <div class="font-thin text-xs max-h-[1.25rem] overflow-hidden">
                                <span>{{ userItem.last_message_content }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- content -->
                <div v-show="isDesktop || !showChatNavigator" class="flex flex-col grow">
                    <div class="basis-1/14 flex items-center justify-between p-2 bg-base-200">
                        <div v-if="conversation && user" class="flex items-center">
                            <div class="max-w-10 max-h-10">
                                <img class="w-10 h-10 object-cover rounded-full" :src="user.profile_photo_path"
                                    alt="704513">
                            </div>
                            <div class="flex flex-col ms-2 max-w-[10rem] overflow-hidden">
                                <span class="font-semibold text-sm">{{ user.name }}</span>
                                <span class="font-bold text-xs"
                                    :class="isUserOnline ? 'text-success' : 'text-gray-500'">
                                    {{ isUserOnline ? 'Online' : 'Offline' }}
                                </span>
                            </div>
                        </div>
                        <div v-else>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="modal_block_conversation.showModal()" v-if="conversation != null"
                                class="btn btn-sm btn-error btn-outline rounded-full w-8 h-8"
                                :class="{ 'btn-error': !isBlocked, 'btn-primary': isBlocked }">
                                <i v-if="!isBlocked" class="fa-regular fa-hand"></i>
                                <i v-else class="fa-solid fa-key"></i>
                            </button>
                            <div v-else class="w-8 h-8">
                            </div>
                            <button v-show="!isDesktop" @click="showChatNavigator = !showChatNavigator"
                                class="btn btn-sm btn-accent btn-outline rounded-full w-8 h-8">
                                <i class="fa-solid fa-forward"></i>
                            </button>
                        </div>
                    </div>
                    <div ref="messageContainer" class="basis-10/12 overflow-scroll py-2">
                        <div v-if="conversation != null">
                            <div v-for="message in messages"
                                :class="{ 'chat-end': message.user_id == $page.props.auth.user.user_id, 'chat-start': message.user_id != $page.props.auth.user.user_id }"
                                class="chat">
                                <div class="chat-image avatar">
                                    <div class="w-10 rounded-full">
                                        <img alt="Tailwind CSS chat bubble component"
                                            :src="message.profile_photo_path" />
                                    </div>
                                </div>
                                <div class="chat-header">
                                    <time class="text-xs opacity-50">{{ message.updated_at }}</time>
                                </div>
                                <div class="chat-bubble">{{ message.content }}</div>
                                <div class="chat-footer opacity-50">{{ message.is_read ? 'Read' : 'Delivered' }}</div>
                            </div>
                        </div>
                        <div v-else class="w-full h-full flex justify-center items-center">
                            <span class="opacity-50">Empty</span>
                        </div>
                    </div>
                    <form @submit.prevent="submit"
                        class="basis-1/14 flex gap-2 justify-center items-center bg-base-200 p-2">
                        <button disabled type="button" class="btn">
                            <i class="fa-solid fa-upload"></i>
                        </button>
                        <button disabled type="button" class="btn">
                            <i class="fa-solid fa-image"></i>
                        </button>
                        <input v-model="content" :disabled="conversation == null" type="text" placeholder="Type here"
                            class="input grow" />
                        <button type="submit" :disabled="conversation == null" class="btn btn-primary btn-outline">
                            Send
                        </button>
                    </form>
                </div>
            </div>
        </template>
    </DefaultLayout>
</template>