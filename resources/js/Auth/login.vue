<template>
    <div class="flex w-full items-center bg-[#000000A3] fixed inset-0 justify-center">
        <div class="bg-white shadow-light flex-col items-center max-h-[90vh] w-[55rem] rounded-[2rem] overflow-auto">
            <div class="border-b border-gray-300 flex h-24 justify-evenly p-8 relative w-full">
                <button 
                	class="absolute top-5 left-5 flex items-center justify-center w-14 h-14 hover:bg-gray-200 rounded-full"
                	@click="closeWindow">
                    <svg class="h-full w-1/2">
                        <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                    </svg>
                </button>
                <p class="font-semibold text-1xl">Log in or sign up</p>
            </div>
            <div class="flex flex-col h-full justify-start p-8 w-full">
                <div class="login-form">
			        <div class="field">
			            <h3 class="mb-8 mt-4">Welcome to Everything Immersive</h3>
			            <input 
			                id="email" 
			                type="email" 
			                class="bg-white border border-gray-300 rounded-t-lg text-gray-700 font-montserrat text-base md:text-lg px-4 py-5 relative transition duration-200 w-full focus:rounded-t-lg"
			                v-model="user.email" 
			                :class="{ 'error': v$.user.email.$error }"
			                @input="clearServerError('email')"
			                @keyup.enter="onSubmit"
			                required
			                placeholder="email" 
			                autofocus>
			            <p v-if="v$.user.email.$dirty && v$.user.email.required.$invalid" class="text-red-500 text-lg italic">The email is required</p>
						<p v-if="v$.user.email.$dirty && v$.user.email.email.$invalid" class="text-red-500 text-lg italic">Must be an email</p>
			        </div>
			        <div class="field">
			            <input 
			                id="password" 
			                class="bg-white border border-gray-300 rounded-b-lg border-t-0 text-gray-700 font-montserrat text-base md:text-lg px-4 py-5 relative transition duration-200 w-full focus:border-t-0 focus:rounded-b-lg"
			                :type="isVisible ? 'text' : 'password'" 
			                v-model="user.password"
			                :class="{'error': v$.user.password.$error || !v$.user.email.serverFailed }"
			                @input="clearServerError('password')"
			                @keyup.enter="onSubmit"
			                required
			                placeholder="password">
			            <p v-if="v$.user.password.$dirty && v$.user.password.required.$invalid" class="text-red-500 text-lg italic">The password is required.</p>
						<p v-if="v$.user.password.$dirty && v$.user.password.minLength.$invalid" class="text-red-500 text-lg italic">The password must be at least 8 characters long.</p>
			            <div v-if="errors.length > 0" class="text-red-500 text-lg italic">
					        <p class="error" v-for="(error, index) in errors" :key="index">{{ error }}</p>
					    </div>
			            <div class="password">
			                <svg class="w-8 h-8 absolute top-6 right-12"
			                    @click="isVisible=!isVisible">
			                    <use 
			                        v-if="isVisible" 
			                        :xlink:href="`/storage/website-files/icons.svg#ri-eye-off-line`" />
			                    <use 
			                        v-else 
			                        :xlink:href="`/storage/website-files/icons.svg#ri-eye-line`" />
			                </svg>
			            </div>
			        </div>
			        <div class="field mt-2">
			            <transition name="fade" mode="out-in">
			                <template v-if="true">
			                    <button 
			                    	@click="forgotPassword"
			                        class="underline border-none underline text-xl mb-8"
			                        :class="{ inprogress: disabled}">
			                        Forgot your password?
			                    </button>
			                </template>
			                <template v-else>
			                    <div class="rounded-2xl text-white mb-4 p-4 bg-gradient-to-r from-cyan-500 to-blue-500">
			                        <button 
			                            class="top-1.5 right-1.5 absolute border-white rounded-full z-10 hover:bg-white"
			                            @click="forget=false">
			                            <svg class="w-8 h-8 fill-white hover:fill-black">
			                                <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
			                            </svg>
			                        </button>
			                        <h3 class="text-white">We have emailed you a reset password link.</h3>
			                        <p class="text-white">Please check your email.</p>
			                    </div>
			                </template>
			            </transition>
			        </div>
			        <div class="field">
			            <button 
			                type="submit" 
			                :disabled="isDisabled" 
			                class="mb-4 font-medium py-6 px-4 rounded-2xl w-full border-none text-white float-right bg-gradient-to-r from-button-red-1 via-button-red-2 to-button-red-3 md:px-20 hover:from-button-red-2 hover:via-button-red-3 hover:to-button-red-1"
			                @click="onSubmit">
			                Continue
			            </button>
			        </div>
			    </div>
			    <div class="border-t border-gray-300 pt-8 mt-4">
	            	<button class="border border-black w-full flex items-center justify-center p-6 rounded-2xl mb-8">
	            		Continue with Google
	            	</button>
	            	<button class="border border-black w-full flex items-center justify-center p-6 rounded-2xl">
	            		Continue with Apple
	            	</button>
	            </div>
            </div>
        </div>
    </div>
</template>

<script>
import { reactive, toRefs } from 'vue';
import { useVuelidate } from '@vuelidate/core';
import { required, email, minLength } from '@vuelidate/validators';

export default {
    setup (props, context) {

        const form = reactive({
            user: {
                email: '',
                password: '',
            },
            isVisible:false,
            isDisabled: false,
            disabled: false, 
            errors: {},
        });

        const rules = {
            user: {
                email: {
                    required,
                    email,
                },
                password: {
                    required,
                    minLength: minLength(8),
                },
            },
        };

        const v$ = useVuelidate(rules, form);


        const onSubmit = async () => {

            form.errors = {};
            const isFormValid = await v$.value.$validate();
            if (!isFormValid) return;
            
            try {
                const res = await axios.post(`/authenticate`, form.user);
                location.reload();
            } catch (err) {
                if (err.response && err.response.data && err.response.data.errors) {
                    for (const [key, value] of Object.entries(err.response.data.errors)) {
                        form.errors.value.push(...value);
                    }
                } else {
                    form.errors.value.push('An unexpected error occurred.');
                }
            }
        };

        const forgotPassword = () => {
            axios.post('/forgot-password', { email: user.value.email })
                .then(response => {
                    alert("Reset link sent! Check your email.");
                })
                .catch(error => {
                    console.error(error);
                });
        };


        const closeWindow = () => {
	        document.body.classList.remove('noscroll');
	        context.emit('close', false);
	    };

        const clearServerError = (fieldName) => {
            if (form.errors[fieldName]) {
                form.errors[fieldName] = null;
            }
        };

        return { ...toRefs(form), v$, onSubmit, forgotPassword, closeWindow, clearServerError };
    }
}
</script>
