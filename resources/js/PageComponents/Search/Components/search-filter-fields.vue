<template>
    <div v-if="loading" class="flex-1 p-8 flex justify-center items-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-black"></div>
    </div>

    <div v-else class="flex-1 overflow-y-auto">
        <div class="flex flex-col px-8 pb-8">

            <!-- Price Range Section -->
            <div
                v-if="showPrice && maxPrice > 0"
                class="transition-all duration-300 ease-in-out flex-1 bg-white border-b flex flex-col"
            >
                <div class="flex items-center justify-between py-4 cursor-pointer flex-shrink-0">
                    <div>
                        <p class="text-3xl p-4 font-semibold">Price range</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div
                            v-if="(modelValue.price[0] !== 0 || modelValue.price[1] !== maxPrice) && activeSection !== 'price'"
                            class="bg-black rounded-full px-4 py-2"
                        >
                            <span class="text-xl text-white">
                                {{ `$${modelValue.price[0]} - $${modelValue.price[1]}` }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="px-12 pb-12">
                    <vue-slider
                        v-model="priceModel"
                        :min="0"
                        :max="maxPrice"
                        :tooltip="'none'"
                        :enable-cross="false"
                        :process-style="{ backgroundColor: '#000' }"
                        :rail-style="{ backgroundColor: '#e5e5e5' }"
                        :dot-style="{
                            border: '2px solid black',
                            backgroundColor: 'white',
                            width: '24px',
                            height: '24px',
                            marginTop: '-.5rem'
                        }"
                        class="w-full mb-8"
                    />
                    <div class="flex justify-between">
                        <div class="space-y-2">
                            <div class="text-lg font-medium text-neutral-600">Minimum</div>
                            <div class="border border-neutral-300 rounded-full px-10 py-5">
                                <div class="text-2xl">${{ modelValue.price[0] }}</div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="text-lg font-medium text-neutral-600">Maximum</div>
                            <div class="border border-neutral-300 rounded-full px-10 py-5">
                                <div class="text-2xl">
                                    ${{ modelValue.price[1] }}{{ modelValue.price[1] === maxPrice ? '+' : '' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories Section -->
            <div
                class="transition-all duration-300 ease-in-out bg-white border-b flex flex-col"
                :class="[activeSection === 'categories' ? 'flex-1' : '']"
            >
                <div
                    @click="toggleSection('categories')"
                    class="flex items-center justify-between py-8 cursor-pointer flex-shrink-0"
                >
                    <p v-if="!isSearchingCategories" class="text-3xl p-4 font-semibold">Categories</p>
                    <div class="flex items-center gap-4" :class="{'w-full': isSearchingCategories}">
                        <div v-if="activeSection === 'categories'" class="flex items-center gap-4" :class="{'w-full': isSearchingCategories}">
                            <div v-if="!isSearchingCategories">
                                <button
                                    @click.stop="toggleCategorySearch"
                                    class="p-2 rounded-full hover:bg-neutral-100 transition-colors"
                                >
                                    <svg class="w-8 h-8 fill-black">
                                        <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                    </svg>
                                </button>
                            </div>
                            <div v-else class="w-full border border-neutral-400 rounded-full flex items-center">
                                <svg class="w-8 h-8 fill-black z-[1002] ml-8 flex-shrink-0">
                                    <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                </svg>
                                <input
                                    ref="categorySearchInput"
                                    v-model="categorySearchQuery"
                                    class="relative text-3xl p-4 w-full font-bold z-40 bg-transparent focus:border-none focus:outline-none placeholder-slate-400 touch-manipulation"
                                    placeholder="Search categories"
                                    @click.stop
                                    autocomplete="off"
                                    type="text"
                                >
                                <button @click.stop="clearCategorySearch" class="mr-8 flex-shrink-0">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div v-if="modelValue.categories.length && !isSearchingCategories" class="bg-black rounded-full px-4 py-2">
                            <span class="text-xl text-white">{{ modelValue.categories.length }} selected</span>
                        </div>
                        <svg
                            v-if="activeSection !== 'categories' && !isSearchingCategories"
                            class="w-8 h-8 text-gray-600 ml-2"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                        <svg
                            v-else-if="!isSearchingCategories"
                            class="w-8 h-8 text-gray-600 ml-2"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                    </div>
                </div>

                <div v-show="activeSection === 'categories'" class="pb-8">
                    <div class="grid grid-cols-2 gap-4">
                        <button
                            v-for="category in filteredCategories"
                            :key="category.id"
                            class="py-3 px-4 text-left flex items-center gap-3 hover:bg-neutral-50 transition-all"
                            @click="toggleCategory(category.id)"
                        >
                            <div class="flex-shrink-0 w-10 h-10 rounded-lg border transition-colors flex items-center justify-center"
                                 :class="{
                                    'border-black bg-neutral-800': isSelectedCategory(category.id),
                                    'border-neutral-300': !isSelectedCategory(category.id)
                                 }"
                            >
                                <svg v-if="isSelectedCategory(category.id)" class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <span class="text-1xl truncate">{{ category.name }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tags Section -->
            <div
                class="transition-all duration-300 ease-in-out bg-white border-b flex flex-col"
                :class="[activeSection === 'tags' ? 'flex-1' : '']"
            >
                <div
                    @click="toggleSection('tags')"
                    class="flex items-center justify-between py-8 cursor-pointer flex-shrink-0"
                >
                    <p v-if="!isSearchingTags" class="text-3xl p-4 font-semibold">{{ tagsLabel }}</p>
                    <div class="flex items-center gap-4" :class="{'w-full': isSearchingTags}">
                        <div v-if="activeSection === 'tags'" class="flex items-center gap-4" :class="{'w-full': isSearchingTags}">
                            <div v-if="!isSearchingTags">
                                <button
                                    @click.stop="toggleTagSearch"
                                    class="p-2 rounded-full hover:bg-neutral-100 transition-colors"
                                >
                                    <svg class="w-8 h-8 fill-black">
                                        <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                    </svg>
                                </button>
                            </div>
                            <div v-else class="w-full border border-neutral-400 rounded-full flex items-center">
                                <svg class="w-8 h-8 fill-black z-[1002] ml-8 flex-shrink-0">
                                    <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                                </svg>
                                <input
                                    ref="tagSearchInput"
                                    v-model="tagSearchQuery"
                                    class="relative text-3xl p-4 w-full font-bold z-40 bg-transparent focus:border-none focus:outline-none placeholder-slate-400 touch-manipulation"
                                    :placeholder="`Search ${tagsLabel.toLowerCase()}`"
                                    @click.stop
                                    autocomplete="off"
                                    type="text"
                                >
                                <button @click.stop="clearTagSearch" class="mr-8 flex-shrink-0">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div v-if="modelValue.tags.length && !isSearchingTags" class="bg-black rounded-full px-4 py-2">
                            <span class="text-xl text-white">{{ modelValue.tags.length }} selected</span>
                        </div>
                        <svg
                            v-if="activeSection !== 'tags' && !isSearchingTags"
                            class="w-8 h-8 text-gray-600 ml-2"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                        <svg
                            v-else-if="!isSearchingTags"
                            class="w-8 h-8 text-gray-600 ml-2"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                    </div>
                </div>

                <div v-show="activeSection === 'tags'" class="px-8 pb-8">
                    <div class="grid grid-cols-2 gap-4">
                        <button
                            v-for="tag in filteredTags"
                            :key="tag.id"
                            class="py-3 px-4 text-left flex items-center gap-3 hover:bg-neutral-50 transition-all"
                            @click="toggleTag(tag.id)"
                        >
                            <div class="flex-shrink-0 w-10 h-10 rounded-lg border transition-colors flex items-center justify-center"
                                 :class="{
                                    'border-black bg-black': isSelectedTag(tag.id),
                                    'border-neutral-300': !isSelectedTag(tag.id)
                                 }"
                            >
                                <svg v-if="isSelectedTag(tag.id)" class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <span class="text-1xl truncate">{{ tag.name }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
// Same module-level (not <script setup>) caching trick as the old filters.vue
// body this was extracted from — see the comment that used to live there:
// a `let` inside <script setup> compiles to per-instance state, so this has
// to be a genuine plain <script> block to actually share the cache across
// every mount (nav dropdown re-opens, the Hub editor, etc).
let cachedFiltersData = null
let filtersFetchPromise = null
</script>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/antd.css'
import axios from 'axios'

const props = defineProps({
    // { categories: number[], tags: number[], price: [number, number|null] }
    modelValue: {
        type: Object,
        required: true,
    },
    maxPrice: {
        type: Number,
        default: 0,
    },
    // Drives category applicability filtering — true for an At Home search,
    // false for in-person — same rule the nav dropdown already applied.
    atHome: {
        type: Boolean,
        default: false,
    },
    showPrice: {
        type: Boolean,
        default: false,
    },
})

// "Genres" everywhere a user sees this — "tags" is only the backend/
// criteria.tags field name (see BuildSearchUrlAction/SearchStore), never a
// user-facing label.
const tagsLabel = 'Genres';

const emit = defineEmits(['update:modelValue'])

const updateModelValue = (patch) => {
    emit('update:modelValue', { ...props.modelValue, ...patch })
}

const loading = ref(true)
const categories = ref([])
const tags = ref([])

const isSearchingCategories = ref(false)
const isSearchingTags = ref(false)
const categorySearchQuery = ref('')
const tagSearchQuery = ref('')
const categorySearchInput = ref(null)
const tagSearchInput = ref(null)

const sortedCategories = ref([])
const sortedTags = ref([])

const activeSection = ref('')

const isSelectedCategory = (id) => props.modelValue.categories.includes(id)
const isSelectedTag = (id) => props.modelValue.tags.includes(id)

const filteredCategories = computed(() => {
    let filtered = sortedCategories.value

    filtered = props.atHome
        ? filtered.filter(cat => !cat.applicable_attendance_types || cat.applicable_attendance_types.includes(2))
        : filtered.filter(cat => !cat.applicable_attendance_types || cat.applicable_attendance_types.includes(1))

    if (categorySearchQuery.value) {
        const query = categorySearchQuery.value.toLowerCase()
        filtered = filtered.filter(cat => cat.name.toLowerCase().includes(query))
    }
    return filtered
})

const filteredTags = computed(() => {
    let filtered = sortedTags.value
    if (tagSearchQuery.value) {
        const query = tagSearchQuery.value.toLowerCase()
        filtered = filtered.filter(tag => tag.name.toLowerCase().includes(query))
    }
    return filtered
})

const priceModel = computed({
    get: () => props.modelValue.price,
    set: (value) => updateModelValue({ price: value }),
})

const fetchFiltersData = async () => {
    if (cachedFiltersData) {
        categories.value = cachedFiltersData.categories
        tags.value = cachedFiltersData.tags
        sortLists()
        loading.value = false
        return
    }

    try {
        if (!filtersFetchPromise) {
            filtersFetchPromise = Promise.all([
                axios.get('/api/categories/active/cached'),
                axios.get('/api/genres/active/cached')
            ])
        }
        const [categoriesResponse, genresResponse] = await filtersFetchPromise

        cachedFiltersData = { categories: categoriesResponse.data, tags: genresResponse.data }
        categories.value = cachedFiltersData.categories
        tags.value = cachedFiltersData.tags

        sortLists()
        loading.value = false
    } catch (error) {
        filtersFetchPromise = null
        console.error('Error fetching filters:', error)
        loading.value = false
    }
}

const toggleCategory = (categoryId) => {
    const index = props.modelValue.categories.indexOf(categoryId)
    const next = index === -1
        ? [...new Set([...props.modelValue.categories, categoryId])]
        : props.modelValue.categories.filter(id => id !== categoryId)
    updateModelValue({ categories: next })
}

const toggleTag = (tagId) => {
    const index = props.modelValue.tags.indexOf(tagId)
    const next = index === -1
        ? [...new Set([...props.modelValue.tags, tagId])]
        : props.modelValue.tags.filter(id => id !== tagId)
    updateModelValue({ tags: next })
}

const toggleSection = (section) => {
    activeSection.value = activeSection.value === section ? null : section
}

const sortLists = () => {
    sortedCategories.value = [...categories.value].sort((a, b) => {
        const aSelected = isSelectedCategory(a.id)
        const bSelected = isSelectedCategory(b.id)
        if (aSelected && !bSelected) return -1
        if (!aSelected && bSelected) return 1
        return 0
    })

    sortedTags.value = [...tags.value].sort((a, b) => {
        const aSelected = isSelectedTag(a.id)
        const bSelected = isSelectedTag(b.id)
        if (aSelected && !bSelected) return -1
        if (!aSelected && bSelected) return 1
        return 0
    })
}

const toggleCategorySearch = () => {
    isSearchingCategories.value = true
    nextTick(() => categorySearchInput.value?.focus())
}

const toggleTagSearch = () => {
    isSearchingTags.value = true
    nextTick(() => tagSearchInput.value?.focus())
}

const clearCategorySearch = () => {
    categorySearchQuery.value = ''
    isSearchingCategories.value = false
}

const clearTagSearch = () => {
    tagSearchQuery.value = ''
    isSearchingTags.value = false
}

// When attendance type changes, drop any selected category that's no longer
// applicable to the new mode — same rule as before this was extracted.
watch(() => props.atHome, (newValue) => {
    if (props.modelValue.categories.length) {
        const applicableCategories = categories.value.filter(cat => {
            if (!cat.applicable_attendance_types) return true
            return newValue
                ? cat.applicable_attendance_types.includes(2)
                : cat.applicable_attendance_types.includes(1)
        })
        const applicableCategoryIds = applicableCategories.map(cat => cat.id)
        const next = props.modelValue.categories.filter(id => applicableCategoryIds.includes(id))
        if (next.length !== props.modelValue.categories.length) {
            updateModelValue({ categories: next })
        }
    }
    sortLists()
})

onMounted(() => {
    fetchFiltersData()
})

defineExpose({ toggleSection })
</script>

<style>
/* Prevent zooming on focus for mobile devices — travels with these input
   elements (unscoped, matches every mount site: the nav dropdown and the
   Hub editor both render this component's own category/tag search inputs). */
input, select, textarea {
    font-size: 16px !important;
}

.touch-manipulation {
    touch-action: manipulation;
}

::placeholder {
    font-size: 16px !important;
}
</style>
