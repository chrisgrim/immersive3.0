<template>
    <div class="w-full inline-block relative min-w-[30rem] col-span-1 z-20">
        <div>
        </div>
        <template v-if="search">
            <div 
                @click="checkClickPosition"
                class="search-container fixed pt-8 left-0 top-0 w-full z-20">
                <div class="w-full flex justify-center items-center gap-2">
                    <button 
                        @click="search='l'"
                        :class="[
                            'text-gray-500 relative border-none p-4 text-2xl rounded-full transition-all duration-200',
                            search === 'l' ? 'font-bold' : 'font-normal',
                            'hover:bg-gray-100'
                        ]">
                        <span class="block font-bold invisible h-0">Location</span>
                        <span class="block" :class="{ 'font-bold text-black': search === 'l' }">Location</span>
                    </button>
                    <button 
                        @click="search='e'"
                        :class="[
                            'text-gray-500 relative border-none p-4 text-2xl rounded-full transition-all duration-200',
                            search === 'e' ? 'font-bold' : 'font-normal',
                            'hover:bg-gray-100'
                        ]">
                        <span class="block font-bold invisible h-0">Name</span>
                        <span class="block" :class="{ 'font-bold text-black': search === 'e' }">Name</span>
                    </button>
                </div>
                <div class="w-full mx-auto flex relative max-w-6xl mt-8">
                    <SearchLocation v-if="search==='l'"/>
                    <SearchEvent v-if="search==='e'"/>
                </div>
                <div class="fixed top-0 left-0 w-full h-full bg-[#00000026] z-[-10]" />
            </div>
        </template>
        <template v-else>
            <div
                class="w-full absolute top-0 bottom-0 z-[500] cursor-pointer" 
                @click="openSearch" />
            <div class="p-4 border rounded-full flex justify-between items-center shadow-custom-3 w-[39rem] m-auto">
                <p class="ml-4 flex items-center justify-center gap-2 flex-1 text-center">
                    <template v-if="city">
                        <span class="text-black text-1xl font-bold mr-10">{{ city }}</span>
                        <span class="text-gray-300">|</span>
                        <span class="ml-10 text-black text-1xl" :class="{ 'font-bold': startDate }">
                            {{ startDate ? formatDateDisplay : 'Add dates' }}
                        </span>
                    </template>
                    <template v-else>
                        <span class="text-black text-1xl">Start your search</span>
                    </template>
                </p>
                <div class="w-12 h-12 flex-shrink-0 flex items-center justify-center rounded-full bg-default-red">
                    <svg class="w-8 h-8 fill-white">
                        <use :xlink:href="`/storage/website-files/icons.svg#ri-search-line`" />
                    </svg>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import SearchLocation from './Components/location-search.vue'
import SearchEvent from './Components/events-search.vue'

export default {
    components: { 
        SearchLocation, 
        SearchEvent 
    },

    data() {
        const urlParams = new URLSearchParams(window.location.search);
        return {
            search: null,
            city: urlParams.get("city"),
            startDate: urlParams.get("start"),
            endDate: urlParams.get("end"),
        };
    },

    computed: {
        searchPlaceholder() {
            if (!this.city) return 'Start your search';

            let text = this.city;

            if (this.startDate && this.endDate) {
                const start = new Date(this.startDate);
                const end = new Date(this.endDate);
                
                // Format dates
                const formatDate = (date) => {
                    return date.toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric' 
                    });
                };

                // If dates are the same, show only one date
                if (start.getTime() === end.getTime()) {
                    text += ` · ${formatDate(start)}`;
                } else {
                    text += ` · ${formatDate(start)} - ${formatDate(end)}`;
                }
            }

            return text;
        },
        formatDateDisplay() {
            if (!this.startDate) return '';

            const start = new Date(this.startDate);
            const end = new Date(this.endDate);
            
            // Format dates
            const formatDate = (date) => {
                return date.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric' 
                });
            };

            // If dates are the same, show only one date
            if (start.getTime() === end.getTime()) {
                return formatDate(start);
            } else {
                return `${formatDate(start)} - ${formatDate(end)}`;
            }
        }
    },

    methods: {
        openSearch() {
            this.search = 'l'
        },
        handleScroll () {
            this.search = null;
        },
        checkClickPosition(event) {
            if (event.clientY > 150) {
                this.search = null;
            }
        },
        hideSearch() {
            this.search = null;
        },
        handleFilterUpdate(event) {
            if (event.detail.type === 'location') {
                const { value } = event.detail;
                this.city = value.city;
                if (value.start) {
                    this.startDate = value.start;
                    this.endDate = value.end || value.start;
                }
            }
        }
    },

    mounted() {
        window.addEventListener('scroll', this.handleScroll);
        window.addEventListener('filter-update', this.handleFilterUpdate);
        window.addEventListener('hide-search', this.hideSearch);
    },

    beforeUnmount() {
        window.removeEventListener('scroll', this.handleScroll);
        window.removeEventListener('filter-update', this.handleFilterUpdate);
        window.removeEventListener('hide-search', this.hideSearch);
    }
}
</script>

<style scoped>
.search-container::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 18rem;
    background: white;
    z-index: -1;
    box-shadow: 0 1px 12px rgba(0, 0, 0, 0.08);
}
</style>