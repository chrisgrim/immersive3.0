<template>
	<div class="relative text-1xl font-medium w-full h-[calc(100vh-8rem)] flex flex-col">
		<!-- Main Content Area with proper scrolling -->
		<div class="flex-1 overflow-y-auto">
			<div class="max-w-screen-xl mx-auto min-h-full flex">
				<div class="w-full lg:w-1/2 mx-auto pt-20 md:pt-20 md:pb-20">
					<div class="h-full flex md:items-center p-8">
						<div class="w-full">
							<header>
								<h2 class="text-5xl font-semibold text-gray-600">Account</h2>
							</header>

							<div class="border-b mt-16">
								<h3 class="text-2xl uppercase">Notifications</h3>
							</div>

							<!-- Notification Settings -->
							<div class="space-y-8 mt-10">
								<!-- Monthly Newsletter -->
								<div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
									<div class="flex-1">
										<h3 class="text-2xl md:text-3xl">Subscribe to monthly newsletter</h3>
										<p class="text-1xl text-gray-600">Get our monthly newsletters about the latest and greatest immersive events.</p>
									</div>
									<div class="flex justify-end">
										<label class="inline-flex items-center cursor-pointer">
											<input type="checkbox" value="" class="sr-only peer" v-model="newsletter">
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
											<input type="checkbox" value="" class="sr-only peer" v-model="eventNewsletter">
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
											<input type="checkbox" value="" class="sr-only peer" v-model="emailMessages">
											<div class="relative w-[5.5rem] h-[3.1rem] bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[3px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-10 after:w-10 after:transition-all peer-checked:bg-blue-600"></div>
										</label>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps(['loaduser']);

const user = reactive({ ...props.loaduser });
const newsletter = ref(user.newsletter_type.includes('m') || user.newsletter_type.includes('a'));
const eventNewsletter = ref(user.newsletter_type.includes('u') || user.newsletter_type.includes('a'));
const emailMessages = ref(user.silence === 'n');

const newsletterType = computed(() => {
    if (newsletter.value && eventNewsletter.value) return 'a';
    if (newsletter.value) return 'm';
    if (eventNewsletter.value) return 'u';
    return 'n';
});

const silence = computed(() => emailMessages.value ? 'n' : 'y');

watch([newsletterType, silence], async () => {
    try {
        const response = await axios.post(`/users/${user.id}`, {
            newsletter_type: newsletterType.value,
            silence: silence.value
        });
        Object.assign(user, response.data);
    } catch (err) {
	    alert("Failed to update settings. Please try again."); 
	}
}, { immediate: true });

onMounted(() => {
    // Set initial checkbox states based on user properties
    newsletter.value = user.newsletter_type.includes('m') || user.newsletter_type.includes('a');
    eventNewsletter.value = user.newsletter_type.includes('u') || user.newsletter_type.includes('a');
    emailMessages.value = user.silence === 'n';
});
</script>
