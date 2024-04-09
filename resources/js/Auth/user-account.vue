<template>
	<div>
		<div class="md:px-12 lg:p-32 bg-white shadow rounded-lg w-full">
            <section>
                <header>
                    <h2 class="text-5xl font-semibold text-gray-600">Account</h2>
                </header>
                <div class="border-b mt-16 group">
                	<h3 class="text-2xl uppercase">Notifications</h3>
                </div>
                <div>
                	<div class="flex flex-row justify-between w-[70rem] items-center mt-10">
                		<div>
                			<h3 class="text-3xl">Subscribe to monthly newsletter</h3>
	                		<p class="text-1xl">Get our monthly newsletters about the latest and greatest immersive events.</p>
                		</div>
                		<div>
                			<label class="inline-flex items-center cursor-pointer">
							  <input type="checkbox" value="" class="sr-only peer" v-model="newsletter">
							  <div class="relative w-[5.5rem] h-[3.1rem] bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[3px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-10 after:w-10 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
							</label>
                		</div>
	                </div>
	                <div class="flex flex-row justify-between w-[70rem] items-center mt-10">
	                	<div>
	                		<h3 class="text-3xl">Subscribe to event update newsletters</h3>
		                	<p class="text-1xl">Get the latest updates about the organizations and events you have liked on EI.</p>
	                	</div>
	                	<div>
                			<label class="inline-flex items-center cursor-pointer">
							  <input type="checkbox" value="" class="sr-only peer" v-model="eventNewsletter">
							  <div class="relative w-[5.5rem] h-[3.1rem] bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[3px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-10 after:w-10 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
							</label>
                		</div>
	                </div>
	                <div class="flex flex-row justify-between w-[70rem] items-center mt-10">
	                	<div>
	                		<h3 class="text-3xl">Email Messages</h3>
	                		<p class="text-1xl">Get an email whenever a user or admin sends you a message.</p>
		                </div>
		                <div>
	            			<label class="inline-flex items-center cursor-pointer">
							  <input type="checkbox" value="" class="sr-only peer" v-model="email">
							  <div class="relative w-[5.5rem] h-[3.1rem] bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[3px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-10 after:w-10 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
							</label>
	            		</div>
	                </div>
                </div>
            </section>
        </div>
	</div>
</template>


<script>
import { ref, reactive } from 'vue';

export default {
    props: ['loaduser'],
    setup(props) {
        const user = reactive({ ...props.loaduser });
        const toggleNotifications = ref(false);
        const newsletter = ref(false);
        const eventNewsletter = ref(false);
        const email = ref(false);

        
        // Methods
        const onSubmit = async () => {
            disabled.value = true;
            try {
                const response = await axios.patch(`/users/${user.id}`, user); // Assuming 'user' is the payload
                Object.assign(user, response.data);
                updated.value = true;
                setTimeout(() => (updated.value = false), 3000);
            } catch (err) {
                console.error(err);
            } finally {
                console.log();
                disabled.value = false;
            }
        };


        return {
            user, toggleNotifications, newsletter, eventNewsletter, email
        };
    }
};
</script>