<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2 class="text-black">What's your event called?</h2>
                <p class="text-neutral-500 font-normal mt-4">Enter a unique name for your event</p>
                <div class="mt-6">
                    <!-- Event Name Input -->
                    <textarea 
                        name="name" 
                        :class="[
                            'text-4xl font-normal border rounded-2xl p-4 w-full mt-8 transition-all duration-200',
                            {
                                'border-red-500 focus:border-red-500 focus:shadow-focus-error': showNameError || errors?.name,
                                'border-orange-400 focus:border-orange-400 focus:shadow-focus-warning': duplicateWarning,
                                'border-neutral-300 hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': !showNameError && !errors?.name && !duplicateWarning,
                                'bg-neutral-100 text-neutral-500 cursor-not-allowed': hasPendingNameChange
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
                             'text-neutral-500': !isNameNearLimit
                         }">
                        {{ event.name?.length || 0 }}/100
                        <span v-if="hasPendingNameChange" class="ml-2 italic">
                            (Name change pending approval)
                        </span>
                    </div>

                    <!-- Duplicate Name Warning -->
                    <div v-if="duplicateWarning" class="text-orange-600 text-1xl mt-2 mb-4 px-4 bg-orange-50 p-3 rounded-lg border border-orange-300">
                        <p class="font-medium">⚠️ Possible duplicate event name found:</p>
                        <p class="mt-1 mb-2">{{ duplicateWarning }}</p>
                        <div v-if="duplicateEvents.length > 0" class="mt-2">
                            <div v-for="duplicate in duplicateEvents" :key="duplicate.id" class="flex items-center justify-between py-1">
                                <span>{{ duplicate.name }}</span>
                                <a :href="`/events/${duplicate.slug}`" 
                                   target="_blank"
                                   class="text-blue-600 hover:text-blue-800 underline">
                                    View Event
                                </a>
                            </div>
                        </div>
                        <p class="mt-4 text-gray-600 italic text-lg">
                            If you feel this is a mistake, please contact us at <a href="mailto:support@everythingimmersive.com" class="text-blue-600 hover:underline">support@everythingimmersive.com</a>
                        </p>
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
                    
                    <!-- Server Validation Errors -->
                    <p v-if="errors?.name && !showNameMaxLengthError && !showNameRequiredError" 
                       class="text-red-500 text-1xl mb-4">
                        {{ errors.name[0] }}
                    </p>

                    <!-- Tag Line Section -->
                    <div v-if="event.name">
                        <p class="text-neutral-500 font-normal">Tag Line</p>
                        <textarea 
                            name="tag_line" 
                            class="text-2xl border rounded-2xl p-4 w-full mt-4 transition-all duration-200" 
                            :class="{ 
                                'border-red-500 focus:border-red-500 focus:shadow-focus-error': showTagLineError,
                                'border-neutral-300 hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': !showTagLineError 
                            }"
                            v-model="event.tag_line" 
                            @input="handleTagLineInput"
                            placeholder="Enter a catchy tagline"
                            rows="2" 
                        />

                        <!-- Tag Line Character Count -->
                        <div class="flex justify-end mt-1 relative" 
                             :class="{'text-red-500': isTagLineNearLimit, 'text-neutral-500': !isTagLineNearLimit}">
                            {{ event.tag_line?.length || 0 }}/250
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

// Add state for duplicate names
const duplicateWarning = ref('');
const duplicateEvents = ref([]);

// 2. Validation Rules
const rules = {
    event: {
        name: {
            required,
            maxLength: maxLength(100),
        },
        tag_line: {
            required,
            maxLength: maxLength(250),
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
    
    // Clear server validation errors when user edits
    if (errors.value?.name) {
        errors.value = {};
    }

    // Clear duplicate warning when user edits the name
    duplicateWarning.value = '';
    duplicateEvents.value = [];
};

const handleTagLineInput = () => {
    $v.value.event.tag_line.$touch();
    if (event.tag_line?.length > 250) {
        event.tag_line = event.tag_line.slice(0, 250);
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
    resetName,
    // Add method to handle duplicate name errors
    handleDuplicateError: (error) => {
        if (error.response?.status === 409) {
            duplicateWarning.value = error.response.data.warning || 'A similar event name already exists.';
            duplicateEvents.value = error.response.data.duplicateEvents || [];
            return true; // Error was handled
        }
        return false; // Error was not handled
    }
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
        showNameChangeModal.value = false;
        
        // Handle validation errors (422 status)
        if (error.response?.status === 422) {
            // Extract error message from response
            const errorData = error.response.data;
            errors.value = {
                name: errorData.errors?.requested_name || 
                      (errorData.message ? [errorData.message] : ['Unable to submit name change request'])
            };
        } else {
            // For other errors, alert and reset name
            alert('Failed to submit name change request');
            event.name = originalName.value;
        }
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
