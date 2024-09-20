import Vue from 'vue';
import VueRouter from 'vue-router';

import LoginComponent from '../components/LoginComponent.vue';
import ManagerComponent from '../components/manager/ManagerComponent.vue';
import AdminComponent from '../components/admin/AdminComponent.vue';

Vue.use(VueRouter);

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: LoginComponent,
    },
    {
        path: '/manager',
        name: 'Manager',
        component: ManagerComponent,
        meta: { requiresAuth: true, role: 'manager' },
    },
    {
        path: '/admin',
        name: 'Admin',
        component: AdminComponent,
        meta: { requiresAuth: true, role: 'admin' },
    },
];

const router = new VueRouter({
    mode: 'history',
    routes,
});

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token');
    const user = JSON.parse(localStorage.getItem('user'));

    if (to.matched.some(record => record.meta.requiresAuth)) {
        if (!token) {
            next({ name: 'Login' });
        } else {
            if (to.meta.role && user.role.name !== to.meta.role) {
                next({ name: 'Login' });
            } else {
                next();
            }
        }
    } else {
        next();
    }
});

export default router;
