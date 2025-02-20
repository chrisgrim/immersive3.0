<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <div class="mt-6 space-y-8">
                    <!-- Name Input -->
                    <div>
                        <label class="block text-xl text-gray-700 mb-2">Name</label>
                        <textarea 
                            name="name" 
                            :class="[
                                'text-4xl p-4 border rounded-2xl mt-1 block w-full',
                                {
                                    'border-red-500 focus:border-red-500 focus:shadow-focus-error': hasNameError,
                                    'border-[#222222] focus:border-black focus:shadow-focus-black': !hasNameError
                                }
                            ]"
                            v-model="owner.name" 
                            @input="handleNameInput"
                            placeholder="Enter your name"
                            rows="2" 
                        />
                        
                        <!-- Name Character Count -->
                        <div class="flex justify-end mt-1" 
                             :class="{
                                 'text-red-500': isNameNearLimit, 
                                 'text-gray-500': !isNameNearLimit
                             }">
                            {{ owner.name?.length || 0 }}/60
                        </div>

                        <!-- Name Error Messages -->
                        <p v-if="showNameMaxLengthError" 
                           class="text-red-500 text-1xl mt-2 px-4">
                            Name is too long.
                        </p>
                        <p v-if="showNameRequiredError" 
                           class="text-red-500 text-1xl mt-2 px-4">
                            Name is required
                        </p>
                    </div>

                    <!-- Email Section -->
                    <div>
                        <label class="block text-xl text-gray-700 mb-2">Email Address</label>
                        <p class="text-gray-500 mb-4">Your current email is {{ currentEmail }}</p>
                        
                        <div v-if="!showVerificationForm">
                            <button 
                                @click="showChangeEmail = true"
                                v-if="!showChangeEmail"
                                class="px-4 py-2 text-black border border-black rounded-xl hover:bg-gray-100"
                            >
                                Change Email Address
                            </button>

                            <div v-else class="space-y-4">
                                <div class="relative">
                                    <input 
                                        type="email" 
                                        v-model="emailInput"
                                        :disabled="isVerifying"
                                        class="text-2xl p-4 border rounded-2xl mt-1 block w-full disabled:bg-gray-50"
                                        :class="{'border-red-500': emailError}"
                                        placeholder="Enter new email address"
                                    >
                                    <button 
                                        v-if="emailInput !== owner.email"
                                        @click="initiateEmailChange"
                                        :disabled="isVerifying || !isValidEmail"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 px-4 py-2 bg-black text-white rounded-xl hover:bg-gray-800 disabled:bg-gray-300"
                                    >
                                        Verify
                                    </button>
                                </div>
                                <button 
                                    @click="cancelEmailChange"
                                    class="text-gray-500 hover:text-gray-700"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <!-- Email Verification Form -->
                        <div v-if="showVerificationForm" class="w-full md:w-[40rem] border border-neutral-200 rounded-3xl p-14 space-y-6">
                            <div class="text-center space-y-10">
                                <svg class="w-20 h-20 mx-auto text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76" />
                                </svg>
                                <h3 class="text-4xl font-bold text-neutral-800">You've got mail</h3>
                                <p class="text-2xl text-neutral-600">We've sent a security code to <br/><span>{{ emailInput }}</span></p>
                            </div>

                            <div class="space-y-10">
                                <div class="space-y-4 pb-8">
                                    <div class="grid grid-cols-6 gap-4">
                                        <input
                                            v-for="i in 6"
                                            :key="i"
                                            :ref="el => codeInputs[i-1] = el"
                                            v-model="codeDigits[i-1]"
                                            type="text"
                                            inputmode="numeric"
                                            maxlength="1"
                                            @input="handleCodeInput($event, i-1)"
                                            @keydown="handleKeydown($event, i-1)"
                                            @paste="handlePaste"
                                            class="w-full h-20 text-2xl text-center border border-neutral-300 rounded-xl focus:border-pink-500 focus:ring-pink-500"
                                        >
                                    </div>
                                </div>

                                <!-- Error Message -->
                                <div v-if="emailError" class="text-red-700 text-lg">
                                    <p>{{ emailError }}</p>
                                </div>

                                <div class="flex flex-col space-y-4">
                                    <button 
                                        @click="verifyCode"
                                        :disabled="!isCodeComplete || isVerifying"
                                        class="w-full flex justify-center p-6 border border-transparent text-2xl font-medium rounded-full text-white bg-gradient-to-r from-[#E41E53] to-[#FF4E85] hover:from-[#FF2E63] hover:to-[#FF5E95] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#FF4E85] transition-all disabled:opacity-50"
                                    >
                                        <template v-if="isVerifying">
                                            <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Verifying...
                                        </template>
                                        <template v-else>
                                            Continue
                                        </template>
                                    </button>

                                    <button 
                                        @click="resendCode"
                                        :disabled="isResending"
                                        class="text-neutral-600 text-xl hover:text-neutral-800"
                                    >
                                        {{ isResending ? 'Resending...' : 'Resend code' }}
                                    </button>

                                    <button 
                                        @click="cancelEmailChange"
                                        class="text-neutral-600 text-xl hover:text-neutral-800"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { ref, computed, inject } from 'vue';
