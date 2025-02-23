<template>
    <div class="w-full inline-block relative z-20">
        <div class="flex items-center justify-end pr-8 relative">
        </div>
        <template v-if="search">
            <div class="search-container fixed left-0 top-0 h-screen w-full z-20 bg-[#f4f3f3] flex flex-col">
                <div class="w-full flex justify-center items-center p-4">
                    <button 
                        @click.stop="hideSearch"
                        class="absolute top-4 z-20 left-8 items-center justify-center rounded-full p-0 w-14 h-14 flex border bg-white border-slate-400 hover:bg-black hover:fill-white">
                        <svg class="w-8 h-8 text-red-500">
                            <use :xlink:href="`/storage/website-files/icons.svg#ri-close-line`" />
                        </svg>
                    </button>
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
                <div class="flex-grow overflow-y-auto px-4">
                    <div class="h-full">
                        <Transition name="fade" mode="out-in">
                            <div class="h-full" :key="search">
                                <SearchLocation 
                                    v-if="search==='l'" 
                                    ref="searchLocation"
                                    class="h-full"
                                    :initial-city="city"
                                    :initial-start-date="startDate"
                                    :initial-end-date="endDate"
                                    @update:location="handleLocationUpdate"
                                />
                                <SearchEvent v-if="search==='e'" class="h-full"/>
                            </div>
                        </Transition>
                    </div>
                </div>
                <div v-if="search === 'l'" class="w-full bg-white flex p-12 justify-between items-center mt-auto">
                    <div>
                        <button @click="handleClearAll" class="underline">Clear All</button>
                    </div>
                    <div>
                        <button 
                            @click="handleSearch"
                            :disabled="!city"
                            :class="[
                                'py-4 px-8 rounded-2xl flex gap-4',
                                city ? 'bg-[#ff385c] text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                            ]">
                            <svg class="w-8 h-8" :class="city ? 'fill-white' : 'fill-gray-500'">
                                <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                            </svg>
                            Search
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Default State -->
         
        <template v-else>
            <div
                class="w-full absolute top-0 bottom-0 z-[500] cursor-pointer" 
                @click="openSearch" />
            <div class="w-full flex justify-between items-center gap-10 px-0">
                <button 
                    @click="window.history.back()"
                    class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors flex-shrink-0"
                >
                    <svg 
                        class="w-6 h-6" 
                        viewBox="0 0 24 24" 
                        fill="none" 
                        stroke="currentColor" 
                        stroke-width="2" 
                        stroke-linecap="round" 
                        stroke-linejoin="round"
                    >
                        <path d="M19 12H5"/>
                        <path d="M12 19l-7-7 7-7"/>
                    </svg>
                </button>
                <div class="w-full p-4 border rounded-full flex items-center shadow-custom-3">
                    <p class="w-full flex items-center justify-center text-center truncate">
                        <template v-if="city">
                            <span class="text-black text-1xl font-bold truncate max-w-[40%]">{{ city }}</span>
                            <span class="text-gray-300 mx-4">|</span>
                            <span class="text-black text-1xl truncate max-w-[40%]" :class="{ 'font-bold': startDate }">
                                {{ startDate ? formatDateDisplay : 'Add dates' }}
                            </span>
                        </template>
                        <template v-else>
                            <svg class="w-6 h-6 fill-[#ff385c] mr-2">
                                <use :xlink:href="`/storage/website-files/icons.svg#ri-search-line`" />
                            </svg>
                            <span class="text-black font-bold text-2xl">Search</span>
                        </template>
                    </p>
                </div>
                <button 
                    @click="openFilters"
                    class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors flex-shrink-0"
                >
                    <svg 
                        class="w-6 h-6" 
                        viewBox="0 0 24 24" 
                        fill="none" 
                        stroke="currentColor" 
                        stroke-width="2" 
                        stroke-linecap="round" 
                        stroke-linejoin="round"
                    >
                        <line x1="4" y1="6" x2="20" y2="6"/>
                        <line x1="4" y1="12" x2="20" y2="12"/>
                        <line x1="4" y1="18" x2="20" y2="18"/>
                    </svg>
                </button>
            </div>
        </template>
    </div>
</template>

