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
                                'border-neutral-300 hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': !showNameError && !errors?.name,
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

                    <!-- Dismissed state: a small re-openable chip on the left. "This isn't mine" collapses
                         the full alert to this; clicking it brings the alert back. -->
                    <button v-if="duplicateWarning && duplicateDismissed"
                            type="button"
                            @click="duplicateDismissed = false"
                            class="mt-2 mb-4 inline-flex items-center gap-2 bg-orange-50 border border-orange-200 rounded-lg px-3 py-2 text-xl text-orange-700 hover:bg-orange-100">
                        ⚠️ Duplicate name accepted — <span class="underline">reopen</span>
                    </button>

                    <!-- Duplicate Name Warning (full) -->
                    <div v-if="duplicateWarning && !duplicateDismissed" class="mt-2 mb-4 bg-orange-50 p-5 rounded-2xl border border-orange-300">
                        <p class="text-3xl font-semibold text-orange-700">⚠️ This event may already be on EI</p>

                        <div v-if="duplicateEvents.length > 0" class="mt-4 space-y-3">
                            <div v-for="duplicate in duplicateEvents" :key="duplicate.id"
                                 class="bg-white rounded-xl p-5 border border-orange-200">
                                <!-- The existing listing -->
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <span class="text-2.5xl font-semibold text-gray-900">{{ duplicate.name }}</span>
                                        <span v-if="duplicate.organizer" class="block text-2xl text-gray-500 mt-1">
                                            Added by {{ duplicate.organizer.name }}
                                        </span>
                                    </div>
                                    <a :href="`/events/${duplicate.slug}`"
                                       target="_blank"
                                       class="shrink-0 text-2xl text-blue-600 hover:text-blue-800 underline whitespace-nowrap">
                                        View event
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Two deliberate buttons — neither auto-advances the wizard. "Claim" opens a modal;
                             "This isn't mine" just collapses this alert (you continue with the wizard's Next). -->
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <button v-if="claimableDuplicate"
                                    type="button"
                                    @click="openClaimModal(claimableDuplicate)"
                                    class="px-5 py-2.5 rounded-xl border border-gray-300 bg-white text-2xl text-gray-800 hover:bg-gray-50">
                                I believe this is my event and org
                            </button>
                            <button type="button"
                                    @click="dismissDuplicate"
                                    class="px-5 py-2.5 rounded-xl border border-gray-300 bg-white text-2xl text-gray-800 hover:bg-gray-50">
                                Accept duplicate name
                            </button>
                        </div>
                        <p class="text-xl text-gray-400 mt-3">
                            Questions? <a href="mailto:support@everythingimmersive.com" class="text-blue-600 hover:underline">support@everythingimmersive.com</a>
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
                            class="text-2.5xl border rounded-2xl p-4 w-full mt-4 transition-all duration-200" 
                            :class="{ 
                                'border-red-500 focus:border-red-500 focus:shadow-focus-error': showTagLineError,
                                'border-neutral-300 hover:border-[#222222] focus:border-[#222222] focus:shadow-focus-black': !showTagLineError 
                            }"
                            v-model="event.tag_line" 
                            @input="handleTagLineInput"
                            placeholder="Enter a catchy tagline"
                            rows="4" 
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

        <!-- Claim-organization modal — opened from the duplicate warning's "Is this your organization?"
             link. Holds the explanation + the actual Claim action so the inline warning stays light. -->
        <teleport to="body">
            <div v-if="claimModalDup"
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-end md:items-center justify-center z-50">
                <div class="bg-white w-full md:max-w-2xl md:mx-4 md:rounded-2xl rounded-t-2xl shadow-xl flex flex-col max-h-[90vh] relative z-50">
                    <!-- Header -->
                    <div class="p-8 pb-6">
                        <h2 class="text-3xl font-bold mb-2">Claim {{ claimModalDup.organizer.name }}?</h2>
                    </div>

                    <!-- Content -->
                    <div class="p-8 pt-0 overflow-y-auto flex-1">
                        <p class="text-2xl text-gray-600 font-normal leading-relaxed">
                            Our team already added <span class="font-medium text-gray-900">“{{ claimModalDup.name }}”</span>
                            to Everything Immersive under <span class="font-medium text-gray-900">{{ claimModalDup.organizer.name }}</span> organization.
                        </p>
                        <p class="text-2xl text-gray-600 font-normal leading-relaxed mt-4">
                            If that's your organization, press <span class="font-medium text-gray-900">“Claim it”</span> to take ownership
                            and manage the events yourself. Please allow a day or two for our admins to review the request.
                        </p>
                        <p v-if="claimState(claimModalDup.organizer).status === 'error'" class="text-red-600 text-2xl mt-4">
                            {{ claimState(claimModalDup.organizer).message }}
                        </p>
                    </div>

                    <!-- Footer -->
                    <div class="p-8 border-t border-neutral-400 bg-white md:rounded-b-2xl">
                        <div class="flex justify-end space-x-4">
                            <button
                                @click="closeClaimModal"
                                :disabled="claimState(claimModalDup.organizer).status === 'submitting'"
                                class="px-6 py-3 border border-neutral-400 rounded-2xl hover:bg-neutral-50 text-xl disabled:opacity-50"
                            >
                                Cancel
                            </button>
                            <button
                                @click="requestOwnership(claimModalDup.organizer)"
                                :disabled="claimState(claimModalDup.organizer).status === 'submitting'"
                                class="px-6 py-3 bg-black text-white rounded-2xl hover:bg-gray-800 text-xl disabled:opacity-50"
                            >
                                {{ claimState(claimModalDup.organizer).status === 'submitting' ? 'Submitting…' : 'Claim it' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>
    </main>
</template>

<script setup>
import { inject, computed, ref, reactive } from 'vue';
import { required, maxLength } from '@vuelidate/validators';
import useVuelidate from '@vuelidate/core';
import axios from 'axios';

// 1. Injected Dependencies
const event = inject('event');
const errors = inject('errors');
const isSubmitting = inject('isSubmitting');
// Greys out the wizard's Next button while an unresolved duplicate warning is showing.
const setComponentReady = inject('setComponentReady', () => {});

// Add state for duplicate names
const duplicateWarning = ref('');
const duplicateEvents = ref([]);
const duplicateAcknowledged = ref(false);
// Collapses the full alert to the small chip after "This isn't mine" (re-openable).
const duplicateDismissed = ref(false);

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
    duplicateAcknowledged.value = false;
    duplicateDismissed.value = false;
    setComponentReady(true); // editing the name clears the gate
};

const handleTagLineInput = () => {
    $v.value.event.tag_line.$touch();
    if (event.tag_line?.length > 250) {
        event.tag_line = event.tag_line.slice(0, 250);
    }
};

// "This isn't mine" — collapse the alert to the small chip and mark it acknowledged so the
// wizard's own Next button proceeds (no auto-advance). The user stays put until they choose
// to move on; re-openable via the chip.
const dismissDuplicate = () => {
    duplicateAcknowledged.value = true;
    duplicateDismissed.value = true;
    setComponentReady(true); // re-enable Next
};

// The first duplicate whose organizer can be claimed — drives the "I believe this is my
// event and org" link and is the org the claim modal targets.
const claimableDuplicate = computed(() => duplicateEvents.value.find((d) => d.organizer?.claimable) || null);

// Ownership-claim state for the duplicate's organizer, keyed by organizer id:
// { status: 'idle'|'submitting'|'error', message }
const claimStates = reactive({});
const claimState = (org) => claimStates[org.id] || { status: 'idle', message: '' };

// The "Is this your organization?" link opens a focused modal that holds the explanation
// and the actual Claim action, keeping the inline duplicate warning light.
const claimModalDup = ref(null);
const openClaimModal = (dup) => { claimModalDup.value = dup; };
const closeClaimModal = () => { claimModalDup.value = null; };

// When a duplicate title belongs to a claimable (staff-entered, externally-unowned)
// organizer, the creator can claim that org instead of filing a duplicate event —
// reusing the same endpoint as the organizer-creation claim flow (initial.vue).
const requestOwnership = async (org) => {
    claimStates[org.id] = { status: 'submitting', message: '' };
    try {
        await axios.post(`/api/organizers/${org.slug}/claim`);

        // They've decided this is a duplicate, so discard the in-progress draft they
        // were creating (best-effort) before sending them to the org page, which shows
        // a "pending review" banner. The claim is already filed; a stray draft is harmless.
        try {
            await axios.delete(`/hosting/event/${event.slug}`);
        } catch (e) {
            // Non-fatal — leave the draft if deletion fails.
        }

        window.location.href = `/organizers/${org.slug}`;
    } catch (error) {
        claimStates[org.id] = {
            status: 'error',
            message: error.response?.data?.message || 'Could not submit your request. Please try again.',
        };
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
    duplicateAcknowledged,
    handleDuplicateError: (error) => {
        if (error.response?.status === 409) {
            duplicateWarning.value = error.response.data.warning || 'A similar event name already exists.';
            duplicateEvents.value = error.response.data.duplicateEvents || [];
            // Don't auto-acknowledge. Next stays gated (the backend keeps 409-ing) until the
            // user explicitly clicks "This isn't mine" (dismissDuplicate) or claims, so they
            // can't sail past the warning by mistake.
            duplicateDismissed.value = false; // a fresh match always shows the full alert
            setComponentReady(false); // grey out Next until they pick "This isn't mine" or claim
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
