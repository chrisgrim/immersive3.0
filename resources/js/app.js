import { createApp, defineAsyncComponent } from 'vue';
import '../css/app.css';
import axios from 'axios';


// Define an async component
const NavSearch = defineAsyncComponent(() => import('./PageComponents/Nav/nav-search.vue'));
const NavProfile = defineAsyncComponent(() => import('./PageComponents/Nav/nav-profile.vue'));

const SearchLocation = defineAsyncComponent(() => import('./PageComponents/Search/location.vue'));

const ShowMore = defineAsyncComponent(() => import('./GlobalComponents/show-more.vue'));

const ShowPurchase = defineAsyncComponent(() => import('./PageComponents/EventShow/show-purchase.vue'));
const ShowMap = defineAsyncComponent(() => import('./PageComponents/EventShow/show-map.vue'));

const ResetPassword = defineAsyncComponent(() => import('./Auth/reset-password.vue'));
const UserProfile = defineAsyncComponent(() => import('./Auth/user-profile.vue'));
const UserAccount = defineAsyncComponent(() => import('./Auth/user-account.vue'));
const Login = defineAsyncComponent(() => import('./Auth/login.vue'));

const Inbox = defineAsyncComponent(() => import('./PageComponents/Messaging/inbox.vue'));

const Hosting = defineAsyncComponent(() => import('./PageComponents/Creation/index.vue'));
const GettingStarted = defineAsyncComponent(() => import('./PageComponents/Creation/initial.vue'));
const HostingEvent = defineAsyncComponent(() => import('./PageComponents/Creation/Core/index.vue'));

const Admin = defineAsyncComponent(() => import('./PageComponents/Admin/index.vue'));

const CommunityShow = defineAsyncComponent(() => import('./PageComponents/Curated/Communities/show.vue'));
const CommunityEdit = defineAsyncComponent(() => import('./PageComponents/Curated/Communities/edit.vue'));
const PostEdit = defineAsyncComponent(() => import('./PageComponents/Curated/Posts/edit.vue'));
const PostShow = defineAsyncComponent(() => import('./PageComponents/Curated/Posts/show.vue'));

const QuickBar = defineAsyncComponent(() => import('./PageComponents/Nav/quick-bar.vue'));

const SearchAll = defineAsyncComponent(() => import('./PageComponents/Search/all.vue'));


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

app.component('vue-search-location', SearchLocation);

app.component('vue-show-more', ShowMore);

app.component('vue-show-purchase', ShowPurchase);
app.component('vue-show-map', ShowMap);

app.component('vue-reset-password', ResetPassword);
app.component('vue-user-profile', UserProfile);
app.component('vue-user-account', UserAccount)
app.component('vue-user-login', Login);

app.component('vue-inbox', Inbox);

app.component('vue-hosting', Hosting);
app.component('vue-getting-started', GettingStarted);
app.component('vue-hosting-event', HostingEvent);

app.component('vue-admin', Admin);
app.component('vue-community-show', CommunityShow);
app.component('vue-community-edit', CommunityEdit);
app.component('vue-post-edit', PostEdit);
app.component('vue-post-show', PostShow);
app.component('vue-quick-bar', QuickBar);
app.component('vue-search-all', SearchAll);



app.directive('click-outside', ClickOutsideDirective);



app.mount('#app');