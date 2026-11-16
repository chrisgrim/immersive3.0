<template>
    <!-- What a venue offers, group by group, as the event's advisories: a box to
         pick from (the main ones with their icon first, then more) or to type
         your own, and what is picked as chips under it. Age takes one at most
         (picking another swaps it) and no words of your own. -->
    <div class="space-y-8">
        <!-- The open list over the boxes below it, not under their arrows. -->
        <section v-for="group in AMENITY_GROUPS" :key="group.key" class="relative focus-within:z-20" :data-amenity-group="group.key">
            <h3 class="text-[1.8rem] leading-[2.4rem] font-medium text-[#222222] mb-4">{{ group.label }}</h3>
            <Dropdown
                :list="choices(group)"
                :creatable="!group.single && owned(group).length < MAX_CUSTOM"
                :placeholder="group.single ? 'Pick one' : `Add to ${group.label}`"
                :max-input-length="MAX_LABEL"
                @onSelect="(item) => add(group, item)"
            />
            <!-- Room for one row of picks already, so a few picked move nothing. -->
            <div class="mt-6 min-h-[6rem]">
                <List
                    :selections="chips(group)"
                    :item-height="'h-24'"
                    @onSelect="(chip) => remove(chip)"
                />
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, markRaw } from 'vue';
import Dropdown from '@/GlobalComponents/dropdown.vue';
import List from '@/GlobalComponents/dropdown-list.vue';
import { AMENITY_GROUPS, AMENITY_ICONS, amenityGroup, amenityLabel } from './amenity-icons.js';

// The same limits as App\Support\Projects\VenueAmenities.
const MAX_CUSTOM = 10;
const MAX_LABEL = 40;

const props = defineProps({
    // Known keys, and the venue's own words as {group, label}.
    modelValue: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);

const items = computed(() => props.modelValue ?? []);
const picked = computed(() => new Set(items.value.filter((item) => typeof item === 'string')));
const icon = (key) => (AMENITY_ICONS[key] ? markRaw(AMENITY_ICONS[key]) : null);
const known = (group) => [...group.amenities, ...group.more];
const owned = (group) => items.value.filter((item) => typeof item !== 'string' && item.group === group.key);

const choices = (group) => known(group)
    .filter((amenity) => !picked.value.has(amenity.key))
    .map((amenity) => ({ id: amenity.key, name: amenity.label, icon: icon(amenity.key) }));

const chips = (group) => items.value
    .filter((item) => amenityGroup(item) === group.key)
    .map((item) => ({ id: typeof item === 'string' ? item : `own:${item.label}`, name: amenityLabel(item), icon: typeof item === 'string' ? icon(item) : null, item }));

const add = (group, choice) => {
    const label = String(choice.name ?? '').trim().replace(/\s+/g, ' ').slice(0, MAX_LABEL);
    if (!label) return;
    const lower = label.toLowerCase();
    // Typed words that are one we know become it.
    const match = known(group).find((amenity) => amenity.key === choice.id || amenity.label.toLowerCase() === lower);
    if (match) {
        if (picked.value.has(match.key)) return;
        const keep = group.single ? items.value.filter((item) => amenityGroup(item) !== group.key) : items.value;
        emit('update:modelValue', [...keep, match.key]);
        return;
    }
    if (group.single || owned(group).length >= MAX_CUSTOM || owned(group).some((item) => item.label.toLowerCase() === lower)) return;
    emit('update:modelValue', [...items.value, { group: group.key, label }]);
};

const remove = (chip) => emit('update:modelValue', items.value.filter((item) => item !== chip.item));
</script>
