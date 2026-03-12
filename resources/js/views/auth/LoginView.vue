<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';
import { startAuthentication, browserSupportsWebAuthn } from '@simplewebauthn/browser';

const router = useRouter();
const auth   = useAuthStore();

const form         = ref({ email: '', password: '' });
const error        = ref('');
const loading      = ref(false);
const totpRequired = ref(false);
const totpCode     = ref('');
const loginToken   = ref('');

const passkeyLoading    = ref(false);
const webAuthnSupported = browserSupportsWebAuthn();

async function submit() {
    error.value   = '';
    loading.value = true;
    try {
        const { data } = await axios.post('/api/v1/login', form.value);
        if (data.requires_totp) {
            totpRequired.value = true;
            loginToken.value   = data.login_token ?? '';
        } else {
            auth.setAuth(data.user, data.token);
            router.push('/');
        }
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Login failed.';
    } finally {
        loading.value = false;
    }
}

async function submitTotp() {
    error.value   = '';
    loading.value = true;
    try {
        const { data } = await axios.post('/api/v1/login/totp', {
            code:        totpCode.value,
            login_token: loginToken.value,
        });
        auth.setAuth(data.user, data.token);
        router.push('/');
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Invalid code.';
    } finally {
        loading.value = false;
    }
}

async function loginWithPasskey() {
    error.value         = '';
    passkeyLoading.value = true;
    try {
        // 1. Get authentication challenge
        const { data: challengeData } = await axios.get('/api/v1/passkeys/challenge');

        // 2. Trigger browser authenticator
        const assertion = await startAuthentication({ optionsJSON: challengeData.options });

        // 3. Verify with server
        const { data } = await axios.post('/api/v1/passkeys/authenticate', {
            challenge_token: challengeData.challenge_token,
            credential:      assertion,
        });

        auth.setAuth(data.user, data.token);
        router.push('/');
    } catch (e) {
        if (e.name === 'NotAllowedError') {
            error.value = 'Passkey login cancelled.';
        } else {
            error.value = e.response?.data?.message ?? e.message ?? 'Passkey login failed.';
        }
    } finally {
        passkeyLoading.value = false;
    }
}
</script>

<template>
  <div class="min-h-screen bg-zinc-950 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
      <!-- Logo -->
      <div class="flex justify-center mb-8">
        <div class="flex items-center gap-2.5">
          <div class="w-10 h-10 rounded-xl bg-teal-500 flex items-center justify-center">
            <svg class="w-5.5 h-5.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
          </div>
          <span class="text-2xl font-bold tracking-tight">BioTracker</span>
        </div>
      </div>

      <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-8">
        <!-- TOTP step -->
        <template v-if="totpRequired">
          <h1 class="text-xl font-semibold mb-1">Two-factor auth</h1>
          <p class="text-sm text-zinc-400 mb-6">Enter the 6-digit code from your authenticator app.</p>
          <form @submit.prevent="submitTotp" class="space-y-4">
            <input
              v-model="totpCode"
              type="text"
              inputmode="numeric"
              maxlength="6"
              placeholder="000000"
              autofocus
              class="w-full px-3 py-3 bg-zinc-800 border border-zinc-700 rounded-lg text-center text-2xl tracking-[0.5em] focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent"
            />
            <div v-if="error" class="text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ error }}</div>
            <button type="submit" :disabled="loading"
              class="w-full py-2.5 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white font-semibold rounded-lg transition-colors">
              {{ loading ? 'Verifying…' : 'Verify' }}
            </button>
          </form>
        </template>

        <!-- Login step -->
        <template v-else>
          <h1 class="text-xl font-semibold mb-1">Sign in</h1>
          <p class="text-sm text-zinc-400 mb-6">Welcome back to your health journal.</p>
          <form @submit.prevent="submit" class="space-y-4" autocomplete="off">
            <div>
              <label class="block text-sm font-medium mb-1.5 text-zinc-200">Email</label>
              <input v-model="form.email" type="email" placeholder="you@example.com" required autofocus
                autocomplete="username"
                class="input-field" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1.5 text-zinc-200">Password</label>
              <input v-model="form.password" type="password" placeholder="••••••••" required
                autocomplete="current-password"
                class="input-field" />
            </div>
            <div v-if="error" class="text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ error }}</div>
            <button type="submit" :disabled="loading"
              class="w-full py-2.5 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white font-semibold rounded-lg transition-colors">
              {{ loading ? 'Signing in…' : 'Sign in' }}
            </button>
          </form>

          <!-- Passkey login -->
          <template v-if="webAuthnSupported">
            <div class="flex items-center gap-3 my-5">
              <div class="flex-1 h-px bg-zinc-800"></div>
              <span class="text-xs text-zinc-600">or</span>
              <div class="flex-1 h-px bg-zinc-800"></div>
            </div>
            <button @click="loginWithPasskey" :disabled="passkeyLoading"
              class="w-full py-2.5 bg-zinc-800 hover:bg-zinc-700 disabled:opacity-50 border border-zinc-700 text-sm font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
              <svg class="w-4 h-4 text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
              </svg>
              {{ passkeyLoading ? 'Waiting for device…' : 'Sign in with passkey' }}
            </button>
          </template>

          <p class="text-sm text-center text-zinc-500 mt-6">
            Don't have an account?
            <RouterLink to="/register" class="text-teal-400 hover:text-teal-300 font-medium">Sign up</RouterLink>
          </p>
        </template>
      </div>
    </div>
  </div>
</template>
