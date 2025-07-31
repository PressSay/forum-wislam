<script setup>
// Web validation messages are still confusing
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import InputError from '@/Components/Default/InputError.vue';
import {
    form,
    photoInput,
    closeEditImage,
    isEditCover,
    isEditAvatar,
    cover,
    avatar,
    coverImg,
    avatarImg,
    avatarPreview,
    coverPreview,
    cropperRef,
    onChangeCover,
    onChangeAvatar,
    getImage,
    setIsEditCover,
    setIsEditAvatar,
    closeEditImg,
    deletePhoto,
    isDeletePhoto,
    isDeleteCover,
    showPhotos,
    showDelele,
    profileImageId,
    deleteProfileCropImage,
    deleteOriginalImage,
    selectOriginalImage,
    save,
} from './Utilities/show.js';

const props = defineProps({
    auth: Object,
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});

const api = axios.create({
    baseURL: `http://localhost:8000`,
    withCredentials: true
});
const getImageHistories = async (callback, page = 1) => {
    api.get(route('user-profile-information.history') + '/?page=' + page).then((response) => {
        callback(response.data);
    });
};
const imagesFromHistory = ref(null);
const getUploadedImages = () => {
    showPhotos.value = !showPhotos.value;
    getImageHistories((data) => {
        imagesFromHistory.value = data;
    })
}

const maxPage = ref(0);
const nextOriginalImages = () => {
    const page = (imagesFromHistory.value['current_page'] < maxPage.value || maxPage.value == 0) ? imagesFromHistory.value['current_page'] + 1 : imagesFromHistory.value['current_page'];
    if (page == imagesFromHistory.value['current_page'] + 1 && page != maxPage.value) {
        getImageHistories((data) => {
            if (data['data'].length == 0) {
                maxPage.value = data['current_page'];
            } else {
                imagesFromHistory.value = data;
            }
        }, page);
    }
}
const previousOriginalImages = () => {
    const page = ((imagesFromHistory.value['current_page'] - 1) <= 0) ? 0 : imagesFromHistory.value['current_page'] - 1;
    if (page > 0) {
        getImageHistories((data) => {
            imagesFromHistory.value = data;
        }, page);
    }
}
const confirmDeleteOriginalImage = () => {
    if (isDeletePhoto.value) {
        isDeletePhoto.value = false
        deletePhoto();
        return;
    }
    if (isDeleteCover.value) {
        isDeleteCover.value = false;
        return;
    }
    if (profileImageId.value == '') {
        return;
    }
    router.delete(route('user-profile-information.deleteImage', profileImageId.value), {
        preserveScroll: true,
        onSuccess: () => {
            if (imagesFromHistory.value) {
                getImageHistories((data) => {
                    imagesFromHistory.value = data;
                }, imagesFromHistory.value['current_page']);
            }
        },
    });
    profileImageId.value = '';
}



onMounted(() => {
    document.addEventListener('keydown', (e) => {
        if (e.key == 'Escape') {
            if (!showDelele.value && !isDeleteCover.value && !isDeletePhoto.value) {
                closeEditImg();
            }
            showDelele.value = false;
            isDeleteCover.value = false;
            isDeletePhoto.value = false;
        }
    });
});

const threads = ref(null);
const getThreads = (callback, userId, page) => {
    api.get(route('threads.getUserThreadsByCategory', userId) + '?page=' + page).then((res) => {
        callback(res.data);
    });
}
const pageThreads = ref(1);
getThreads((data) => {
    threads.value = data;
}, props.auth.user.user_id, pageThreads.value);
const maxPageThread = ref(0);
const nextThread = () => {
    const page = (threads.value['current_page'] < maxPageThread.value || maxPageThread.value == 0) ? threads.value['current_page'] + 1 : threads.value['current_page'];
    if (page == threads.value['current_page'] + 1 && page != maxPageThread.value) {
        getThreads((data) => {
            if (data['data'].length == 0) {
                maxPageThread.value = data['current_page'];
            } else {
                threads.value = data;
                pageThreads.value += 1;
            }
        }, props.auth.user.user_id, page);
        console.log(pageThreads.value);
    }
}
const previousThread = () => {
    const page = (threads.value['current_page'] - 1) <= 0 ? 0 : threads.value['current_page'] - 1;
    if (page > 0) {
        getPosts((data) => {
            threads.value = data;
            pageThreads.value -=1;
        }, page);
        console.log(pageThreads.value);
    }
}

</script>

