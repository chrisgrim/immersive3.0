<template>
    <!-- The event image step's look: a big dashed box to start, then the main photo
         beside a grid of the others with empty slots. Drag to reorder (the first
         leads the page), the circle cross removes. Saved by the page's Update. -->
    <div>
        <div class="w-full max-w-[64rem] mx-auto mb-16">
            <template v-if="!items.length || max === 1">
                <h2 class="text-black text-center">{{ max === 1 ? `Add an image for your ${noun}` : `Add photos of your ${noun}` }}</h2>
                <p class="text-neutral-500 font-normal mt-4 text-center">{{ max === 1 ? '' : `Up to ${max}. ` }}Ideal resolution: {{ portrait ? '900 by 1200 pixels (vertical)' : '1200 by 800 pixels (horizontal)' }}</p>
            </template>
            <h2 v-else class="text-black">Add more photos <span class="text-neutral-500 font-normal">({{ items.length }} of {{ max }})</span></h2>
        </div>

        <!-- One photo only: it fills the big slot; the cross clears it for another. -->
        <div v-if="max === 1 && items.length" class="mb-8 flex flex-col items-center justify-center pt-12">
            <div class="relative" :class="portrait ? 'w-[30rem] h-[40rem]' : 'w-full max-w-[48rem] aspect-[3/2]'">
                <img :src="items[0].preview" alt="" class="w-full h-full object-cover rounded-2xl" />
                <button
                    type="button"
                    aria-label="Remove this photo"
                    class="absolute top-2 right-2 bg-white rounded-full"
                    @mouseenter="hovered = 0"
                    @mouseleave="hovered = null"
                    @click="remove(0)"
                >
                    <component :is="hovered === 0 ? RiCloseCircleFill : RiCloseCircleLine" />
                </button>
            </div>
        </div>

        <!-- Nothing yet: one big empty slot -->
        <div v-else-if="!items.length" class="mb-8 flex flex-col items-center justify-center pt-12">
            <label :class="[emptySlot, portrait ? 'w-[30rem] h-[40rem]' : 'w-full max-w-[48rem] aspect-[3/2]', 'bg-neutral-50']">
                <input type="file" :multiple="max > 1" :accept="accept" class="hidden" @change="add" />
                <component :is="RiImageCircleLine" class="w-16 h-16 text-neutral-400" />
            </label>
        </div>

        <draggable
            v-else
            v-model="items"
            item-key="key"
            class="grid gap-4"
            :class="portrait ? 'grid-cols-3' : 'grid-cols-2'"
            :animation="200"
            ghost-class="opacity-40"
            @end="changed = true"
        >
            <template #item="{ element, index }">
                <div
                    class="relative cursor-grab active:cursor-grabbing"
                    :class="portrait && index === 0 ? 'row-span-2 aspect-[3/4]' : 'aspect-[3/2]'"
                >
                    <img :src="element.preview" alt="" class="w-full h-full object-cover rounded-2xl pointer-events-none" />
                    <button
                        type="button"
                        aria-label="Remove this photo"
                        class="absolute top-2 right-2 bg-white rounded-full"
                        @mouseenter="hovered = index"
                        @mouseleave="hovered = null"
                        @click="remove(index)"
                    >
                        <component :is="hovered === index ? RiCloseCircleFill : RiCloseCircleLine" />
                    </button>
                </div>
            </template>
            <template #footer>
                <label v-for="slot in emptySlots" :key="`empty-${slot}`" :class="[emptySlot, 'aspect-[3/2]']">
                    <input type="file" multiple :accept="accept" class="hidden" @change="add" />
                    <component :is="RiImageCircleLine" class="w-16 h-16 text-neutral-400" />
                </label>
            </template>
        </draggable>
        <p v-if="items.length && max > 1" class="text-neutral-500 mt-6">Drag to change the order; the first one leads the page.</p>
        <p v-if="error" class="text-red-500 mt-2">{{ error }}</p>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import draggable from 'vuedraggable';
import { RiImageCircleLine, RiCloseCircleLine, RiCloseCircleFill } from '@remixicon/vue';

const props = defineProps({
    photos: { type: Array, default: () => [] },
    max: { type: Number, default: 4 },
    // 'landscape' for venues, 'portrait' for shows
    shape: { type: String, default: 'landscape' },
    // What the heading calls it: 'project', 'venue'
    noun: { type: String, default: 'project' },
});

const imageUrl = import.meta.env.VITE_IMAGE_URL;
const MAX_BYTES = 10 * 1024 * 1024;

const fromSaved = (photos) => photos.map((photo) => ({ key: `saved-${photo.id}`, id: photo.id, path: photo.url, preview: `${imageUrl}${photo.url}` }));

const items = ref(fromSaved(props.photos));
const removed = ref([]);
const changed = ref(false);
const error = ref('');

const portrait = computed(() => props.shape === 'portrait');
const accept = 'image/jpeg,image/png,image/webp,image/avif';
const hovered = ref(null);
const emptySlot = 'relative flex items-center justify-center border border-dashed border-neutral-300 rounded-2xl cursor-pointer hover:border-solid hover:border-[#222222] hover:shadow-focus-black transition-all duration-200';
const emptySlots = computed(() => Math.max(0, props.max - items.value.length));

const add = (picked) => {
    error.value = '';
    const files = Array.from(picked.target.files ?? []);
    picked.target.value = '';
    for (const file of files) {
        if (items.value.length >= props.max) {
            error.value = `Up to ${props.max} photos.`;
            break;
        }
        if (file.size > MAX_BYTES) {
            error.value = `${file.name} is over 10MB.`;
            continue;
        }
        items.value.push({ key: `new-${Date.now()}-${Math.random()}`, file, preview: URL.createObjectURL(file) });
        changed.value = true;
    }
};

const remove = (index) => {
    const [item] = items.value.splice(index, 1);
    if (item.path) {
        removed.value.push(item.path);
    }
    changed.value = true;
};

/** Event-style fields: images[] + ranks[] for new files, currentImages and deletedImages as JSON. */
const formData = () => {
    const body = new FormData();
    const current = [];
    let upload = 0;
    items.value.forEach((item, rank) => {
        if (item.file) {
            body.append('images[]', item.file);
            body.append(`ranks[${upload}]`, rank);
            upload++;
        } else {
            current.push({ id: item.id, rank });
        }
    });
    body.append('currentImages', JSON.stringify(current));
    body.append('deletedImages', JSON.stringify(removed.value));
    return body;
};

/** After a save: show what the server kept. */
const reset = (photos) => {
    items.value = fromSaved(photos);
    removed.value = [];
    changed.value = false;
};

defineExpose({ formData, reset, changed });
</script>
