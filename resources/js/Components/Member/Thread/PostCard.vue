<script setup>
import { onMounted, onUpdated, ref, nextTick, onUnmounted, reactive } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    post: Object,
    hljs: Object,
    contentHtml: String,
    contentParrentHtml: String,
    index: Number,
});
const emit = defineEmits(['accessKey', 'showMorePost', 'editPost', 'reply']);
const refContent = ref(null);
const refEditor = ref(null);
const refParrentContent = ref(null);

const highlightCode = () => {
    if (props.hljs != null) {
        const hljs = props.hljs;
        const codes = refContent.value.getElementsByTagName('code');
        for (let index = 0; index < codes.length; index++) {
            const element = codes[index];
            if (!element.hasAttribute('data-highlighted'))
                hljs.highlightElement(element);
        }
        const parrentCodes = refParrentContent.value.getElementsByTagName('code');
        for (let index = 0; index < parrentCodes.length; index++) {
            const element = parrentCodes[index];
            if (!element.hasAttribute('data-highlighted'))
                hljs.highlightElement(element);
        }
    }
}

onMounted(() => {
    highlightCode();
    window.addEventListener('resize', calculatePopoverPosition);
});
onUpdated(() => {
    highlightCode();
});
onUnmounted(() => {
    window.removeEventListener('resize', calculatePopoverPosition);
});

const triggerRef = ref(null);
const popoverStyle = reactive({
  top: '0px',
  left: '0px',
});
const OFFSET = 5;

const isVisible = ref(false);
const isMouseOverTrigger = ref(false);
const isMouseOverPopover = ref(false);
const showPopover = async () => {
    isMouseOverTrigger.value = true;
    if (!isVisible.value) { // Only show if it is not already shown
        isVisible.value = true;
        await nextTick(); // Wait for the popover to be rendered into the DOM
        calculatePopoverPosition();
    }
};
const handleMouseLeaveTrigger = () => {
    isMouseOverTrigger.value = false;
    // If the mouse is no longer on the trigger and not on the popover, then hide
    setTimeout(() => { // Use setTimeout to allow mouse movement between trigger and popover
        if (!isMouseOverTrigger.value && !isMouseOverPopover.value) {
            isVisible.value = false;
        }
    }, 100); // A small time interval (ms)
};
const handleMouseEnterPopover = () => {
    isMouseOverPopover.value = true;
};
const handleMouseLeavePopover = () => {
    isMouseOverPopover.value = false;
    // If the mouse is no longer on the trigger and not on the popover, then hide
    setTimeout(() => { // Use setTimeout to allow mouse movement between trigger and popover
        if (!isMouseOverTrigger.value && !isMouseOverPopover.value) {
            isVisible.value = false;
        }
    }, 100); // A small time interval (ms)
};
const calculatePopoverPosition = () => {
  if (!triggerRef.value) return;

  const triggerRect = triggerRef.value.getBoundingClientRect(); // Position of trigger relative to viewport
  let top = 0;
  let left = 0;

    // Size of the popover. Make sure the popover has rendered to get the correct size.
    // If the popover is not visible, you can create a temporary div or estimate the size.
    // In this example, we will assume it has a size after v-if = true.
    // A more reliable way is to set the popover to a hidden state (opacity: 0, pointer-events: none)
    // and show it after calculating. Or use a library like Popper.js.
    // Here, for simplicity, we will get the size after v-if=true and nextTick.
    // If popoverRef.value is not available, we can set a default value for popoverRect.
  const tempPopover = document.createElement('div');
  tempPopover.className = 'fixed invisible opacity-0 min-h-[10.5rem] min-w-[10rem]'; // Vô hình để lấy kích thước
  tempPopover.style.cssText = 'position: fixed; top: 0; left: 0; pointer-events: none;'; // Đảm bảo không ảnh hưởng layout
  document.body.appendChild(tempPopover);
    // Copy style from real popover if available or estimate size
    // If popover has dynamic content, this is more complicated.
    // The best solution is still to leave popover in DOM but hidden, then get size.
    // Or use a dedicated library.
  const popoverRect = tempPopover.getBoundingClientRect();
  tempPopover.remove(); // Delete temporary element

  // Calculate initial position (default below)
  let potentialTop = triggerRect.bottom + OFFSET;
  let potentialLeft = triggerRect.left + (triggerRect.width / 2) - (popoverRect.width / 2);

  // Check for bottom edge collision and flip up
  if (potentialTop + popoverRect.height > window.innerHeight) {
    // Not enough room below, flip up
    top = triggerRect.top - popoverRect.height - OFFSET;
  } else {
    top = potentialTop;
  }

  // Check for left edge collision
  if (potentialLeft < 0) {
    left = 0 + OFFSET; // Align to left edge
  }
  // Check for right edge collision
  else if (potentialLeft + popoverRect.width > window.innerWidth) {
    left = window.innerWidth - popoverRect.width - OFFSET; // Căn sát mép phải
  } else {
    left = potentialLeft;
  }

  popoverStyle.top = `${top}px`;
  popoverStyle.left = `${left}px`;
};

