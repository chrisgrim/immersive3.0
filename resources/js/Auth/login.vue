<template>
    <div class=" bg-white flex items-center justify-center">
        <div class="w-[40rem] border border-neutral-200 rounded-3xl p-14 space-y-6">
            <!-- Initial Form -->
            <div v-if="!isVerification">
                <!-- Logo/Header -->
                <div class="text-center space-y-4">
                    <h2 class="text-4xl font-bold bg-gradient-to-r from-[#E41E53] to-[#FF4E85] bg-clip-text text-transparent">
                        Everything Immersive
                    </h2>
                    <p class="text-2xl font-semibold text-neutral-800">Sign in or create an account</p>
                </div>

                <!-- Status Messages -->
                <div v-if="status" class="p-4 rounded-md bg-green-50 text-green-700 text-lg">
                    {{ status }}
                </div>

                <div v-if="errors.length" class="p-4 rounded-md bg-red-50 text-red-700 text-lg">
                    <ul class="list-disc pl-5">
                        <li v-for="error in errors" :key="error">{{ error }}</li>
                    </ul>
                </div>

                <div class="space-y-6 mt-12">
                    <!-- Social Login Buttons -->
                    <div class="space-y-6">
                        <a href="/auth/google" 
                           class="w-full relative inline-flex items-center justify-center p-6 rounded-full border border-neutral-300 bg-white text-2xl font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="absolute left-6 w-10 h-10" viewBox="0 0 24 24">
                                <path d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z" fill="currentColor"/>
                            </svg>
                            <span>Google</span>
                        </a>
                        
                        <button class="w-full relative inline-flex items-center justify-center p-6 rounded-full border border-neutral-300 bg-white text-2xl font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="absolute left-6 w-10 h-10" viewBox="0 0 24 24">
                                <path d="M17.569 12.6254C17.597 15.652 20.2179 16.6592 20.247 16.672C20.2248 16.743 19.8282 18.1073 18.8662 19.5166C18.0345 20.7339 17.1714 21.9442 15.8117 21.9718C14.4756 21.9987 14.046 21.1799 12.5185 21.1799C10.9917 21.1799 10.5141 21.9442 9.2495 21.9987C7.93704 22.0525 6.93758 20.7073 6.09906 19.4941C4.38557 16.9912 3.0697 12.4642 4.83436 9.39056C5.70786 7.87011 7.26882 6.90148 8.96164 6.87456C10.2449 6.84764 11.4551 7.72783 12.2474 7.72783C13.0391 7.72783 14.5141 6.68009 16.0577 6.84899C16.7516 6.87725 18.5486 7.13516 19.7101 8.80501C19.6107 8.86961 17.5451 10.097 17.569 12.6254ZM15.0536 5.14759C15.749 4.29961 16.2172 3.13026 16.0846 1.9668C15.0667 2.01503 13.8236 2.63159 13.1026 3.47824C12.4538 4.22251 11.8968 5.41963 12.0557 6.55785C13.1849 6.64901 14.3582 5.99558 15.0536 5.14759Z" fill="currentColor"/>
                            </svg>
                            <span>Apple</span>
                        </button>
                    </div>

                    <!-- Divider -->
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-neutral-200"></div>
                        </div>
                        <div class="relative flex justify-center text-2xl">
                            <span class="px-4 bg-white text-neutral-800">Or</span>
                        </div>
                    </div>

                    <!-- Email Form -->
                    <div class="space-y-6">
                        <div>
                            <label for="email" class="sr-only">Email address</label>
                            <input 
                                v-model="email"
                                type="email" 
                                required 
                                class="appearance-none rounded-full w-full p-6 border border-neutral-300 placeholder-neutral-500 text-neutral-900 text-2xl focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-pink-500"
                                placeholder="Email address"
                            >
                        </div>

                        <button 
                            @click="sendCode"
                            :disabled="isResending"
                            class="w-full flex justify-center p-6 border border-transparent text-2xl font-medium rounded-full text-white bg-gradient-to-r from-[#E41E53] to-[#FF4E85] hover:from-[#FF2E63] hover:to-[#FF5E95] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#FF4E85] transition-all disabled:opacity-50"
                        >
                            <template v-if="isResending">
                                <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Sending...
                            </template>
                            <template v-else>
                                Continue
                            </template>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Verification Form -->
            <div v-else class="space-y-8">
                <div class="text-center space-y-10">
                    <svg class="w-20 h-20 mx-auto text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76" />
                    </svg>
                    <h3 class="text-4xl font-bold text-neutral-800">You've got mail</h3>
                    <p class="text-2xl text-neutral-600">We've sent a security code to <br/><span>{{ email }}</span></p>
                </div>

                <div class="text-center pb-8">
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="transform opacity-0"
                        enter-to-class="transform opacity-100"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="transform opacity-100"
                        leave-to-class="transform opacity-0"
                    >
                        <div v-if="showResendStatus && status" class="text-1xl text-green-600">
                            {{ status }}
                        </div>
                        <button 
                            v-else 
                            @click="resendCode"
                            :disabled="isResending"
                            class="text-1xl text-neutral-600 underline disabled:opacity-50"
                        >
                            <template v-if="isResending">
                                Resending...
                            </template>
                            <template v-else>
                                Resend code
                            </template>
                        </button>
                    </Transition>
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
                                @input="handleInput($event, i-1)"
                                @keydown="handleKeydown($event, i-1)"
                                @paste="handlePaste"
                                class="w-full h-20 text-2xl text-center border border-neutral-300 rounded-xl focus:border-pink-500 focus:ring-pink-500"
                            >
                        </div>
                    </div>

                     <!-- Error Messages -->
                    <div v-if="errors.length" class="text-red-700 text-lg">
                        <ul class="list-disc pl-5">
                            <li v-for="error in errors" :key="error">{{ error }}</li>
                        </ul>
                    </div>

                    <button 
                        @click="verifyCode"
                        :disabled="verificationCode.length !== 6 || isVerifying"
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

                    <div class="text-center text-xl">
                        <span class="text-neutral-600">Having trouble?</span>
                        <a href="#" class="text-neutral-800 hover:underline ml-1">Get Support</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import axios from 'axios'

