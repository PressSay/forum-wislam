<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/Default/AuthenticationCard.vue';
import Checkbox from '@/Components/Default/Checkbox.vue';
import InputError from '@/Components/Default/InputError.vue';
import InputLabel from '@/Components/Default/InputLabel.vue';
import TextInput from '@/Components/Default/TextInput.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>

    <Head title="Register" />

    <AuthenticationCard>
        <template #logo>
            <img class="object-cover w-full h-screen md:block hidden" src="/images/sebastian-svenson-d2w-_1LJioQ-unsplash 1.png" alt="register_bg_image">
        </template>

        <div class="flex flex-col items-start mb-10">
            <h1 class="text-2xl font-semibold mb-1 ">Wellcome to Forum Wislam</h1>
            <p class="font-light">Already have an account?
                <Link :href="route('login')"
                    class="underline text-sm text-gray-400 hover:text-gray-600 rounded-md focus:outline-none  focus:text-blue-500">
                log in
                </Link>
            </p>
        </div>
        <form @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Name" />
                <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus
                    autocomplete="name" />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required
                    autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="flex flex-row mt-4 w-full">
                <div class="me-2 w-1/2">
                    <InputLabel for="password" value="Password" />
                    <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required
                        autocomplete="new-password" />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="ms-2 w-1/2">
                    <InputLabel for="password_confirmation" value="Confirm Password" />
                    <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password"
                        class="mt-1 block w-full" required autocomplete="new-password" />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>
            </div>

            <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="mt-4">
                <InputLabel for="terms">
                    <div class="flex items-center">
                        <Checkbox id="terms" v-model:checked="form.terms" name="terms" required />

                        <div class="ms-2">
                            I agree to the <a target="_blank" :href="route('terms.show')"
                                class="underline text-sm text-gray-400 hover:text-gray-600 rounded-md focus:outline-none  focus:text-blue-500">Terms
                                of Service</a> and <a target="_blank" :href="route('policy.show')"
                                class="underline text-sm text-gray-400 hover:text-gray-600 rounded-md focus:outline-none  focus:text-blue-500">Privacy
                                Policy</a>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.terms" />
                </InputLabel>
            </div>

            <div class="flex flex-col items-start justify-end mt-10">
                <button class="btn mb-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Register
                </button>

                <p class="font-light">Already have an account?
                    <Link :href="route('login')"
                        class="underline text-sm text-gray-400 hover:text-gray-600 rounded-md focus:outline-none focus:ring-indigo-500">
                    log in
                    </Link>
                </p>
            </div>
        </form>
    </AuthenticationCard>
</template>
