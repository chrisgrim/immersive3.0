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

import { ClickOutsideDirective } from './Directives/ClickOutsideDirective';

const app = createApp({});

// Setup axios
window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

app.component('vue-nav-search', NavSearch);
app.component('vue-nav-profile', NavProfile);

app.component('vue-show-more', ShowMore);

app.component('vue-show-purchase', ShowPurchase);
app.component('vue-show-map', ShowMap);

app.component('vue-reset-password', ResetPassword);

app.directive('click-outside', ClickOutsideDirective);



app.mount('#app');