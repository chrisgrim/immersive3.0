<template>
    <div style="width:100%" v-click-outside="() => dropdown = false">
        <div 
            ref="search"
            class="w-full z-50">
            <div class="w-full m-auto">
                <svg class="absolute top-8 left-8 w-8 h-8 fill-black z-50">
                    <use xlink:href="/storage/website-files/icons.svg#ri-search-line"></use>
                </svg>
                <input 
                    class="relative rounded-full p-7 pl-24 border border-neutral-300 w-full font-normal z-40 focus:border-none focus:rounded-full focus:shadow-custom-7"
                    v-model="searchInput"
                    placeholder="Event and Organizer Search"
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
                    v-for="item in searchOptions"
                    :key="item.model.id + item.index_name"
                    @click="onSelect(item)">
                    <div class="w-20 h-20 rounded-2xl overflow-hidden flex justify-center items-center">
                    <picture
                        v-if="item.model.thumbImagePath">       
                        <source 
                            type="image/webp" 
                            :srcset="`${imageUrl}${item.model.thumbImagePath}`"> 
                        <img :src="`${imageUrl}${item.model.thumbImagePath.slice(0, -4)}jpg`">
                    </picture>
                    </div>
                    <p class="text-xl leading-6 mt-2">
                        {{item.model.name}}
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
        async generateSearchList () {
            await axios.get('/api/search/nav/names', { params: { keywords:  this.searchInput } })
            .then( res => { 
                this.searchOptions = res.data })
        },
        onSelect(item) {
            this.saveSearchData(item);
            if (item.index_name == 'organizers') window.location.href = `/organizers/${item.model.slug}`
            if (item.index_name == 'events') window.location.href = `/events/${item.model.slug}`
        },
        saveSearchData(item) {
            if (item.index_name == 'organizers') { var data = 'organizer'}
            if (item.index_name == 'events') { var data = 'event'}
            axios.post('/search/storedata', {type: data, name: item.model.name});
        },
        debounce() {
            if (this.timeout) 
                clearTimeout(this.timeout); 
            this.timeout = setTimeout(() => {
                this.generateSearchList();
            }, 200); // delay
        },
    },

    mounted() {
        this.generateSearchList()
    },


};
</script>