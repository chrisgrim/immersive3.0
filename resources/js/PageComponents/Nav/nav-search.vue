<template>
    <div class="w-full inline-block relative min-w-[30rem] col-span-3 z-20">
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
                <div class="w-full mx-auto flex relative max-w-5xl mt-8">
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
            <div class="p-4 border rounded-full flex justify-between items-center shadow-custom-3 w-3/4 m-auto">
                <p class="text-gray-300 ml-4">{{ city ? city : 'Start your search'}}</p>
                <div class="w-12 h-12 flex items-center justify-center rounded-full bg-default-red">
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
        return {
            search: null,
            city: new URL(window.location.href).searchParams.get("city"),
        };
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
    },

    mounted() {
        window.addEventListener('scroll', this.handleScroll);
    },

    beforeUnmount() {
        window.removeEventListener('scroll', this.handleScroll);
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