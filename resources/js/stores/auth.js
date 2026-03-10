import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useAuthStore = defineStore('auth', () => {
    const user  = ref(JSON.parse(localStorage.getItem('auth_user') || 'null'));
    const token = ref(localStorage.getItem('auth_token') || null);

    const isAuthenticated = computed(() => !!token.value);

    function setAuth(userData, authToken) {
        user.value  = userData;
        token.value = authToken;
        localStorage.setItem('auth_user', JSON.stringify(userData));
        localStorage.setItem('auth_token', authToken);
        axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`;
    }

    function clearAuth() {
        user.value  = null;
        token.value = null;
        localStorage.removeItem('auth_user');
        localStorage.removeItem('auth_token');
        delete axios.defaults.headers.common['Authorization'];
    }

    // Re-hydrate axios header on store init
    if (token.value) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
    }

    return { user, token, isAuthenticated, setAuth, clearAuth };
});
