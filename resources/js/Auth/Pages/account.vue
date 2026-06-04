<template>
    <div class="space-y-14">
        <div>
            <div class="border-b mb-8">
                <h3 class="text-2xl uppercase">Notifications</h3>
            </div>

            <!-- Notification Settings -->
            <div class="space-y-8">
                <!-- Monthly Newsletter -->
                <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                    <div class="flex-1">
                        <h3 class="text-2xl md:text-3xl">Subscribe to monthly newsletter</h3>
                        <p class="text-1xl text-gray-600">Get our monthly newsletters about the latest and greatest immersive events.</p>
                    </div>
                    <div class="flex justify-end">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer" v-model="newsletter">
                            <div class="relative w-[5.5rem] h-[3.1rem] bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[3px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-10 after:w-10 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Event Updates -->
                <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                    <div class="flex-1">
                        <h3 class="text-2xl md:text-3xl">Subscribe to event update newsletters</h3>
                        <p class="text-1xl text-gray-600">Get the latest updates about the organizations and events you have liked on EI.</p>
                    </div>
                    <div class="flex justify-end">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer" v-model="eventNewsletter">
                            <div class="relative w-[5.5rem] h-[3.1rem] bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[3px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-10 after:w-10 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Email Messages -->
                <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                    <div class="flex-1">
                        <h3 class="text-2xl md:text-3xl">Email Messages</h3>
                        <p class="text-1xl text-gray-600">Get an email whenever a user or admin sends you a message.</p>
                    </div>
                    <div class="flex justify-end">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer" v-model="emailMessages">
                            <div class="relative w-[5.5rem] h-[3.1rem] bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[3px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-10 after:w-10 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Admin-only notifications -->
            <div v-if="isAdmin" class="mt-12 pt-8 border-t">
                <h4 class="text-xl uppercase text-gray-500 mb-2">Admin</h4>
                <p class="text-1xl text-gray-600 mb-8">Emails for the admin review queue. Turn off any you don't want.</p>
                <div class="space-y-8">
                    <!-- Organizer notifications -->
                    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                        <div class="flex-1">
                            <h3 class="text-2xl md:text-3xl">Organizer notifications</h3>
                            <p class="text-1xl text-gray-600">Ownership claims, name-change requests, and new organizers submitted for review.</p>
                        </div>
                        <div class="flex justify-end">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" v-model="notifyOrganizers">
                                <div class="relative w-[5.5rem] h-[3.1rem] bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[3px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-10 after:w-10 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                    </div>

                    <!-- Event notifications -->
                    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                        <div class="flex-1">
                            <h3 class="text-2xl md:text-3xl">Event notifications</h3>
                            <p class="text-1xl text-gray-600">New events submitted for review.</p>
                        </div>
                        <div class="flex justify-end">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" v-model="notifyEvents">
                                <div class="relative w-[5.5rem] h-[3.1rem] bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[3px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-10 after:w-10 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, inject, computed } from 'vue';

const owner = inject('owner', {
    newsletter_type: 'n',
    silence: 'y'
});


const newsletter = ref(
    owner?.newsletter_type?.includes('m') || 
    owner?.newsletter_type?.includes('a') || 
    false
);

const eventNewsletter = ref(
    owner?.newsletter_type?.includes('u') || 
    owner?.newsletter_type?.includes('a') || 
    false
);

const emailMessages = ref(owner?.silence === 'n' || false);

// Admin-only notification opt-outs (default ON to match the opt-out model).
const isAdmin = computed(() => owner?.type === 'a' || owner?.isAdmin === true);
const notifyOrganizers = ref(owner?.notification_preferences?.organizers ?? true);
const notifyEvents = ref(owner?.notification_preferences?.events ?? true);

const newsletterType = computed(() => {
    const type = newsletter.value && eventNewsletter.value ? 'a' 
               : newsletter.value ? 'm'
               : eventNewsletter.value ? 'u'
               : 'n';
    return type;
});

const isValid = async () => true;

const submitData = async () => {
    const data = {
        newsletter_type: newsletterType.value,
        silence: emailMessages.value ? 'n' : 'y'
    };
    // Only admins have these toggles; send the full object so a partial save can't drop a key.
    if (isAdmin.value) {
        data.notification_preferences = {
            organizers: notifyOrganizers.value,
            events: notifyEvents.value,
        };
    }
    return data;
};

// Add this method to update the component state
const updateFromOwner = (newOwner) => {
    newsletter.value = newOwner.newsletter_type.includes('m') ||
                      newOwner.newsletter_type.includes('a');
    eventNewsletter.value = newOwner.newsletter_type.includes('u') ||
                           newOwner.newsletter_type.includes('a');
    emailMessages.value = newOwner.silence === 'n';
    notifyOrganizers.value = newOwner.notification_preferences?.organizers ?? true;
    notifyEvents.value = newOwner.notification_preferences?.events ?? true;
};

defineExpose({
    isValid,
    submitData,
    updateFromOwner
});
</script>
