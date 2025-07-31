<script setup>
/* 
DefaultLayout
This is a main layout
Includes notification bar, Navigation, Header, Body
*/
import { ref, onMounted, onUnmounted, onBeforeMount } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import Banner from '@/Components/Default/Banner.vue';
import Dropdown from '@/Components/Default/Dropdown.vue';
import DropdownLink from '@/Components/Default/DropdownLink.vue';

const props = defineProps({
    title: String,
});

const api = axios.create({
    baseURL: `http://localhost:8000`,
    withCredentials: true
});

const logout = () => {
    router.post(route('logout'));
};

const showingNavigation = ref(false);
const isDesktop = ref(false);

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
});

onUnmounted(() => {
    window.removeEventListener('resize', checkScreenSize);
});

const countUnreadNotification = ref(0);
const notifications = ref(null);
const getNotifications = (callback, page) => {
    api.get(route('notifications.index') + '?page=' + page).then((res) => {
        callback(res.data);
    });
}
const pageNotification = ref(1);
getNotifications((data) => {
    notifications.value = data.data;
}, pageNotification.value);
const maxPageNotifications = ref(0);
const nextNotification = () => {
    const page = (notifications.value['current_page'] < maxPageNotifications.value || maxPageNotifications.value == 0) ? notifications.value['current_page'] + 1 : notifications.value['current_page'];
    if (page == notifications.value['current_page'] + 1 && page != maxPageNotifications.value) {
        getNotifications((data) => {
            if (data.data['data'].length == 0) {
                maxPageNotifications.value = data.data['current_page'];
            } else {
                notifications.value = data.data;
                pageNotification.value += 1;
            }
        }, page);
    }
}
const previousNotification = () => {
    const page = (notifications.value['current_page'] - 1) <= 0 ? 0 : notifications.value['current_page'] - 1;
    if (page > 0) {
        getNotifications((data) => {
            notifications.value = data.data;
            pageNotification.value -= 1;
        }, page);
    }
}
const getAmountNotification = (callback) => {
    api.get(route('notification.countUnread')).then((res) => {
        callback(res.data);
    });
}
getAmountNotification((data) => {
    countUnreadNotification.value = data.data.unread_notifications;
});
const markAsRead = (item) => {
    api.post(route('notifications.markAsRead'), {
        notification_ids: [
            item.notification_id
        ]
    }).then((res) => {
        window.location.href = item.link;
    });
}
const clearNotification = (item) => {
    api.delete(route('notifications.destroy'), {
        data: {
            notification_ids: [
                item.notification_id
            ]
        }
    }).then((res) => {
        getNotifications((data) => {
            notifications.value = data.data;
        }, pageNotification.value);
    });
}

</script>


