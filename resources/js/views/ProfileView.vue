<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';
import {
    startRegistration,
    startAuthentication,
    browserSupportsWebAuthn,
} from '@simplewebauthn/browser';

const auth    = useAuthStore();
const saving  = ref(false);
const success = ref('');
const error   = ref('');

const form = ref({
    name:  auth.user?.name  ?? '',
    email: auth.user?.email ?? '',
});

async function saveProfile() {
    success.value = '';
    error.value   = '';
    saving.value  = true;
    try {
        const { data } = await axios.put('/api/v1/user/profile', form.value);
        auth.setAuth(data.data ?? data.user ?? data, auth.token);
        success.value = 'Profile updated.';
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Save failed.';
    } finally {
        saving.value = false;
    }
}

async function exportData() {
    try {
        const { data } = await axios.get('/api/v1/user/data-export');
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = 'biotracker-export.json';
        document.body.appendChild(a); a.click();
        document.body.removeChild(a); URL.revokeObjectURL(url);
    } catch {
        alert('Export failed. Please try again.');
    }
}

// ── Passkeys ────────────────────────────────────────────────────────────────
const passkeys        = ref([]);
const passkeyName     = ref('');
const passkeyWorking  = ref(false);
const passkeyError    = ref('');
const passkeySuccess  = ref('');
const webAuthnSupported = browserSupportsWebAuthn();

async function loadPasskeys() {
    try {
        const { data } = await axios.get('/api/v1/user/passkeys');
        passkeys.value = data.data;
    } catch { /* silently fail */ }
}

async function addPasskey() {
    passkeyError.value   = '';
    passkeySuccess.value = '';
    passkeyWorking.value = true;
    try {
        // 1. Get challenge from server
        const { data: optData } = await axios.get('/api/v1/user/passkeys/register-options');

        // 2. Ask authenticator to create credential
        const attestation = await startRegistration({ optionsJSON: optData.options });

        // 3. Send attestation to server
        await axios.post('/api/v1/user/passkeys/register', {
            challenge_token: optData.challenge_token,
            name:            passkeyName.value || undefined,
            credential:      attestation,
        });

        passkeyName.value    = '';
        passkeySuccess.value = 'Passkey added successfully.';
        await loadPasskeys();
    } catch (e) {
        if (e.name === 'NotAllowedError') {
            passkeyError.value = 'Registration cancelled.';
        } else {
            passkeyError.value = e.response?.data?.message ?? e.message ?? 'Registration failed.';
        }
    } finally {
        passkeyWorking.value = false;
    }
}

async function removePasskey(id) {
    if (!confirm('Remove this passkey?')) return;
    try {
        await axios.delete(`/api/v1/user/passkeys/${id}`);
        await loadPasskeys();
    } catch {
        passkeyError.value = 'Failed to remove passkey.';
    }
}

onMounted(loadPasskeys);

async function deleteAccount() {
    if (!confirm('Permanently delete your account and ALL health data?\n\nThis action CANNOT be undone.')) return;
    const input = prompt('Type DELETE to confirm account deletion:');
    if (input !== 'DELETE') return;
    try {
        await axios.delete('/api/v1/user/account');
        auth.clearAuth();
        window.location.href = '/login';
    } catch {
        alert('Account deletion failed. Please try again.');
    }
}
</script>

