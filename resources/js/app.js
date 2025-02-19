import './bootstrap';
import { createApp } from 'vue/dist/vue.esm-bundler.js';
import CountryVideos from './components/CountryVideos.vue';

const app = createApp({});
app.component('country-videos', CountryVideos);
app.mount('#app');
