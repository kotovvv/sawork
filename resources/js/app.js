import { createApp } from 'vue';
import vuetify from "./vuetify";
import App from '@/Main.vue';
import VueMoment from 'vue3-moment';

// import Router from '@/router/router.js';
// import Store from '@/store/store.js';

// createApp(App).use(Router).use(Store).mount('#app');
createApp(App).use(vuetify).use(VueMoment).mount('#app');
