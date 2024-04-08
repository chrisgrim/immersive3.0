<template>
    <div class="flex justify-end relative z-30 items-center">
    	<!-- If user is logged in -->
        <template v-if="user">
            <div 
                class="relative ml-8" 
                v-if="!user.hasCreatedOrganizers">
                <a href="/events/create">
                    <span class="text-xl font-medium hover:text-black hover:font-semibold">Submit Your Experience (Free)</span>
                </a>
            </div>
            <div 
                class="relative ml-8" 
                v-if="user.hasCreatedOrganizers">
                <a href="/create/events/edit">
                    <span class="text-xl font-medium hover:text-black hover:font-semibold">Your Events</span>
                </a>
            </div>
        </template>

        <!-- If user is guest -->
        <template v-else>
            <div class="relative ml-8">
                <a href="/register?create=true">
                    <span class="text-xl font-medium hover:text-black hover:font-semibold">Submit Your Experience (Free)</span>
                </a>
            </div>
        </template>
        <div class="relative ml-8" >
	    	<div class="w-12 h-12" v-click-outside="() => { dropdown = false; }">
	    		<div 
		            :style="{ background: userColor }" 
		            @click="onToggle" 
		            :class="{ 'shadow-custom-2' : dropdown }"
		            class="cursor-pointer overflow-hidden flex justify-center items-center w-12 h-12 rounded-full hover:shadow-custom-2">
		            <!-- -->
		            <!-- If user is logged in -->
		            <template v-if="user">
		                <div 
		                    class="rounded-full bg-default-red w-4 h-4 absolute top-0 right-0 border border-white"
		                    v-if="user.unread" />
		                <template v-if="user.largeImagePath">
		                    <picture>
		                        <source 
		                            type="image/webp" 
		                            :srcset="`${$envImageUrl}${user.thumbImagePath}`"> 
		                        <img 
		                            class="w-12 h-12"
		                            :src="`${$envImageUrl}${user.thumbImagePath.slice(0, -4)}jpg?timestamp=${new Date().getTime()}`" 
		                            :alt="user.name + `'s account`">
		                    </picture>
		                </template>
		                <template v-else-if="user.gravatar">
		                    <img 
		                        :src="user.gravatar" 
		                        class="w-12 h-12"
		                        :alt="user.name + `'s account`">
		                </template>
		                <template v-else>
		                    <svg class="w-10 h-10 fill-white">
			                    <use :xlink:href="`/storage/website-files/icons.svg#ri-user-line`" />
			                </svg>
		                </template>
		            </template>
		            <!-- If user is a guest -->
		            <template v-else>
		                <svg class="w-10 h-10 fill-white">
		                    <use :xlink:href="`/storage/website-files/icons.svg#ri-user-line`" />
		                </svg>
		            </template>
		        </div>

		        <ul 
		            v-if="dropdown" 
		            class="z-10 mt-8 min-w-[24rem] rounded-3xl overflow-hidden block shadow-custom-1 absolute right-0 top-full bg-white py-4">
		            <template v-if="user">
		                <a 
		                    v-if="user.hasMessages"
		                    class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
		                    href="/messages">
		                    Inbox
		                    <div 
		                        class="ml-2 rounded-full bg-default-red w-4 h-4 top-0 right-0"
		                        v-if="user.unread" />
		                </a>
		                <a 
		                    v-if="user.isCurator"
		                    class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
		                    href="/communities">
		                    Communities
		                </a>
		                <a 
		                    class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
		                    :href="`/users/${user.id}`">
		                    Profile
		                </a>
		                <a 
		                    class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
		                    href="/account-settings">
		                    Account
		                </a>
		                <a 
		                    v-if="user.isCurator"
		                    class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
		                    href="/admin/dashboard">
		                    Admin Dashboard
		                </a>
		                <div 
		                    class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
		                    @click="logout()">
		                    Logout
		                </div>
		            </template>
		            <template v-else>
		                <div 
		                    class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
		                    @click="onLogin">
		                    Register
		                </div>
		                <div 
		                    class="font-semibold p-6 cursor-pointer flex whitespace-nowrap w-full items-center hover:bg-slate-100"
		                    @click="onLogin">
		                    Login
		                </div>
		            </template>
		        </ul>
	    	</div>
	    </div>
	    <Login 
	    	@close="showLogin = !showLogin"
	    	v-if="showLogin" />
    </div>
</template>

<script>
	import Login from '../../Auth/login.vue'

    export default {

        props: ['user'],

        components: { Login },

        computed: {
	        userColor() {
	            return this.user ? this.user.hexColor : '#717171';
	        },
	    },

        data() {
            return {
                dropdown:false,
                showLogin:false,
                hex: this.user ? this.user.hexColor : `#717171`,
            };
        },

        methods: {
            async logout() {
                await axios.post('/logout')
                location.reload()
            }, 
            onLogin(value) {
                this.dropdown = false;
                this.showLogin = true;
            },
            onToggle() {
                this.dropdown = !this.dropdown
            },
        },
    }
</script>



