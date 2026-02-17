import { createRouter, createWebHistory } from 'vue-router';
import Machines from './pages/Machines.vue';
import Inventory from './pages/Inventory.vue';
import Buy from './pages/Buy.vue';

const routes = [
    { path: '/', redirect: '/machines' },
    { path: '/machines', component: Machines },
    { path: '/inventory', component: Inventory },
    { path: '/buy', component: Buy },
];

export default createRouter({
    history: createWebHistory(),
    routes,
});