<template>
  <div class="p-6 max-w-xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold">Profile & Settings</h1>

    <!-- Profile -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6">
      <h2 class="font-semibold mb-4">Personal Details</h2>
      <form @submit.prevent="saveProfile" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Name</label>
          <input v-model="form.name" type="text" required
            class="w-full px-3 py-2.5 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-zinc-400 mb-1.5">Email</label>
          <input v-model="form.email" type="email" required
            class="w-full px-3 py-2.5 bg-zinc-800 border border-zinc-700 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500" />
        </div>
        <div v-if="success" class="text-sm text-teal-400 bg-teal-400/10 rounded-lg px-3 py-2">{{ success }}</div>
        <div v-if="error"   class="text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2">{{ error }}</div>
        <button type="submit" :disabled="saving"
          class="px-4 py-2 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors">
          {{ saving ? 'Saving…' : 'Save Changes' }}
        </button>
      </form>
    </div>

    <!-- Account info -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6">
      <h2 class="font-semibold mb-1">Account</h2>
      <p class="text-sm text-zinc-500 mb-4">Member since account creation. All health data is encrypted at rest.</p>
      <div class="flex items-center gap-2 text-xs text-zinc-500 bg-zinc-800/60 rounded-lg px-3 py-2 mb-4">
        <svg class="w-3.5 h-3.5 text-teal-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        Health data encrypted with AES-256
      </div>
    </div>

    <!-- Data export -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6">
      <h2 class="font-semibold mb-1">Export Your Data</h2>
      <p class="text-sm text-zinc-500 mb-4">Download all your health data as JSON (GDPR Article 20 — Right to data portability).</p>
      <button @click="exportData"
        class="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-sm font-semibold rounded-lg transition-colors">
        Download Data Export
      </button>
    </div>

    <!-- Passkeys -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-6">
      <div class="flex items-center gap-2 mb-1">
        <svg class="w-4 h-4 text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
        </svg>
        <h2 class="font-semibold">Passkeys</h2>
      </div>
      <p class="text-sm text-zinc-500 mb-4">
        Phishing-resistant biometric login — Touch ID, Face ID, Windows Hello, or a hardware key.
      </p>

      <div v-if="!webAuthnSupported" class="text-sm text-amber-400 bg-amber-400/10 rounded-lg px-3 py-2 mb-4">
        Your browser does not support passkeys.
      </div>

      <template v-else>
        <!-- Registered passkeys list -->
        <div v-if="passkeys.length" class="space-y-2 mb-4">
          <div v-for="pk in passkeys" :key="pk.id"
            class="flex items-center justify-between bg-zinc-800 rounded-lg px-3 py-2.5">
            <div>
              <p class="text-sm font-medium">{{ pk.name }}</p>
              <p class="text-xs text-zinc-500">
                Added {{ new Date(pk.created_at).toLocaleDateString() }}
                <span v-if="pk.last_used_at"> · Last used {{ new Date(pk.last_used_at).toLocaleDateString() }}</span>
              </p>
            </div>
            <button @click="removePasskey(pk.id)"
              class="text-xs text-red-400 hover:text-red-300 transition-colors px-2 py-1 rounded hover:bg-red-900/20">
              Remove
            </button>
          </div>
        </div>
        <p v-else class="text-sm text-zinc-500 mb-4">No passkeys registered yet. Add one below to enable biometric login.</p>

        <!-- Add passkey -->
        <div class="flex gap-2">
          <input v-model="passkeyName" type="text" placeholder="Name (e.g. MacBook Touch ID)"
            class="input-field flex-1 text-sm" autocomplete="off" />
          <button @click="addPasskey" :disabled="passkeyWorking"
            class="px-4 py-2 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors whitespace-nowrap">
            {{ passkeyWorking ? 'Waiting…' : 'Add Passkey' }}
          </button>
        </div>

        <div v-if="passkeySuccess" class="text-sm text-teal-400 bg-teal-400/10 rounded-lg px-3 py-2 mt-3">{{ passkeySuccess }}</div>
        <div v-if="passkeyError"   class="text-sm text-red-400 bg-red-400/10 rounded-lg px-3 py-2 mt-3">{{ passkeyError }}</div>
      </template>
    </div>

    <!-- Danger zone -->
    <div class="border border-red-900/50 bg-red-950/20 rounded-xl p-6">
      <h2 class="font-semibold text-red-400 mb-1">Danger Zone</h2>
      <p class="text-sm text-zinc-500 mb-4">
        Permanently delete your account and all associated health data. This cannot be undone.
      </p>
      <button @click="deleteAccount"
        class="px-4 py-2 bg-red-700 hover:bg-red-600 text-white text-sm font-semibold rounded-lg transition-colors">
        Delete Account
      </button>
    </div>
  </div>
</template>
