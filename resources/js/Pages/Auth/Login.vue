<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SvgClose from '@/Components/Svgs/SvgClose.vue';
import Checkbox from '@/Components/Default/Checkbox.vue';
import InputError from '@/Components/Default/InputError.vue';
import InputLabel from '@/Components/Default/InputLabel.vue';
import TextInput from '@/Components/Default/TextInput.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>

    <Head title="Log in" />

    <div
        class="min-h-screen flex flex-col md:flex-row-reverse justify-center items-center pt-6 sm:pt-0 bg-[url(/images/704513.jpg)]">


        <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
            {{ status }}
        </div>
        <div
            class="flex flex-col justify-center w-[90%] sm:w-[30rem] px-6 py-[4rem] overflow-hidden sm:rounded-lg bg-base-100 shadow-lg relative">

            <Link class="absolute top-4 right-4 z-10" :href="route('categories.explore')" :active="route().current('categories.explore')">
                <SvgClose />
            </Link>


            <form @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autofocus
                        autocomplete="username" />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mt-4">
                    <InputLabel for="password" value="Password" />
                    <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required
                        autocomplete="current-password" />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="block mt-4">
                    <label class="flex items-center">
                        <Checkbox v-model:checked="form.remember" name="remember" />
                        <span class="ms-2 text-sm text-gray-600">Remember me</span>
                    </label>
                </div>

                <div class="flex items-center justify-end my-4">
                    <Link v-if="canResetPassword" :href="route('password.request')"
                        class="underline text-sm text-gray-400 hover:text-gray-600 rounded-md focus:outline-none  focus:text-blue-500">
                    Forgot your password?
                    </Link>

                    <button class="btn ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Log in
                    </button>
                </div>

                <div></div>
            </form>
        </div>
    </div>
</template>
