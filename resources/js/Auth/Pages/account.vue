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
        </div>
    </div>
</template>

<script setup>
import { ref, inject, computed, watch } from 'vue';

const owner = inject('owner', {
    newsletter_type: 'n',
    silence: 'y'
});

console.log('Initial owner data:', owner);

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

const newsletterType = computed(() => {
    const type = newsletter.value && eventNewsletter.value ? 'a' 
               : newsletter.value ? 'm'
               : eventNewsletter.value ? 'u'
               : 'n';
    console.log('Computed newsletter type:', type);
    return type;
});

const isValid = async () => true;

const submitData = async () => {
    const data = {
        newsletter_type: newsletterType.value,
        silence: emailMessages.value ? 'n' : 'y'
    };
    console.log('Submitting data:', data);
    return data;
};

// Watch for changes
watch([newsletter, eventNewsletter, emailMessages], (newValues) => {
    console.log('Settings changed:', {
        newsletter: newValues[0],
        eventNewsletter: newValues[1],
        emailMessages: newValues[2],
        computedType: newsletterType.value
    });
});

// Add this method to update the component state
const updateFromOwner = (newOwner) => {
    newsletter.value = newOwner.newsletter_type.includes('m') || 
                      newOwner.newsletter_type.includes('a');
    eventNewsletter.value = newOwner.newsletter_type.includes('u') || 
                           newOwner.newsletter_type.includes('a');
    emailMessages.value = newOwner.silence === 'n';
};

defineExpose({
    isValid,
    submitData,
    updateFromOwner
});
</script>