// Define props
const props = defineProps({
    flashData: {
        type: Object,
        default: () => ({})
    }
})

// Computed property for auto code
const autoCode = computed(() => {
    return props.flashData?.auto_code
})

const autoEmail = computed(() => {
    return props.flashData?.auto_email
})

const email = ref('')
const verificationCode = ref('')
const isVerification = ref(false)
const errors = ref([])
const status = ref('')
const codeInputs = ref([])
const codeDigits = ref(['', '', '', '', '', ''])
const isResending = ref(false)
const isVerifying = ref(false)
const showResendStatus = ref(false)

// Set default CSRF token for all axios requests
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content

// Watch codeDigits array to update verificationCode
watch(codeDigits, (newDigits) => {
    verificationCode.value = newDigits.join('')
}, { deep: true })

const sendCode = async () => {
    isResending.value = true
    try {
        if (autoCode.value) {
            // If we have an auto-code, skip sending and go straight to verification
            isVerification.value = true
            autoCode.value.split('').forEach((digit, index) => {
                codeDigits.value[index] = digit
            })
            await verifyCode()
        } else {
            // Normal flow - request new code
            const response = await axios.post('/login/code', {
                email: email.value
            })
            isVerification.value = true
        }
        errors.value = []
    } catch (error) {
        errors.value = error.response?.data?.errors?.email || ['An error occurred']
    } finally {
        isResending.value = false
    }
}

const handleInput = (event, index) => {
    const value = event.target.value
    // Only allow numbers
    if (!/^\d*$/.test(value)) {
        codeDigits.value[index] = ''
        return
    }
    
    // Take only the last character if multiple characters are entered
    codeDigits.value[index] = value.slice(-1)
    
    // Move to next input if available
    if (value && index < 5) {
        codeInputs.value[index + 1]?.focus()
    }
}

const handleKeydown = (event, index) => {
    // Handle backspace
    if (event.key === 'Backspace') {
        if (!codeDigits.value[index] && index > 0) {
            codeDigits.value[index - 1] = ''
            codeInputs.value[index - 1]?.focus()
        }
    }
}

const handlePaste = (event) => {
    event.preventDefault()
    const pastedText = (event.clipboardData || window.clipboardData).getData('text')
    const numbers = pastedText.replace(/[^0-9]/g, '').slice(0, 6)
    
    // Distribute numbers across inputs
    numbers.split('').forEach((num, index) => {
        if (index < 6) {
            codeDigits.value[index] = num
        }
    })
    
    // Focus last filled input or first empty input
    const lastIndex = Math.min(numbers.length, 6) - 1
    if (lastIndex >= 0) {
        codeInputs.value[lastIndex]?.focus()
    }
}

const verifyCode = async () => {
    isVerifying.value = true
    try {
        const response = await axios.post('/login/verify', {
            email: email.value,
            code: verificationCode.value
        })

        if (response.data.redirect) {
            window.location.href = response.data.redirect
        }
    } catch (error) {
        if (error.response?.data?.errors?.code) {
            errors.value = error.response.data.errors.code
        } else if (error.response?.data?.message) {
            errors.value = [error.response.data.message]
        } else {
            errors.value = ['An error occurred']
        }
    } finally {
        isVerifying.value = false
    }
}

const resendCode = async () => {
    try {
        isResending.value = true
        await sendCode()
        codeDigits.value = ['', '', '', '', '', '']
        verificationCode.value = ''
        status.value = 'Code resent successfully'
        showResendStatus.value = true
        
        setTimeout(() => {
            showResendStatus.value = false
            status.value = ''
        }, 10000)
    } catch (error) {
        errors.value = ['Failed to resend code']
    } finally {
        isResending.value = false
    }
}

onMounted(() => {
    if (autoCode.value && autoCode.value.length === 6) {
        // If we have both code and email, proceed automatically
        if (autoEmail.value) {
            email.value = autoEmail.value
            isVerification.value = true
            autoCode.value.split('').forEach((digit, index) => {
                codeDigits.value[index] = digit
            })
            
            // Short delay before verification
            setTimeout(() => {
                verifyCode()
            }, 500)
        } else {
            // If we only have the code, show the prompt for email
            status.value = 'We detected your login code. Please enter your email to continue.'
            
            // Watch for email input
            watch(email, (newEmail) => {
                if (newEmail) {
                    isVerification.value = true
                    autoCode.value.split('').forEach((digit, index) => {
                        codeDigits.value[index] = digit
                    })
                    setTimeout(() => {
                        verifyCode()
                    }, 500)
                }
            })
        }
    }
})
</script>

<style scoped>
/* Hide number input spinners */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type=number] {
    -moz-appearance: textfield;
}
</style>