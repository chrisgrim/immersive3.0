<template>
    <div class="w-full inline-block relative min-w-[30rem] col-span-3 z-20">
        <div>
        	
        </div>
        <template v-if="search">
            <div 
            	@click="checkClickPosition"
            	class="search-container fixed pt-8 left-0 top-0 w-full z-20">
            	<div class="w-full flex justify-center items-center">
            		<p class="text-xl font-semibold">Search By:</p>
	                <button 
	                    @click="search='l'"
	                    :class="{ 'custom-underline': search==='l' }"
	                    class="tab relative border-none p-4 text-1xl hover:text-black hover:font-medium">
	                    Location
	                </button>
	                <button 
	                    @click="search='t'"
	                    :class="{ 'custom-underline': search==='t' }"
	                    class="tab relative border-none p-4 text-1xl hover:text-black hover:font-medium">
	                    Genre
	                </button>
	                <button 
	                    @click="search='e'"
	                    :class="{ 'custom-underline': search==='e' }"
	                    class="tab relative border-none p-4 text-1xl hover:text-black hover:font-medium">
	                    Name
	                </button>
	                <div class="bg-black w-[.15rem] h-8"/>
	                <a 
	                    class="border-none text-1xl p-4 hover:text-black hover:font-medium hover:border-b-black"
	                    href="/index/search?&searchType=atHome">
	                    At Home 
	                </a>
	                <a 
	                    class="border-none text-1xl p-4 hover:text-black hover:font-medium"
	                    href="/index/search?&category=23&searchType=atHome">
	                    VR 
	                </a>
            	</div>
                <div class="w-full mx-auto flex relative max-w-5xl mt-8">
		            <SearchLocation v-if="search==='l'"/>
                    <SearchGenre v-if="search==='t'"/>
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
    import SearchGenre from './Components/genre-search.vue'
    import SearchEvent from './Components/events-search.vue'

    export default {

        components: { SearchLocation, SearchGenre, SearchEvent },

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
.tab.custom-underline::after {
    content: '';
    position: absolute;
    bottom: 0px; /* Adjusts vertical position of the underline */
    left: 50%;
    transform: translateX(-50%);
    width: 25%; /* Controls the width of the underline */
    height: 2px; /* Thickness of the underline */
    background-color: currentColor; /* Color of the underline */
}
.search-container::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 18rem; /* Height of the transparent area */
    background: white;
    z-index: -1;
    box-shadow: 0 1px 12px rgba(0, 0, 0, 0.08);
}
</style>