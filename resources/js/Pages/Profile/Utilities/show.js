import { ref, reactive } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

export const form = useForm({
    _method: 'PUT',
    cover: null,
    photo: null,
    original_photo: null,
    original_cover: null
});

export const photoInput = ref(null);
export const closeEditImage = ref(null);
export const isEditCover = ref(false);
export const isEditAvatar = ref(false);
export const cover = ref('');
export const avatar = ref('');

export const clearPhotoFileInput = () => {
    form.photo = null;
    form.original_photo = null;
    form.cover = null;
    form.original_cover = null;
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
export const coverImg = reactive({
    src: '',
    type: ''
});
export const avatarImg = reactive({
    src: '',
    type: ''
});
export const avatarPreview = reactive({
    image: null,
    coordinates: null
});
export const coverPreview = reactive({
    image: null,
    coordinates: null
});
export const cropperRef = ref(null);
export const onChangeCover = ({ coordinates, image }) => {
    coverPreview.image = image;
    coverPreview.coordinates = coordinates;
}
export const onChangeAvatar = ({ coordinates, image }) => {
    avatarPreview.image = image;
    avatarPreview.coordinates = coordinates
}
export const getImage = (e) => {
    const { files } = e.target;

    if (files && files[0]) {
        if (isEditAvatar.value) {
            form.original_photo = files[0];
            if (avatarImg.src) {
                URL.revokeObjectURL(avatarImg.src);
            }
        } else if (isEditCover.value) {
            form.original_cover = files[0];
            if (coverImg.src) {
                URL.revokeObjectURL(coverImg.src);
            }
        }
        const blob = URL.createObjectURL(files[0]);

        const reader = new FileReader();
        reader.onload = (e) => {
            if (isEditAvatar.value) {
                avatarImg.src = blob;
                avatarImg.type = getMimeType(e.target.result, files[0].type);
            } else if (isEditCover.value) {
                coverImg.src = blob;
                coverImg.type = getMimeType(e.target.result, files[0].type);
            }
        };
        reader.readAsArrayBuffer(files[0]);
    }
}
const getMimeType = (file, fallback = null) => {
    const byteArray = new Uint8Array(file).subarray(0, 4);
    let header = '';
    for (let i = 0; i < byteArray.length; i++) {
        header += byteArray[i].toString(16);
    }
    switch (header) {
        case '89504e47':
            return 'image/png';
        case '47494638':
            return 'image/gif';
        case 'ffd8ffe0':
        case 'ffd8ffe1':
        case 'ffd8ffe2':
        case 'ffd8ffe3':
        case 'ffd8ffe8':
            return 'image/jpeg';
        default:
            return fallback;
    }
}
export const setIsEditCover = () => {
    isEditCover.value = true;
}
export const setIsEditAvatar = () => {
    isEditAvatar.value = true;
}
export const closeEditImg = () => {
    isEditAvatar.value = false;
    isEditCover.value = false;
    clearPhotoFileInput();
}
export const closeModalImage = () => {
    closeEditImg();
    closeEditImage.value.click();
}
export const save = () => {
    if (cropperRef.value == null) {
        return;
    }
    const { canvas } = cropperRef.value.getResult();
    if (isEditAvatar.value) {
        if (canvas) {
            canvas.toBlob(blob => {
                if (form.photo) {
                    URL.revokeObjectURL(avatar.value)
                    URL.revokeObjectURL(form.photo);
                }
                form.photo = blob;
                form.post(route('user-profile-information.updateImage'), {
                    forceFormData: true,
                    errorBag: 'updateProfileInformation',
                    preserveScroll: true,
                    onSuccess: () => {
                        closeModalImage();
                        avatar.value = canvas.toDataURL();
                    },
                    onError: () => {
                        clearPhotoFileInput();
                    }
                });
            }, avatarImg.type);
        }
    }
    if (isEditCover.value) {
        if (canvas) {
            canvas.toBlob(blob => {
                if (form.cover) {
                    URL.revokeObjectURL(cover.value);
                    URL.revokeObjectURL(form.cover);
                }
                form.cover = blob;
                form.post(route('user-profile-information.updateImage'), {
                    errorBag: 'updateProfileInformation',
                    preserveScroll: true,
                    onSuccess: () => {
                        closeModalImage();
                        cover.value = canvas.toDataURL();
                    },
                    onError: () => {
                        clearPhotoFileInput();
                     }
                });
            }, coverImg.type);
        }
    }
}

// Delete
export const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            avatar.value = '';
            clearPhotoFileInput();
        },
    });
};
export const isDeletePhoto = ref(false);
export const isDeleteCover = ref(false);
export const showPhotos = ref(false);
export const showDelele = ref(false);
export const profileImageId = ref('');
export const deleteProfileCropImage = () => {
    if (isEditAvatar.value) {
        isDeletePhoto.value = true;
    } else if (isEditCover.value) {
        isDeleteCover.value = true;
    }
}
export const deleteOriginalImage = (uuid) => {
    profileImageId.value = uuid
    showDelele.value = true;
}
export const selectOriginalImage = (originalImage) => {
    const urlImage = originalImage.split(".");
    const type = urlImage[urlImage.length - 1];
    const typeImage = type == 'jpg' ? 'image/jpeg' : 'image/' + type;
    console.log(typeImage);
    if (isEditAvatar.value) {
        avatarImg.src = originalImage;
        avatarImg.type = typeImage;
    } else if (isEditCover.value) {
        coverImg.src = originalImage;
        coverImg.type = typeImage;
    }
    showPhotos.value = false;
}