<template>
    <DefaultLayout title="Profile">
        <template #absolute-body>
            <dialog id="modal_upload_image" class="modal">
                <div class="modal-box max-h-[90%] max-w-[90%] xl:max-w-[80rem] 2xl:max-w-[96rem] lg-m-6">
                    <form method="dialog">
                        <button @click="closeEditImg" ref="closeEditImage"
                            class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                    </form>

                    <div class="flex flex-col items-center w-full">
                        <div :class="{ 'mb-4': showPhotos }" class="flex justify-center gap-x-2 w-full">
                            <form v-if="!showPhotos" @submit.prevent="save">
                                <button class="btn btn-xs sm:btn-md btn-outline" type="submit">
                                    Save
                                </button>
                            </form>
                            <button @click="getUploadedImages" class="btn btn-xs sm:btn-md btn-outline">
                                {{ !showPhotos ? 'Select Uploaded' : 'Upload Image' }}
                            </button>
                            <button v-if="!showPhotos" class="btn btn-xs sm:btn-md btn-outline"
                                @click="deleteProfileCropImage" onclick="modal_delete.showModal()">Delete</button>
                        </div>
                        <div :class="{ 'hidden': showPhotos }" class="flex flex-col items-center">
                            <InputError :message="form.errors.photo" class="mt-2" />
                            <InputError :message="form.errors.original_photo" class="mt-2" />
                            <InputError :message="form.errors.cover" class="mt-2" />
                            <InputError :message="form.errors.original_cover" class="mt-2" />
                            <div>
                                <fieldset class="fieldset">
                                    <legend class="fieldset-legend">Pick a file</legend>
                                    <input @change="getImage($event)" type="file" class="file-input" multiple
                                        ref="photoInput" />
                                    <label class="label">Max size 2MB</label>
                                </fieldset>
                            </div>
                        </div>
                    </div>

                    <div v-if="showPhotos && imagesFromHistory" class="flex flex-col items-center w-full">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-[1rem] xl:grid-cols-4">
                            <div v-for="item in imagesFromHistory['data']"
                                class="card bg-base-100 w-[17.5rem] shadow-sm">
                                <figure>
                                    <img class="object-cover h-[9.75rem]" :src="item.original_url"
                                        alt="profile_image" />
                                </figure>
                                <div class="card-body">
                                    <div class="card-actions justify-between">
                                        <button class="btn btn-xs sm:btn-md btn-error"
                                            @click="deleteOriginalImage(item.profile_image_id)"
                                            onclick="modal_delete.showModal()">Delete</button>
                                        <button @click="selectOriginalImage(item.original_url)"
                                            class="btn btn-xs sm:btn-md btn-primary">Select</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="join mt-4">
                            <button @click="previousOriginalImages" class="join-item btn">«</button>
                            <button class="join-item btn">{{ imagesFromHistory['current_page'] }}</button>
                            <button @click="nextOriginalImages" class="join-item btn">»</button>
                        </div>
                    </div>

                    <!-- edit avatar -->
                    <div v-if="!showPhotos" class="flex flex-col items-center justify-center">
                        <preview v-if="isEditAvatar" class="h-[7rem] w-[7rem] rounded-full mb-2"
                            :image="avatarPreview.image" :coordinates="avatarPreview.coordinates" />
                        <cropper v-if="isEditAvatar" check-orientation ref="cropperRef" class="cropper h-[25rem]"
                            :src="avatarImg.src" @change="onChangeAvatar" :stencil-props="{
                                aspectRatio: 1 / 1,
                            }"></cropper>
                    </div>
                    <!-- edit cover -->
                    <div v-if="!showPhotos" class="flex flex-col items-center justify-center">
                        <preview v-if="isEditCover" :height="144" class="w-full mb-2" :image="coverPreview.image"
                            :coordinates="coverPreview.coordinates" />
                        <cropper v-if="isEditCover" check-orientation ref="cropperRef" class="cropper h-[25rem]"
                            :src="coverImg.src" @change="onChangeCover" :stencil-props="{
                                aspectRatio: 6 / 1,
                            }"></cropper>
                    </div>
                </div>
            </dialog>
            <dialog id="modal_delete" class="modal">
                <div class="modal-box">
                    <div class="flex flex-col items-center">
                        <h3 class="font-bold text-lg mb-4">Delete Image</h3>
                        <p>Are you sure you want to delete this {{ showPhotos ? "image" : isEditAvatar ? "avatar" :
                            "cover" }}?</p>
                    </div>
                    <div class="modal-action inline-flex items-center justify-center w-full">
                        <form method="dialog">
                            <button type="submit" class="btn btn-sm btn-soft btn-error me-2"
                                @click="confirmDeleteOriginalImage">
                                Delete
                            </button>
                        </form>
                        <form method="dialog">
                            <!-- if there is a button in form, it will close the modal -->
                            <button type="submit" class="btn btn-sm btn-soft"
                                ref="closeModelDeleteThread">Close</button>
                        </form>
                    </div>
                </div>
            </dialog>
        </template>

        <template #body>
            <div class="relative h-[18rem] sm:h-[14rem] rounded-lg shadow-md mb-4">
                <div class="w-full h-full relative">
                    <img class="w-full h-[9rem] object-cover"
                        :src="cover != '' ? cover : $page.props.auth.user.profile_cover_url" alt="" />
                    <div class="h-[9rem] sm:h-[7rem] w-full bg-base-300 absolute bottom-0 left-0 rounded-b-lg" />
                </div>
                <div
                    class="absolute bottom-[5rem] sm:bottom-[3.5rem] left-0 w-full h-[7rem] flex items-center justify-between px-4">
                    <!-- avatar, name, nickname, bio -->
                    <div class="flex flex-col sm:flex-row items-center h-full">
                        <div class="w-[7rem] h-full relative mb-2 sm:mb-0">
                            <img class="rounded-full w-[7rem] h-full"
                                :src="avatar != '' ? avatar : $page.props.auth.user.profile_photo_url" alt="avatar">
                            <button @click="setIsEditAvatar" onclick="modal_upload_image.showModal()"
                                class="btn btn-xs btn-ghost btn-accent absolute left-0 bottom-0 rounded-b-full w-[7rem] h-[4rem]">Edit</button>
                        </div>

                        <div class="ms-4 h-full flex flex-col justify-end">
                            <h2 class="text-sm sm:text-base font-semibold max-w-[10rem] overflow-hidden">Name Avatar
                            </h2>
                            <p class="text-xs sm:text-sm font-extralight">Agent</p>
                        </div>
                    </div>
                    <!-- edit cover, edit profile -->
                    <div class="flex flex-col justify-between items-end h-full">
                        <button class="btn btn-xs sm:btn-md btn-accent btn-outline" @click="setIsEditCover"
                            onclick="modal_upload_image.showModal()">
                            Edit Cover
                        </button>
                        <Link :href="route('profile.detail')" class="btn btn-xs sm:btn-md text-xs">
                        Edit Profile
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Thread by category -->
            <div v-if="threads != null" v-for="thread in threads.data" class="mb-4">
                <div class="flex items-center mt-2 mb-4">
                    <h1 class="text-lg font-semibold">{{ thread.category_title }}</h1>
                </div>
                <table class="w-full table-auto border-collapse text-sm">
                    <tbody>
                        <tr>
                            <th class="border-b border-base-content p-4 py-3 pl-8 text-left font-semibold">
                                Topic</th>
                            <th
                                class="border-b border-base-content p-4 py-3 pl-8 text-left font-semibold max-sm:hidden">
                                Replies</th>
                            <th
                                class="border-b border-base-content p-4 py-3 pl-8 text-left font-semibold max-sm:hidden">
                                Views</th>
                            <th class="border-b border-base-content p-4 py-3 pl-8 text-left font-semibold">
                                Last post</th>
                        </tr>
                        <tr v-for="item in thread.threads" :key="item.thread_id">
                            <td class="border-b border-base-content p-4 pl-8">
                                <div class="inline-flex flex-col lg:flex-row items-start gap-2">
                                    <Link class="link link-info max-w-[12rem] max-h-[2.5rem] overflow-scroll"
                                        :href="route('threads.detail', [thread.category_slug, item.slug])">{{
                                            item.title
                                        }}</Link>
                                    <div class="flex gap-1"><span v-for="tag in item.tags"
                                            class="badge badge-primary">{{
                                                tag.name }} </span></div>
                                </div>
                            </td>
                            <td class="border-b border-base-content p-4 pl-8 max-sm:hidden">{{ item.totalReply }}</td>
                            <td class="border-b border-base-content p-4 pl-8 max-sm:hidden">{{ item.views }}</td>
                            <td class="border-b border-base-content p-4 pl-8">
                                <div v-if="item.lastPost" class="inline-flex flex-col">
                                    <p>{{ item.lastPost.created_at.substring(0, 10) }}</p>
                                    <p>by {{ item.lastPost.user.name }}</p>
                                </div>
                                <div v-else class="inline-flex flex-col">
                                    <p>Empty</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-center">
                <div class="join">
                    <button @click="previousThread" class="join-item btn">«</button>
                    <button class="join-item btn">{{ pageThreads }}</button>
                    <button @click="nextThread" class="join-item btn">»</button>
                </div>
            </div>
        </template>
    </DefaultLayout>
</template>