const api = axios.create({
    baseURL: `http://localhost:8000`,
    withCredentials: true
})

const sendMessage = (user_id) => {
    api.post(route('conversations.store'), {
        'user_id': user_id
    }).then((response) => {
        router.visit(route('conversations.show', response.data.conversation_id));
    });
}

</script>

<template>
    <div class="flex flex-col bg-base-200 text-base-content p-2 rounded-md border border-accent-content border-double">
        <div :class="{ 'hidden': (post.parrent == null) }"
            class="flex flex-col my-4 gap-2 border p-2 border-accent rounded-sm">
            <div v-if="post.parrent" class="flex items-start ">
                <img class="rounded-full max-w-10" alt="avatar" :src="post.parrent.user.profile_photo_path" />
                <div class="inline-flex flex-col ms-2 ">
                    <span class="font-semibold text-sm max-w-[10rem] max-h-[1.25rem] overflow-x-auto">{{
                        post.parrent.user.name
                    }}</span>
                    <p class="text-xs">{{ post.parrent.created_at }}</p>
                </div>
            </div>
            <div class="nakedScope" ref="refParrentContent" v-html="contentParrentHtml">
            </div>
        </div>
        <div class="flex items-start justify-between relative">
            <div ref='triggerRef' class="flex items-start">
                <img  @mouseenter="showPopover" @mouseleave="handleMouseLeaveTrigger"
                    class="rounded-full hover:border max-w-10 cursor-pointer" alt="avatar"
                    :src="post.user.profile_photo_path" />
                <div class="inline-flex flex-col ms-2 ">
                    <span @mouseenter="showPopover" @mouseleave="handleMouseLeaveTrigger"
                        class="font-semibold text-sm max-w-[10rem] max-h-[1.25rem] overflow-x-auto cursor-pointer hover:underline underline-offset-1">{{
                            post.user.name
                        }}</span>
                    <p class="text-xs">{{ post.created_at }}</p>
                </div>
            </div>
            <div v-if="$page.props.auth.user" :style='popoverStyle' @mouseenter="handleMouseEnterPopover" v-show="isVisible"
                @mouseleave="handleMouseLeavePopover"
                class="fixed bg-base-200 border border-gray-300 rounded-lg shadow-xl p-4 z-100 transition-opacity duration-200">
                <div class="flex gap-2 items-center cursor-pointer">
                    <img :src="post.user.profile_photo_path" alt="avatar"
                        class="w-10 h-10 rounded-full hover:border curosr-pointer">
                    <h3 class="font-bold text-lg mb-2">{{ post.user.name }}</h3>
                </div>
                <p class="text-sm mt-2">Joined on</p>
                <p class="text-sm">{{ post.user.created_at }}</p>
                <button  @click="sendMessage(post.user.user_id)" class="btn btn-sm mt-3">Send Message</button>
            </div>
            <div class="flex gap-2">
                <button
                    v-if="$page.props.auth.user && post.user.user_id == $page.props.auth.user.user_id && (post.showMore == 3 || post.showMore == 4)"
                    class="btn btn-sm btn-soft btn-success"
                    @click="$emit('editPost', post, index, refContent, refEditor, true)">Cancel</button>
                <button v-if="$page.props.auth.user && post.user.user_id == $page.props.auth.user.user_id"
                    class="btn btn-sm btn-soft btn-info"
                    @click="$emit('editPost', post, index, refContent, refEditor)">{{ ((post.showMore == 3 ||
                        post.showMore == 4) ?
                        'Save' : 'Edit') }}</button>
                <button v-if="$page.props.auth.user && post.user.user_id == $page.props.auth.user.user_id"
                    class="btn btn-sm btn-soft btn-error" @click="$emit('accessKey')"
                    onclick="modal_delete_post.showModal()">Delete</button>
            </div>
        </div>
        <div ref="refContent" :class="{ 'hidden': (post.showMore == 3 || post.showMore == 4) }"
            class="nakedScope mx-2 mt-2 post" v-html="contentHtml"></div>
        <div :class="{ 'hidden': !(post.showMore == 1 || post.showMore == 0) }"
            @click="$emit('showMorePost', post, index, refContent)" class="py-2">
            <button class="btn btn-xs">{{ (post.showMore == 1) ? 'Show More' : 'Show Less'
                }}</button>
        </div>
        <div :class="{ 'hidden': post.showMore == 3 }" class="flex gap-2 mt-2">
            <!-- <button class="btn btn-xs btn-soft btn-primary">Like</button> -->
            <a href="#comment" v-if="$page.props.auth.user && post.user.user_id != $page.props.auth.user.user_id"
                class="btn btn-xs btn-soft btn-accent" @click="$emit('reply', post, index)">reply</a>
        </div>
        <div ref="refEditor" :class="{ 'hidden': !(post.showMore == 3 || post.showMore == 4) }" class="border mt-4">
            <div></div>
        </div>
    </div>
</template>