import { required, maxLength, email } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';

const owner = inject('owner');
const errors = inject('errors');

// Local State
const emailInput = ref(owner.email || '');
const emailError = ref('');
const showVerificationForm = ref(false);
const isVerifying = ref(false);
const isResending = ref(false);
const codeInputs = ref([]);
const codeDigits = ref(['', '', '', '', '', '']);
const showChangeEmail = ref(false);

// Add a computed property for the email display
const currentEmail = computed(() => {
    return owner.email || 'No email set';
});

// Validation Rules
const rules = {
    owner: {
        name: {
            required,
            maxLength: maxLength(60),
        }
    }
};

// Setup Vuelidate
const v$ = useVuelidate(rules, { owner });

// Computed Properties
const hasNameError = computed(() => {
    return v$.value.owner.name.$dirty && v$.value.owner.name.$error;
});

const showNameMaxLengthError = computed(() => {
    return v$.value.owner.name.$dirty && v$.value.owner.name.maxLength.$invalid;
});

const showNameRequiredError = computed(() => {
    return v$.value.owner.name.$dirty && v$.value.owner.name.required.$invalid;
});

const isNameNearLimit = computed(() => {
    const count = owner.name?.length || 0;
    return count > 50;
});

const isValidEmail = computed(() => {
    // Basic email validation regex
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(emailInput.value) && emailInput.value !== owner.email;
});

const isCodeComplete = computed(() => {
    return codeDigits.value.every(digit => digit !== '');
});

// Methods
const handleNameInput = () => {
    v$.value.owner.name.$touch();
    if (owner.name?.length > 60) {
        owner.name = owner.name.slice(0, 60);
    }
};

const initiateEmailChange = async () => {
    try {
        isVerifying.value = true;
        emailError.value = '';
        
        const response = await axios.post('/users/email/verify', {
            email: emailInput.value
        });
        
        showVerificationForm.value = true;
    } catch (error) {
        emailError.value = error.response?.data?.message || 'Failed to send verification code';
    } finally {
        isVerifying.value = false;
    }
};

const handleCodeInput = (event, index) => {
    const value = event.target.value;
    if (!/^\d*$/.test(value)) {
        codeDigits.value[index] = '';
        return;
    }
    
    codeDigits.value[index] = value.slice(-1);
    
    if (value && index < 5) {
        codeInputs.value[index + 1]?.focus();
    }
};

const handleKeydown = (event, index) => {
    if (event.key === 'Backspace') {
        if (!codeDigits.value[index] && index > 0) {
            codeDigits.value[index - 1] = '';
            codeInputs.value[index - 1]?.focus();
        }
    }
};

const handlePaste = (event) => {
    event.preventDefault();
    const pastedText = (event.clipboardData || window.clipboardData).getData('text');
    const numbers = pastedText.replace(/[^0-9]/g, '').slice(0, 6);
    
    numbers.split('').forEach((num, index) => {
        if (index < 6) {
            codeDigits.value[index] = num;
        }
    });
    
    const lastIndex = Math.min(numbers.length, 6) - 1;
    if (lastIndex >= 0) {
        codeInputs.value[lastIndex]?.focus();
    }
};

const verifyCode = async () => {
    try {
        isVerifying.value = true;
        emailError.value = '';
        
        const response = await axios.post('/users/email/confirm', {
            email: emailInput.value,
            code: codeDigits.value.join('')
        });
        
        // Update the owner's email
        owner.email = emailInput.value;
        
        // Reset the form state
        showVerificationForm.value = false;
        showChangeEmail.value = false;
        resetVerificationForm();
        
        // Trigger a page reload to update all components
        window.location.reload();
        
    } catch (error) {
        emailError.value = error.response?.data?.message || 'Invalid verification code';
    } finally {
        isVerifying.value = false;
    }
};

const resendCode = async () => {
    try {
        isResending.value = true;
        await initiateEmailChange();
        resetVerificationForm();
    } finally {
        isResending.value = false;
    }
};

const cancelEmailChange = () => {
    showChangeEmail.value = false;
    showVerificationForm.value = false;
    emailInput.value = owner.email;
    resetVerificationForm();
};

const resetVerificationForm = () => {
    codeDigits.value = ['', '', '', '', '', ''];
    emailError.value = '';
};

// Component API
defineExpose({
    isValid: async () => {
        await v$.value.$validate();
        const isValid = !v$.value.$error;
        
        if (!isValid) {
            errors.value = { 
                name: ['Please provide a valid name'] 
            };
        }
        
        return isValid;
    },
    submitData: () => {
        // Include both name and email in the submission data
        const data = {
            name: owner.name
        };
        
        // Only include email if it has changed
        if (emailInput.value !== owner.email) {
            data.email = emailInput.value;
        }
        
        return data;
    }
});
</script>