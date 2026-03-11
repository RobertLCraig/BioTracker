import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes = [
    { path: '/login',    component: () => import('@/views/auth/LoginView.vue'),    meta: { guest: true } },
    { path: '/register', component: () => import('@/views/auth/RegisterView.vue'), meta: { guest: true } },
    {
        path: '/',
        component: () => import('@/components/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '',             name: 'dashboard',    component: () => import('@/views/DashboardView.vue') },
            { path: 'activity',     name: 'activity',     component: () => import('@/views/ActivityView.vue') },
            { path: 'food',         name: 'food',         component: () => import('@/views/FoodView.vue') },
            { path: 'drink',        name: 'drink',        component: () => import('@/views/DrinkView.vue') },
            { path: 'exercise',     name: 'exercise',     component: () => import('@/views/ExerciseView.vue') },
            { path: 'sleep',        name: 'sleep',        component: () => import('@/views/SleepView.vue') },
            { path: 'excretion',    name: 'excretion',    component: () => import('@/views/ExcretionView.vue') },
            { path: 'medications',  name: 'medications',  component: () => import('@/views/MedicationsView.vue') },
            { path: 'symptoms',     name: 'symptoms',     component: () => import('@/views/SymptomsView.vue') },
            { path: 'vitals',       name: 'vitals',       component: () => import('@/views/VitalsView.vue') },
            { path: 'analytics',    name: 'analytics',    component: () => import('@/views/AnalyticsView.vue') },
            { path: 'achievements', name: 'achievements', component: () => import('@/views/AchievementsView.vue') },
            { path: 'reports',      name: 'reports',      component: () => import('@/views/ReportsView.vue') },
            { path: 'profile',      name: 'profile',      component: () => import('@/views/ProfileView.vue') },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to) => {
    const auth = useAuthStore();
    if (to.meta.requiresAuth && !auth.isAuthenticated) return '/login';
    if (to.meta.guest && auth.isAuthenticated)          return '/';
    return true;
});

export default router;
