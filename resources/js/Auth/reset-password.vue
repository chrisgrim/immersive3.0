<template>
    <div class="flex items-center justify-center min-h-screen bg-gray-100">
        <div class="w-full max-w-[55rem] rounded-[2rem] p-8 space-y-8 bg-white shadow-md">
            <h1 class="text-xl font-bold text-center">Reset Password</h1>
            <form @submit.prevent="submitForm" class="space-y-2">
                <div>
                    <label for="reset-email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input 
                    	type="email" 
                    	id="reset-email" 
                    	name="email" 
                    	@input="clearServerError('email')"
                    	v-model="user.email" 
                    	required 
                    	autofocus 
                    	class="w-full px-3 py-5 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    
                    <p v-if="errors.email" class="text-red-500 text-lg italic">{{ errors.email[0] }}</p>
                	<p v-if="v$.user.email.$dirty && v$.user.email.required.$invalid" class="text-red-500 text-lg italic">The email is required</p>
					<p v-if="v$.user.email.$dirty && v$.user.email.email.$invalid" class="text-red-500 text-lg italic">Must be an email</p>
                </div>


                <div>
				    <label for="reset-password" class="block text-sm font-medium text-gray-700">New Password</label>
				    <input 
				    	type="password" 
				    	id="reset-password" 
				    	name="password"
				    	@input="clearServerError('password')"
				    	v-model="user.password" 
				    	required 
				    	class="w-full px-3 py-5 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">

				    <p v-if="errors.password" class="text-red-500 text-lg italic">{{ errors.password[0] }}</p>
					<p v-if="v$.user.password.$dirty && v$.user.password.required.$invalid" class="text-red-500 text-lg italic">The password is required.</p>
					<p v-if="v$.user.password.$dirty && v$.user.password.minLength.$invalid" class="text-red-500 text-lg italic">The password must be at least 8 characters long.</p>
				</div>

				<div>
				    <label for="password-confirm" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
				    <input 
				    	type="password" 
				    	id="password-confirm" 
				    	name="password_confirmation"
				    	@input="clearServerError('passwordConfirmation')"
				    	v-model="user.passwordConfirmation" 
				    	required 
				    	class="w-full px-3 py-5 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">

				    <p v-if="errors.passwordConfirmation" class="text-red-500 text-lg italic">{{ errors.passwordConfirmation[0] }}</p>
					<p v-if="v$.user.passwordConfirmation.$dirty && v$.user.passwordConfirmation.required.$invalid" class="text-red-500 text-lg italic">The confirmation password is required.</p>
					<p v-if="v$.user.passwordConfirmation.$dirty && v$.user.passwordConfirmation.sameAsPassword.$invalid" class="text-red-500 text-lg italic">The passwords must match.</p>
				</div>

                <div>
                    <button type="submit" class="mb-4 font-medium py-6 px-4 rounded-2xl w-full border-none text-white float-right bg-gradient-to-r from-button-red-1 via-button-red-2 to-button-red-3 md:px-20 hover:from-button-red-2 hover:via-button-red-3 hover:to-button-red-1">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { reactive, toRefs } from 'vue';
import { useVuelidate } from '@vuelidate/core';
import { required, minLength, email } from '@vuelidate/validators';

export default {
    setup() {
        const url = new URL(window.location.href);
        const token = url.pathname.split('/').pop(); 
        const emailParam = url.searchParams.get('email');

        const form = reactive({
            user: {
                email: emailParam ? decodeURIComponent(emailParam) : '',
                password: '',
                passwordConfirmation: '',
            },
            token: token,
            errors: {},
        });

        const rules = {
            user: {
                email: { required, email },
                password: { required, minLength: minLength(8) },
                passwordConfirmation: {
                    required,
                    sameAsPassword: (value) => value === form.user.password,
                },
            },
        };

        const v$ = useVuelidate(rules, form);

        const submitForm = async () => {
            form.errors = {};
            const isFormValid = await v$.value.$validate();
            if (!isFormValid) return;

            axios.post('/reset-password', {
                token: form.token,
                email: form.user.email,
                password: form.user.password,
                password_confirmation: form.user.passwordConfirmation,
            })
            .then(() => {
                window.location.href = '/';
            })
            .catch(error => {
                // Simplified error handling
                if (error.response && error.response.status === 422) {
                    Object.assign(form.errors, error.response.data.errors);
                } else {
                    console.error('Unexpected error:', error);
                }
            });
        };

        const clearServerError = (fieldName) => {
            if (form.errors[fieldName]) {
                form.errors[fieldName] = null;
            }
        };

        return {
            ...toRefs(form),
            v$,
            submitForm,
            clearServerError,
        };
    },
};
</script>