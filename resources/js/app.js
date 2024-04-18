import { createApp, defineAsyncComponent } from 'vue';
import '../css/app.css';
import axios from 'axios';


// Define an async component
const NavSearch = defineAsyncComponent(() => import('./PageComponents/Nav/nav-search.vue'));
const NavProfile = defineAsyncComponent(() => import('./PageComponents/Nav/nav-profile.vue'));

const ShowMore = defineAsyncComponent(() => import('./GlobalComponents/show-more.vue'));

const ShowPurchase = defineAsyncComponent(() => import('./PageComponents/EventShow/show-purchase.vue'));
const ShowMap = defineAsyncComponent(() => import('./PageComponents/EventShow/show-map.vue'));

const ResetPassword = defineAsyncComponent(() => import('./Auth/reset-password.vue'));
const UserProfile = defineAsyncComponent(() => import('./Auth/user-profile.vue'));
const UserAccount = defineAsyncComponent(() => import('./Auth/user-account.vue'));

const Inbox = defineAsyncComponent(() => import('./PageComponents/Messaging/inbox.vue'));

import { ClickOutsideDirective } from './Directives/ClickOutsideDirective';

const app = createApp({
    data() {
        return {
            // Access the global user data
            user: window.Laravel.user
        };
    }
});

// Setup axios
window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

app.component('vue-nav-search', NavSearch);
app.component('vue-nav-profile', NavProfile);

app.component('vue-show-more', ShowMore);

app.component('vue-show-purchase', ShowPurchase);
app.component('vue-show-map', ShowMap);

app.component('vue-reset-password', ResetPassword);
app.component('vue-user-profile', UserProfile);
app.component('vue-user-account', UserAccount);

app.component('vue-inbox', Inbox);

app.directive('click-outside', ClickOutsideDirective);



app.mount('#app');