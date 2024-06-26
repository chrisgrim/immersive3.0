<template>
	<div class="flex justify-end">
		<div class="px-32 w-full ml-[-2rem]">
			<div class="w-full h-44 flex items-center justify-between">
				<a href="/organizer"><h2 class="font-medium px-8">{{organizer.name}}</h2></a>
				<div>
					<div class="rounded-full bg-gray-100 w-20 h-20 flex items-center justify-center text-5xl font-light">
					    +
					</div>
				</div>
			</div>
			<div class="w-full">
			    <div class="grid grid-cols-4 gap-8 py-4 h-36 items-center" style="grid-template-columns: 16rem 30% auto auto;">
			       	<h5 class="px-8 font-medium">Events</h5>
			       	<div></div>
			       	<h5 class="font-medium">Status</h5>
			    </div>
			</div>
			<div class="w-full" v-if="organizer && organizer.events">
			    <div class="group relative grid grid-cols-4 gap-8 py-4 h-36 items-center hover:bg-gray-100 rounded-2xl cursor-pointer"
			         @click="onSubmit"
			         v-for="event in organizer.events"
			         :key="event.id"
			         style="grid-template-columns: 16rem 30% auto auto;">
			        <div class="px-8">
			            <template v-if="event && event.thumbImagePath">
			                <picture>
			                    <source :srcset="`${imageUrl}${event.thumbImagePath}`" type="image/webp">
			                    <img :src="`${imageUrl}${event.thumbImagePath.slice(0, -4)}jpg`"
			                         :alt="`${event.name} Immersive Event`"
			                         class="h-24 w-full object-cover rounded-2xl">
			                </picture>
			            </template>
			            <template v-else>
			                <div class="h-24 w-full rounded-2xl bg-gray-300"></div>
			            </template>
			        </div>
			        <div class="">
			            <p class="text-2xl font-medium">{{ event.name }}</p>
			            <p class="text-md leading-4 text-gray-500">last edited: {{ cleanDate(event.updated_at) }}</p>
			        </div>
			        <div class="flex flex-row items-center">
			        	<div :class="getStatusInfo(event, cleanDate).color" class="w-4 h-4 rounded-full"></div>
            			<p class="text-lg font-medium text-gray-500 ml-4">{{ getStatusInfo(event, cleanDate).progress }}</p>
			        </div>
			        <!-- SVG Icon, visible only on hover -->
			        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
					     class="absolute right-6 top-1/2 transform -translate-y-1/2 w-8 h-8 opacity-0 group-hover:opacity-100"
					     stroke-width="3">
					    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
					</svg>
			    </div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref } from 'vue';
import dayjs from 'dayjs';

const props = defineProps({
    organizer: Object,
    user: Object,
});

const imageUrl = import.meta.env.VITE_IMAGE_URL;


const onSubmit = async () => {

};

const eventPassed = (event) => {
    return event.status === 'p' && !event.isShowing;
};

const getStatusInfo = (event, cleanDateFn) => {
    if (eventPassed(event)) {
        return { color: 'bg-slate-200', progress: 'event has no more dates', url: `/create/${event.slug}/shows` };
    }
    const statusInfo = {
        '0': { color: 'bg-orange-400', progress: 'add event title', url: `/create/${event.slug}/title` },
        '1': { color: 'bg-orange-400', progress: 'add physical/remote location', url: `/create/${event.slug}/location` },
        '2': { color: 'bg-orange-400', progress: 'add category', url: `/create/${event.slug}/category` },
        '3': { color: 'bg-orange-400', progress: 'add show dates', url: `/create/${event.slug}/shows` },
        '4': { color: 'bg-orange-400', progress: 'add pricing', url: `/create/${event.slug}/tickets` },
        '5': { color: 'bg-orange-400', progress: 'add show description', url: `/create/${event.slug}/description` },
        '6': { color: 'bg-orange-400', progress: 'add advisories', url: `/create/${event.slug}/advisories` },
        '7': { color: 'bg-orange-400', progress: 'add image', url: `/create/${event.slug}/images` },
        '8': { color: 'bg-yellow-300', progress: 'submit event', url: `/create/${event.slug}/review` },
        'p': { color: 'bg-green-500', progress: 'approved', url: `/events/${event.slug}` },
        'e': { color: 'bg-green-500', progress: `event live at ${cleanDateFn(event.embargo_date)}`, url: `/create/${event.slug}/title` },
        'r': { color: 'bg-black', progress: 'under moderator review', url: null },
        'n': { color: 'bg-red-500', progress: 'revise and resubmit', url: `/create/${event.slug}/title` }
    };
    return statusInfo[event.status] || { color: 'bg-white', progress: '-', url: `/create/${event.slug}/title` };
};

const cleanDate = (data) => dayjs(data).format("MMM DD, YYYY");


</script>