<script>
import SearchLocation from './Components/location-search-mobile.vue'
import SearchEvent from './Components/events-search-mobile.vue'

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
            startDate: urlParams.get("start") ? urlParams.get("start").split(' ')[0] : null,
            endDate: urlParams.get("end") ? urlParams.get("end").split(' ')[0] : null,
        };
    },

    computed: {
        formatDateDisplay() {
            if (!this.startDate) return '';

            const start = new Date(this.startDate);
            const end = new Date(this.endDate);
            
            // Format dates
            const formatDate = (date, isEndDate = false) => {
                const month = date.toLocaleDateString('en-US', { month: 'short' });
                const day = date.getDate();
                
                // If it's the end date and same month as start date, only return day
                if (isEndDate && start.getMonth() === end.getMonth()) {
                    return day;
                }
                
                return `${month} ${day}`;
            };

            // If dates are the same, show only one date
            if (start.getTime() === end.getTime()) {
                return formatDate(start);
            } else {
                return `${formatDate(start)}-${formatDate(end, true)}`;
            }
        },
        
        searchPlaceholder() {
            if (!this.city) return 'Where to?';

            let text = this.city;

            if (this.startDate && this.endDate) {
                const start = new Date(this.startDate);
                const end = new Date(this.endDate);
                
                // Format dates
                const formatDate = (date, isEndDate = false) => {
                    const month = date.toLocaleDateString('en-US', { month: 'short' });
                    const day = date.getDate();
                    
                    // If it's the end date and same month as start date, only return day
                    if (isEndDate && start.getMonth() === end.getMonth()) {
                        return day;
                    }
                    
                    return `${month} ${day}`;
                };

                // If dates are the same, show only one date
                if (start.getTime() === end.getTime()) {
                    text += ` · ${formatDate(start)}`;
                } else {
                    text += ` · ${formatDate(start)}-${formatDate(end, true)}`;
                }
            }

            return text;
        }
    },

    methods: {
        openSearch() {
            this.search = 'l'
        },
        hideSearch() {
            console.log('Hiding search, resetting to URL state');
            const urlParams = new URLSearchParams(window.location.search);
            
            this.search = null;
            this.city = urlParams.get("city");
            this.startDate = urlParams.get("start") ? urlParams.get("start").split(' ')[0] : null;
            this.endDate = urlParams.get("end") ? urlParams.get("end").split(' ')[0] : null;
            
            console.log('State after hide:', {
                city: this.city,
                startDate: this.startDate,
                endDate: this.endDate
            });
        },
        handleLocationUpdate(value) {
            console.log('Parent handleLocationUpdate received:', value);
            if (value) {
                this.city = value.city;
                // Update URL with new coordinates and city
                const currentParams = new URLSearchParams(window.location.search);
                currentParams.set('city', value.city);
                currentParams.set('lat', value.lat);
                currentParams.set('lng', value.lng);
                
                // Handle dates
                this.startDate = value.start || null;
                this.endDate = value.end || null;
                
                if (this.startDate && this.endDate) {
                    currentParams.set('start', this.startDate + ' 00:00:00');
                    currentParams.set('end', this.endDate + ' 00:00:00');
                } else {
                    currentParams.delete('start');
                    currentParams.delete('end');
                }
                
                // Update URL without page reload
                window.history.replaceState({}, '', `${window.location.pathname}?${currentParams.toString()}`);
            } else {
                this.city = null;
                this.startDate = null;
                this.endDate = null;
                
                // Remove coordinates from URL
                const currentParams = new URLSearchParams(window.location.search);
                currentParams.delete('city');
                currentParams.delete('lat');
                currentParams.delete('lng');
                currentParams.delete('start');
                currentParams.delete('end');
                window.history.replaceState({}, '', `${window.location.pathname}?${currentParams.toString()}`);
            }
            console.log('Parent state after update:', {
                city: this.city,
                startDate: this.startDate,
                endDate: this.endDate,
                url: window.location.href
            });
        },
        handleSearch() {
            // This will trigger the search in the child component
            window.dispatchEvent(new CustomEvent('trigger-search'));
        },
        handleClearAll() {
            console.log('Parent handleClearAll called');
            
            // First clear all state
            this.city = null;
            this.startDate = null;
            this.endDate = null;
            
            console.log('Parent state cleared:', {
                city: this.city,
                startDate: this.startDate,
                endDate: this.endDate
            });
            
            // Then tell child to clear its state
            if (this.$refs.searchLocation) {
                console.log('Calling child clearState');
                this.$refs.searchLocation.clearState(true); // Pass true to indicate this is a clear operation
            }
        },
        openFilters() {
            // Emit an event to handle filter options
            this.$emit('open-filters');
        }
    },

    mounted() {
        window.addEventListener('hide-search', this.hideSearch);
    },

    beforeUnmount() {
        window.removeEventListener('hide-search', this.hideSearch);
    }
}
</script>

<style>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
