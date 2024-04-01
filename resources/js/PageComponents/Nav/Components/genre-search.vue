<template>
    <div style="width:100%" v-click-outside="() => dropdown = false">
        <div 
            ref="search"
            class="w-full z-[10000]">
            <div class="w-full m-auto">
                <svg class="absolute top-8 left-8 w-8 h-8 fill-black z-[1002]">
                    <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                </svg>
                <input 
                    class="relative rounded-full p-7 pl-24 border border-neutral-300 w-full font-normal z-[1001] focus:border-none focus:rounded-full focus:shadow-custom-7"
                    v-model="searchInput"
                    placeholder="Category and Tag Search"
                    @input="debounce"
                    @focus="dropdown=true"
                    autocomplete="false"
                    onfocus="value = ''" 
                    type="text">
            </div>
            <ul 
                class="bg-white relative w-full m-auto overflow-hidden mt-8 p-8 list-none rounded-5xl shadow-custom-7"
                v-if="dropdown">
                <li 
                    class="flex items-center gap-8 hover:bg-neutral-100" 
                    v-for="genre in searchOptions"
                    :key="genre.model.id + genre.index_name"
                    @click="selectGenre(genre)">
                    <div class="w-20 h-20 m-2 rounded-2xl overflow-hidden flex justify-center items-center">
                        <picture
                            v-if="genre.model.thumbImagePath">       
                            <source 
                                type="image/webp" 
                                :srcset="`${imageUrl}${genre.model.thumbImagePath}`"> 
                            <img :src="`${imageUrl}${genre.model.thumbImagePath.slice(0, -4)}jpg`">
                        </picture>
                        <p v-else>o</p>
                    </div>
                    <p class="text-xl leading-6 mt-2">
                        {{genre.model.name}}
                    </p>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>

export default {


    data() {
        return {
            searchInput: '',
            searchOptions: [ ],
            dropdown: false,
            imageUrl: import.meta.env.VITE_IMAGE_URL
        }
    },

    methods: {
        async searchGenre() {
            await axios.get('/api/search/nav/genres', { params: { keywords: this.searchInput } })
            .then( res => {
                this.searchOptions = res.data;
            })
        },
        selectGenre(genre) {
            this.saveSearchData(genre);
            if (genre.type === 'c') { 
                 window.location.href = `/index/search?category=${genre.id}&searchType=allEvents`
            }
            if (genre.type === 't') { 
                window.location.href = `/index/search?tag=${genre.id}&searchType=allEvents`}
        },
        saveSearchData(genre) {
            if (genre.type === 'c') { 
                axios.post('/search/storedata', {type: 'category', name: genre.name});
            }
        },
        debounce() {
            if (this.timeout) 
                clearTimeout(this.timeout); 
            this.timeout = setTimeout(() => {
                this.searchGenre();
            }, 200); // delay
        },
    },
    
    mounted() {
        this.searchGenre()
    },


};
</script>