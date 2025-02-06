<template>
    <main class="w-full min-h-fit">
        <div class="flex flex-col w-full">
            <div>
                <h2>Community Name</h2>
                <p class="text-neutral-500 font-normal mt-4">
                    <span v-if="hasPendingNameChange">
                        A name change request is pending approval. Current name: {{ originalName }}
                    </span>
                    <span v-else>
                        Enter a name for your community
                    </span>
                </p>
                <div class="mt-6">
                    <!-- Name Input -->
                    <textarea 
                        name="name" 
                        :class="[
                            'text-4xl font-normal border rounded-2xl p-4 w-full mt-8',
                            {
                                'border-red-500 focus:shadow-focus-error': showNameError,
                                'border-[#222222] focus:shadow-focus-black': !showNameError,
                                'bg-gray-100 text-gray-500 cursor-not-allowed': hasPendingNameChange || (community.status !== 'p' && community.status !== 'n')
                            }
                        ]"
                        v-model="community.name" 
                        @input="handleNameInput"
                        placeholder="Enter community name"
                        rows="2" 
                        :disabled="hasPendingNameChange || (community.status !== 'p' && community.status !== 'n')"
                    />
                    
                    <!-- Name Character Count -->
                    <div class="flex justify-end mt-1" 
                         :class="{'text-red-500': isNameNearLimit, 'text-neutral-500': !isNameNearLimit}">
                        {{ community.name?.length || 0 }}/100
                        <span v-if="hasPendingNameChange" class="ml-2 italic">
                            (Name change pending approval)
                        </span>
                        <span v-else-if="community.status !== 'p' && community.status !== 'n'" class="ml-2 italic">
                            (Community pending review)
                        </span>
                    </div>

                    <!-- Blurb Input -->
                    <div class="mt-8">
                        <p class="text-neutral-500 font-normal">Blurb</p>
                        <textarea 
                            name="blurb" 
                            class="text-2xl border rounded-2xl p-4 w-full mt-4" 
                            :class="{ 
                                'border-red-500 focus:shadow-focus-error': showBlurbError,
                                'border-[#222222] focus:shadow-focus-black': !showBlurbError 
                            }"
                            v-model="community.blurb" 
                            @input="handleBlurbInput"
                            placeholder="Enter a short description"
                            rows="3" 
                        />

                        <!-- Blurb Character Count -->
                        <div class="flex justify-end mt-1" 
                             :class="{'text-red-500': isBlurbNearLimit, 'text-neutral-500': !isBlurbNearLimit}">
                            {{ community.blurb?.length || 0 }}/254
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
                        <p class="text-gray-500 font-normal">Submit a request to change your community's name</p>
                    </div>

                    <!-- Content -->
                    <div class="p-8 overflow-y-auto flex-1">
                        <div class="space-y-6">
                            <p class="text-gray-600">
                                Changing the community name requires admin approval. Once submitted:
                            </p>
                            <ul class="text-gray-600 list-disc ml-5">
                                <li>The current name will remain until approved</li>
                                <li>The name field will be locked until a decision is made</li>
                            </ul>
                            <div class="mt-8">
                                <p class="text-gray-500 font-normal mb-4">New Name</p>
                                <p class="text-4xl font-bold break-words hyphens-auto">
                                    {{ community.name }}
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
import { inject, computed, ref, watch, onMounted } from 'vue';
import { required, maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import axios from 'axios';

// Injected dependencies
const community = inject('community');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');

// State
const originalName = ref(community.name);
const showNameChangeModal = ref(false);

// Initialize name_change_requests if needed
if (!community.name_change_requests) {
    community.name_change_requests = [];
}

// Validation setup
const rules = {
    community: {
        name: { required, maxLength: maxLength(100) },
        blurb: { required, maxLength: maxLength(254) }
    }
};
const v$ = useVuelidate(rules, { community });

// Computed properties
const showNameError = computed(() => v$.value.community.name.$dirty && v$.value.community.name.$error);
const isNameNearLimit = computed(() => (community.name?.length || 0) > 90);
const showBlurbError = computed(() => v$.value.community.blurb.$dirty && v$.value.community.blurb.$error);
const isBlurbNearLimit = computed(() => (community.blurb?.length || 0) > 244);
const hasPendingNameChange = computed(() => {
    return Array.isArray(community.name_change_requests) && 
           community.name_change_requests.some(request => request.status === 'pending');
});

// Lifecycle hooks
onMounted(() => {
    originalName.value = community.name;
});

// Watchers
watch(() => community.name, (newName) => {
    if (newName === originalName.value) {
        showNameChangeModal.value = false;
    }
});

// Methods
const handleNameInput = () => {
    v$.value.community.name.$touch();
    if (community.name?.length > 100) {
        community.name = community.name.slice(0, 100);
    }
};

const handleBlurbInput = () => {
    v$.value.community.blurb.$touch();
    if (community.blurb?.length > 254) {
        community.blurb = community.blurb.slice(0, 254);
    }
};

const confirmNameChange = async () => {
    try {
        isSubmitting.value = true;
        const response = await axios.post(`/communities/${community.slug}/name-change`, {
            requested_name: community.name,
            current_name: originalName.value
        });
        
        community.name = originalName.value;
        community.name_change_requests.push({
            status: 'pending',
            requested_name: response.data.requested_name || response.data.current_name
        });
        
        showNameChangeModal.value = false;
        return false;
    } catch (error) {
        console.error('Error submitting name change:', error);
        alert('Failed to submit name change request');
        community.name = originalName.value;
        return false;
    } finally {
        isSubmitting.value = false;
    }
};

const cancelNameChange = () => {
    showNameChangeModal.value = false;
    community.name = originalName.value;
    v$.value.community.name.$reset();
};

// Component API
defineExpose({
    isValid: async () => {
        await v$.value.$validate();
        const isValid = !v$.value.$error;
        if (!isValid) {
            errors.value = { name: ['Please provide valid name and blurb'] };
        }
        return isValid;
    },
    submitData: async () => {
        if (hasPendingNameChange.value) {
            return false;
        }

        if (community.name !== originalName.value && community.status === 'p') {
            showNameChangeModal.value = true;
            return false;
        }
        
        return {
            name: community.name,
            blurb: community.blurb
        };
    }
});
</script>