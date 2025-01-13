<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>What's your event called?</h2>
                <p class="text-gray-500 font-normal mt-4">Enter a unique name for your event</p>
                <div class="mt-6">
                    <!-- Event Name Input -->
                    <textarea 
                        name="name" 
                        :class="[
                            'text-4xl font-normal border rounded-2xl p-4 w-full mt-8',
                            {
                                'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': showNameError,
                                'border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black]': !showNameError,
                                'bg-gray-100 text-gray-500 cursor-not-allowed': hasPendingNameChange
                            }
                        ]"
                        v-model="event.name" 
                        @input="handleNameInput"
                        placeholder="Enter event name"
                        rows="3" 
                        :disabled="hasPendingNameChange"
                    />
                    
                    <!-- Name Character Count -->
                    <div class="flex justify-end mt-1" 
                         :class="{
                             'text-red-500': isNameNearLimit, 
                             'text-gray-500': !isNameNearLimit
                         }">
                        {{ event.name?.length || 0 }}/100
                        <span v-if="hasPendingNameChange" class="ml-2 italic">
                            (Name change pending approval)
                        </span>
                    </div>

                    <!-- Name Error Messages -->
                    <p v-if="showNameMaxLengthError" 
                       class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                        Event name is too long.
                    </p>
                    <p v-if="showNameRequiredError" 
                       class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                        Event name is required
                    </p>

                    <!-- Tag Line Section -->
                    <div v-if="event.name">
                        <p class="text-gray-500 font-normal">Tag Line</p>
                        <textarea 
                            name="tag_line" 
                            class="text-2xl border border-[#222222] focus:border-black focus:shadow-[0_0_0_1.5px_black] rounded-2xl p-4 w-full mt-4" 
                            :class="{ 
                                'border-red-500 focus:border-red-500 focus:shadow-[0_0_0_1.5px_#ef4444]': showTagLineError,
                                'focus:border-black focus:shadow-[0_0_0_1.5px_black]': !showTagLineError 
                            }"
                            v-model="event.tag_line" 
                            @input="handleTagLineInput"
                            placeholder="Enter a catchy tagline"
                            rows="2" 
                        />

                        <!-- Tag Line Character Count -->
                        <div class="flex justify-end mt-1 relative" 
                             :class="{'text-red-500': isTagLineNearLimit, 'text-gray-500': !isTagLineNearLimit}">
                            {{ event.tag_line?.length || 0 }}/255
                                <!-- Tag Line Error Messages -->
                            <p v-if="showTagLineMaxLengthError" 
                            class="text-red-500 text-1xl px-4 absolute left-0 top-0">
                                Event tag line is too long.
                            </p>
                            <p v-if="showTagLineRequiredError" 
                            class="text-red-500 text-1xl px-4 absolute left-0 top-0">
                                Tag line is required
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Name Change Modal -->
        <teleport to="body">
            <div v-if="showNameChangeModal" 
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
                <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                    <!-- Header -->
                    <div class="p-8 pb-6">
                        <h2 class="text-2xl font-bold mb-2">Name Change Request</h2>
                        <p class="text-gray-500 font-normal">Submit a request to change your event's name</p>
                    </div>

                    <!-- Scrollable Content -->
                    <div class="p-8 overflow-y-auto flex-1">
                        <div class="space-y-6">
                            <p class="text-gray-600">
                                Changing the event name requires admin approval. Once submitted:
                            </p>
                            <ul class="text-gray-600 list-disc ml-5">
                                <li>The current name will remain until approved</li>
                                <li>The name field will be locked until a decision is made</li>
                            </ul>
                            <div class="mt-8">
                                <p class="text-gray-500 font-normal mb-4">New Name</p>
                                <p class="text-4xl font-bold">
                                    {{ event.name }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                        <div class="flex justify-end space-x-4">
                            <button 
                                @click="cancelNameChange"
                                class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="confirmNameChange"
                                :disabled="isSubmitting"
                                class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl"
                            >
                                Submit Request
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>
    </main>
</template>

<script setup>
import { inject, computed, ref } from 'vue';
import { required, maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import axios from 'axios';

// 1. Injected Dependencies
const event = inject('event');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');

// 2. Validation Rules
const rules = {
    event: {
        name: {
            required,
            maxLength: maxLength(100),
        },
        tag_line: {
            required,
            maxLength: maxLength(255),
        },
    }
};

// 3. Setup Vuelidate
const $v = useVuelidate(rules, { event });

// 4. Computed Properties
// Name validations
const showNameError = computed(() => {
    return $v.value.event.name.$dirty && $v.value.event.name.$error;
});

const showNameMaxLengthError = computed(() => {
    return $v.value.event.name.$dirty && $v.value.event.name.maxLength.$invalid;
});

const showNameRequiredError = computed(() => {
    return $v.value.event.name.$dirty && $v.value.event.name.required.$invalid;
});

const isNameNearLimit = computed(() => {
    const count = event.name?.length || 0;
    return count > 90;
});

// Tag line validations
const showTagLineError = computed(() => {
    return $v.value.event.tag_line.$dirty && $v.value.event.tag_line.$error;
});

const showTagLineMaxLengthError = computed(() => {
    return $v.value.event.tag_line.$dirty && $v.value.event.tag_line.maxLength.$invalid;
});

const showTagLineRequiredError = computed(() => {
    return $v.value.event.tag_line.$dirty && $v.value.event.tag_line.required.$invalid;
});

const isTagLineNearLimit = computed(() => {
    const count = event.tag_line?.length || 0;
    return count > 235;
});

// Store original name for reset functionality
const originalName = ref(event.name);

// Add reset method
const resetName = () => {
    event.name = originalName.value;
    $v.value.event.name.$reset();
};

// 5. Methods
const handleNameInput = () => {
    $v.value.event.name.$touch();
    if (event.name?.length > 100) {
        event.name = event.name.slice(0, 100);
    }
};

const handleTagLineInput = () => {
    $v.value.event.tag_line.$touch();
    if (event.tag_line?.length > 255) {
        event.tag_line = event.tag_line.slice(0, 255);
    }
};

// 6. Component API
defineExpose({
    isValid: async () => {
        await $v.value.$validate();
        const isValid = !$v.value.$error;
        
        if (!isValid) {
            errors.value = { 
                name: ['Please provide valid event name and tag line'] 
            };
        }
        
        return isValid;
    },
    submitData: async () => {
        // Check if this is a name change for a published/embargoed event
        if (['p', 'e'].includes(event.status) && event.name !== originalName.value) {
            pendingNameChange.value = event.name;
            showNameChangeModal.value = true;
            return false; // Prevent immediate submission
        }
        
        return {
            name: event.name,
            tag_line: event.tag_line
        };
    },
    resetName
});

const showNameChangeModal = ref(false);
const pendingNameChange = ref('');

const emit = defineEmits(['showSuccess']);

const confirmNameChange = async () => {
    try {
        isSubmitting.value = true;
        const response = await axios.post(`/hosting/event/${event.slug}/name-change`, {
            requested_name: event.name,
            current_name: originalName.value
        });
        
        showNameChangeModal.value = false;
        emit('showSuccess');
        
        // Update the event data with the fresh data from response
        if (response.data.event) {
            // Update name change requests
            event.name_change_requests = response.data.event.name_change_requests;
        }
        
        // Reset the name back to original
        event.name = originalName.value;
        
    } catch (error) {
        console.error('Error submitting name change:', error);
        alert('Failed to submit name change request');
    } finally {
        isSubmitting.value = false;
    }
};

const cancelNameChange = () => {
    showNameChangeModal.value = false;
    event.name = originalName.value;
    $v.value.event.name.$reset();
};

// Add this computed property
const hasPendingNameChange = computed(() => {
    return event.name_change_requests?.some(request => request.status === 'pending');
});
</script>
