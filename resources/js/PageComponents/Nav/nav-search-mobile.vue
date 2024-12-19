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
            <div class="p-4 border rounded-full flex justify-between items-center shadow-custom-3 m-auto">
                <div class="w-12 h-12 flex-shrink-0 flex items-center justify-center rounded-full bg-default-red">
                    <svg class="w-8 h-8 fill-white">
                        <use :xlink:href="`/storage/website-files/icons.svg#ri-search-line`" />
                    </svg>
                </div>
                <p class="ml-4 flex items-center justify-center gap-2 flex-1 text-center">
                    <template v-if="city">
                        <span class="text-black text-1xl font-bold mr-10">{{ city }}</span>
                        <span class="text-gray-300">|</span>
                        <span class="ml-10 text-black text-1xl" :class="{ 'font-bold': startDate }">
                            {{ startDate ? formatDateDisplay : 'Add dates' }}
                        </span>
                    </template>
                    <template v-else>
                        <span class="text-black text-1xl">Where to?</span>
                    </template>
                </p>
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
        searchPlaceholder() {
            if (!this.city) return 'Where to?';

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
