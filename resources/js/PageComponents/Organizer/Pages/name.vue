<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>What's your organization called?</h2>
                <div class="mt-6">
                    <!-- Organization Name Input -->
                    <textarea 
                        name="name" 
                        :class="[
                            'text-4xl p-4 border rounded-2xl mt-1 block w-full',
                            {
                                'border-red-500 focus:border-red-500 focus:shadow-focus-error': showNameError,
                                'border-[#222222] focus:border-black focus:shadow-focus-black': !showNameError,
                                'bg-gray-100 text-gray-500 cursor-not-allowed': hasPendingNameChange || (organizer.status !== 'p' && organizer.status !== 'n')
                            }
                        ]"
                        v-model="organizer.name" 
                        @input="handleNameInput"
                        placeholder="Enter organization name"
                        rows="2" 
                        :disabled="hasPendingNameChange || (organizer.status !== 'p' && organizer.status !== 'n')"
                    />
                    
                    <!-- Name Character Count -->
                    <div class="flex justify-end mt-1" 
                         :class="{
                             'text-red-500': isNameNearLimit, 
                             'text-gray-500': !isNameNearLimit
                         }">
                        {{ organizer.name?.length || 0 }}/60
                        <span v-if="hasPendingNameChange" class="ml-2 italic">
                            (Name change pending approval)
                        </span>
                        <span v-else-if="organizer.status !== 'p' && organizer.status !== 'n'" class="ml-2 italic">
                            (Organization pending review)
                        </span>
                    </div>

                    <!-- Name Error Messages -->
                    <p v-if="showNameMaxLengthError" 
                       class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                        Organization name is too long.
                    </p>
                    <p v-if="showNameRequiredError" 
                       class="text-red-500 text-1xl mt-[-2.5rem] mb-8 px-4">
                        Organization name is required
                    </p>

                    <!-- Description Section -->
                    <div v-if="organizer.name">
                        <p class="text-black font-medium mb-4">Description</p>
                        <textarea 
                            name="description" 
                            :class="[
                                'p-4 border rounded-2xl mt-1 block w-full',
                                {
                                    'border-red-500 focus:border-red-500 focus:shadow-focus-error': showDescriptionError,
                                    'border-[#222222] focus:border-black focus:shadow-focus-black': !showDescriptionError
                                }
                            ]"
                            v-model="organizer.description" 
                            @input="handleDescriptionInput"
                            placeholder="Tell people about your organization"
                            rows="9" 
                        />

                        <!-- Description Character Count -->
                        <div class="flex justify-end mt-1 relative" 
                             :class="{'text-red-500': isDescriptionNearLimit, 'text-gray-500': !isDescriptionNearLimit}">
                            {{ organizer.description?.length || 0 }}/2000
                            <!-- Description Error Messages -->
                            <p v-if="showDescriptionMaxLengthError" 
                               class="text-red-500 text-1xl px-4 absolute left-0 top-0">
                                Description is too long.
                            </p>
                            <p v-if="showDescriptionRequiredError" 
                               class="text-red-500 text-1xl px-4 absolute left-0 top-0">
                                Description is required
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
                        <p class="text-gray-500 font-normal">Submit a request to change your organization's name</p>
                    </div>

                    <!-- Content -->
                    <div class="p-8 overflow-y-auto flex-1">
                        <div class="space-y-6">
                            <p class="text-gray-600">
                                Changing the organization name requires admin approval. Once submitted:
                            </p>
                            <ul class="text-gray-600 list-disc ml-5">
                                <li>The current name will remain until approved</li>
                                <li>The name field will be locked until a decision is made</li>
                            </ul>
                            <div class="mt-8">
                                <p class="text-gray-500 font-normal mb-4">New Name</p>
                                <p class="text-4xl font-bold break-words hyphens-auto">
                                    {{ organizer.name }}
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

// Injected Dependencies
const organizer = inject('organizer');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');

// Validation Rules
const rules = {
    organizer: {
        name: {
            required,
            maxLength: maxLength(60),
        },
        description: {
            required,
            maxLength: maxLength(2000),
        },
    }
};

// Setup Vuelidate
const v$ = useVuelidate(rules, { organizer });

// Name validations
const showNameError = computed(() => {
    return v$.value.organizer.name.$dirty && v$.value.organizer.name.$error;
});

const showNameMaxLengthError = computed(() => {
    return v$.value.organizer.name.$dirty && v$.value.organizer.name.maxLength.$invalid;
});

const showNameRequiredError = computed(() => {
    return v$.value.organizer.name.$dirty && v$.value.organizer.name.required.$invalid;
});

const isNameNearLimit = computed(() => {
    const count = organizer.name?.length || 0;
    return count > 50;
});

// Description validations
const showDescriptionError = computed(() => {
    return v$.value.organizer.description.$dirty && v$.value.organizer.description.$error;
});

const showDescriptionMaxLengthError = computed(() => {
    return v$.value.organizer.description.$dirty && v$.value.organizer.description.maxLength.$invalid;
});

const showDescriptionRequiredError = computed(() => {
    return v$.value.organizer.description.$dirty && v$.value.organizer.description.required.$invalid;
});

const isDescriptionNearLimit = computed(() => {
    const count = organizer.description?.length || 0;
    return count > 1900;
});

// Store original name for reset functionality
const originalName = ref(organizer.name);

// Methods
const handleNameInput = () => {
    v$.value.organizer.name.$touch();
    if (organizer.name?.length > 60) {
        organizer.name = organizer.name.slice(0, 60);
    }
};

const handleDescriptionInput = () => {
    v$.value.organizer.description.$touch();
    if (organizer.description?.length > 2000) {
        organizer.description = organizer.description.slice(0, 2000);
    }
};

// Name change modal state
const showNameChangeModal = ref(false);
const pendingNameChange = ref('');

const confirmNameChange = async () => {
    try {
        isSubmitting.value = true;
        const response = await axios.post(`/organizers/${organizer.slug}/name-change`, {
            requested_name: organizer.name,
            current_name: originalName.value
        });
        
        showNameChangeModal.value = false;
        
        if (response.data.organizer) {
            organizer.name_change_requests = response.data.organizer.name_change_requests;
        }
        
        organizer.name = originalName.value;
        
    } catch (error) {
        console.error('Error submitting name change:', error);
        alert('Failed to submit name change request');
    } finally {
        isSubmitting.value = false;
    }
};

const cancelNameChange = () => {
    showNameChangeModal.value = false;
    organizer.name = originalName.value;
    v$.value.organizer.name.$reset();
};

const hasPendingNameChange = computed(() => {
    return organizer.name_change_requests?.some(request => request.status === 'pending');
});

// Component API
defineExpose({
    isValid: async () => {
        await v$.value.$validate();
        const isValid = !v$.value.$error;
        
        if (!isValid) {
            errors.value = { 
                name: ['Please provide valid organization name and description'] 
            };
        }
        
        return isValid;
    },
    submitData: async () => {
        if (organizer.name !== originalName.value && organizer.status !== 'n') {
            pendingNameChange.value = organizer.name;
            showNameChangeModal.value = true;
            return false;
        }
        
        return {
            name: organizer.name,
            description: organizer.description
        };
    }
});
</script>