<template>

    <Head :title="title" />

    <Banner />

    <div class="h-screen w-full xl:mx-auto xl:w-[80rem] 2xl:w-[96rem] flex flex-col relative">
        <slot name="absolute-body">

        </slot>

        <div class="grow flex flex-row-reverse items-start relative">
            <!-- body -->
            <div class="flex flex-col-reverse grow mx-1 relative">
                <div class="flex flex-col">
                    <slot name="body"></slot>
                </div>
                <!-- Top navigation-->
                <header class="sticky w-full top-0 grow">
                    <div class="flex items-center justify-between bg-base-300 mb-1 py-2 ">
                        <!-- Search -->
                        <div class="flex items-center ms-4">
                            <button v-show="!isDesktop" @click="showingNavigation = !showingNavigation"
                                class="cursor-pointer">
                                <i class="fa-solid fa-bars"></i>
                            </button>
                            <slot name="search"></slot>
                        </div>
                        <!-- Profile logout -->
                        <div class="flex items-center me-4">
                            <div v-if="$page.props.auth.user" class="dropdown dropdown-end">
                                <div tabindex="0" role="button" class="w-8 relative cursor-pointer hover:text-primary">
                                    <i
                                        class="w-8 bg-base-100  rounded-full p-2 fa-solid fa-bell hover:bg-primary-content"></i>
                                    <span v-if="countUnreadNotification > 0"
                                        class="absolute right-0 top-0 -mt-2 -mr-1 text-xs bg-warning text-warning-content font-medium px-2 rounded-full">{{
                                            countUnreadNotification > 0 ? countUnreadNotification : '' }}</span>
                                </div>
                                <ul tabindex="0"
                                    class="dropdown-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
                                    <li v-if="notifications != null" v-for="item in notifications.data">
                                        <div class="flex justify-center">
                                            <div @click="markAsRead(item)">
                                                {{ item.content }}
                                            </div>
                                            <button @click="clearNotification(item)" class="btn btn-sm btn-error"><i
                                                    class="fa-solid fa-trash"></i></button>
                                        </div>

                                    </li>
                                    <div class="flex justify-center mt-2">
                                        <div class="join">
                                            <button @click="previousNotification"
                                                class="join-item btn btn-xs">«</button>
                                            <button class="join-item btn btn-xs">{{ pageNotification }}</button>
                                            <button @click="nextNotification" class="join-item btn btn-xs">»</button>
                                        </div>
                                    </div>
                                </ul>

                            </div>
                            <div class="ms-3 relative">
                                <Dropdown v-if="$page.props.auth.user" width="48">
                                    <template #trigger>
                                        <button v-if="$page.props.jetstream.managesProfilePhotos"
                                            class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                            <img class="size-8 rounded-full object-cover"
                                                :src="$page.props.auth.user.profile_photo_url"
                                                :alt="$page.props.auth.user.name">
                                        </button>

                                        <span v-else class="inline-flex rounded-md">
                                            <button type="button"
                                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}

                                                <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg"
                                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <!-- Account Management -->
                                        <div class="block px-4 py-2 text-xs">
                                            Manage Account
                                        </div>

                                        <DropdownLink :href="route('profile.show')">
                                            Profile
                                        </DropdownLink>

                                        <DropdownLink v-if="$page.props.jetstream.hasApiFeatures"
                                            :href="route('api-tokens.index')">
                                            API Tokens
                                        </DropdownLink>

                                        <div class="border-t border-base-content" />

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                Log Out
                                            </DropdownLink>
                                        </form>

                                    </template>
                                </Dropdown>
                                <div v-else class="flex flex-items">
                                    <Link :href="route('login')"
                                        class="rounded-md px-3 py-2 text-base-content ring-1 ring-transparent transition hover:text-primary-content focus:outline-none focus-visible:ring-[#FF2D20]">
                                    Log in
                                    </Link>

                                    <Link :href="route('register')"
                                        class="rounded-md px-3 py-2 text-base-content ring-1 ring-transparent transition hover:text-primary-content focus:outline-none focus-visible:ring-[#FF2D20]">
                                    Register
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
            </div>

            <div v-show="showingNavigation && !isDesktop" @click="showingNavigation = !showingNavigation"
                class="w-screen h-screen absolute"></div>
            <!-- Dashboard Navigate -->
            <transition enter-active-class="transition ease-out duration-300"
                enter-from-class="transform -translate-x-full opacity-0"
                enter-to-class="transform translate-x-0 opacity-100"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="transform translate-x-0 opacity-100"
                leave-to-class="transform -translate-x-full opacity-0">
                <div v-show="showingNavigation || isDesktop"
                    class="h-screen w-fit fixed md:sticky lg:max-w-[14.25rem] py-4 px-2 text-base-content bg-base-300 text-left capitalize font-medium shadow-lg left-0 top-0">
                    <slot name="search-mobile"></slot>
                    <Link :href="route('dashboard')"
                        v-if="$page.props.auth.user && $page.props.auth.user.role == 'Admin'"
                        class="cursor-pointer px-2 py-1 md:hover:bg-primary-content hover:text-primary rounded flex items-center mb-5">
                    <i class="w-8 bg-base-100 rounded-full p-2 fa-solid fa-table-columns">
                    </i>
                    <span class="mx-2 inline-block sm:hidden lg:inline-block">Dashboard</span>
                    </Link>
                    <Link :href="route('categories.index')"
                        v-if="$page.props.auth.user && $page.props.auth.user.role == 'Admin'"
                        class="cursor-pointer px-2 py-1 md:hover:bg-primary-content hover:text-primary rounded flex items-center mb-5">
                    <i class="w-8 bg-base-100 rounded-full p-2 fa-solid fa-list"></i>
                    <span class="mx-2 inline-block sm:hidden lg:inline-block">Categories</span>
                    </Link>
                    <Link :href="route('tags.index')"
                        v-if="$page.props.auth.user && $page.props.auth.user.role == 'Admin'"
                        class="cursor-pointer px-2 py-1 md:hover:bg-primary-content hover:text-primary rounded flex items-center mb-5">
                    <i class="w-8 bg-base-100 rounded-full p-2 fa-solid fa-tags">
                    </i>
                    <span class="mx-2 inline-block sm:hidden lg:inline-block">Tags</span>
                    </Link>
                    <Link :href="route('categories.explore')"
                        class="cursor-pointer px-2 py-1 md:hover:bg-primary-content hover:text-primary rounded flex items-center mb-5">
                    <i class="w-8 bg-base-100 p-2 rounded-full fas fa-stream ">
                    </i>
                    <span class="mx-2 inline-block sm:hidden lg:inline-block">Explore</span>
                    </Link>
                    <Link :href="route('threads.create')"
                        class="cursor-pointer px-2 py-1 md:hover:bg-primary-content hover:text-primary rounded flex items-center mb-5">
                    <i class="w-8 bg-base-100 rounded-full p-2 fa-solid fa-circle-plus"></i>
                    <span class="mx-2 inline-block sm:hidden lg:inline-block">Post</span>
                    </Link>
                    <Link :href="route('favorites.index')" v-if="$page.props.auth.user"
                        class="cursor-pointer px-2 py-1 md:hover:bg-primary-content hover:text-primary rounded flex items-center mb-5">
                    <i class="w-8 bg-base-100 rounded-full p-2 fa-solid fa-heart"></i>
                    <span class="mx-2 inline-block sm:hidden lg:inline-block">Favorite</span>
                    </Link>
                    <Link :href="route('conversations.index')" v-if="$page.props.auth.user"
                        class="cursor-pointer px-2 py-1 md:hover:bg-primary-content hover:text-primary rounded flex items-center mb-5">
                    <i class="w-8 bg-base-100 rounded-full p-2 fa-solid fa-message"></i>
                    <span class="mx-2 inline-block sm:hidden lg:inline-block">Message</span>
                    </Link>
                </div>
            </transition>
        </div>

    </div>
</template>