import { createApp, defineAsyncComponent } from 'vue';
import '../css/app.css';
import axios from 'axios';


// Define an async component
const NavSearch = defineAsyncComponent(() => import('./PageComponents/Nav/nav-search.vue'));
const NavProfile = defineAsyncComponent(() => import('./PageComponents/Nav/nav-profile.vue'));

const ShowMore = defineAsyncComponent(() => import('./GlobalComponents/show-more.vue'));

const ShowPurchase = defineAsyncComponent(() => import('./PageComponents/EventShow/show-purchase.vue'));

import { ClickOutsideDirective } from './Directives/ClickOutsideDirective';

const app = createApp({});

// Setup axios
window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

app.component('vue-nav-search', NavSearch);
app.component('vue-nav-profile', NavProfile);

app.component('vue-show-more', ShowMore);

app.component('vue-show-purchase', ShowPurchase);

app.directive('click-outside', ClickOutsideDirective);



app.mount('#app');