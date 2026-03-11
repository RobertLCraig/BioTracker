<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';

const router = useRouter();
const auth   = useAuthStore();

const form = ref({
    name:                  '',
    email:                 '',
    password:              '',
    password_confirmation: '',
    privacy_consent:       false,
    terms_accepted:        false,
});
const error   = ref('');
const loading = ref(false);

async function submit() {
    error.value   = '';
    loading.value = true;
    try {
        const { data } = await axios.post('/api/v1/register', form.value);
        auth.setAuth(data.user, data.token);
        router.push('/');
    } catch (e) {
        const errs = e.response?.data?.errors;
        error.value = errs
            ? Object.values(errs).flat().join(' ')
            : (e.response?.data?.message ?? 'Registration failed.');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
  <div class="min-h-screen bg-zinc-950 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
      <div class="flex justify-center mb-8">
        <div class="flex items-center gap-2.5">
          <div class="w-10 h-10 rounded-xl bg-teal-500 flex items-center justify-center">
            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
          </div>
          <span class="text-2xl font-bold tracking-tight">BioTracker</span>
        </div>
      </div>

      <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-8">
        <h1 class="text-xl font-semibold mb-1">Create account</h1>
        <p class="text-sm text-zinc-400 mb-6">Start tracking your health today.</p>

        <form @submit.prevent="submit" class="space-y-4" autocomplete="off">
          <div>
            <label class="block text-sm font-medium mb-1.5 text-zinc-200">Full name</label>
            <input
              v-model="form.name"
              type="text"
              placeholder="Jane Smith"
              required
              autofocus
              autocomplete="name"
              class="input-field"
            />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1.5 text-zinc-200">Email</label>
            <input
              v-model="form.email"
              type="email"
              placeholder="you@example.com"
              required
              autocomplete="email"
              class="input-field"
            />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1.5 text-zinc-200">Password</label>
            <input
              v-model="form.password"
              type="password"
              placeholder="At least 8 characters"
              required
              autocomplete="new-password"
              class="input-field"
            />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1.5 text-zinc-200">Confirm password</label>
            <input
              v-model="form.password_confirmation"
              type="password"
              placeholder="Repeat password"
              required
              autocomplete="new-password"
              class="input-field"
            />
          </div>

          <!-- Privacy consent (maps to privacy_consent in API) -->
          <label class="flex items-start gap-2.5 cursor-pointer group">
            <input
              v-model="form.privacy_consent"
              type="checkbox"
              required
              class="mt-0.5 accent-teal-500 shrink-0 w-4 h-4"
            />
            <span class="text-xs text-zinc-300 leading-relaxed group-hover:text-zinc-100 transition-colors">
              I consent to my health data being stored and processed in accordance with the
              <span class="text-teal-400">privacy policy</span>.
            </span>
          </label>

          <!-- Terms acceptance (maps to terms_accepted in API) -->
          <label class="flex items-start gap-2.5 cursor-pointer group">
            <input
              v-model="form.terms_accepted"
              type="checkbox"
              required
              class="mt-0.5 accent-teal-500 shrink-0 w-4 h-4"
            />
            <span class="text-xs text-zinc-300 leading-relaxed group-hover:text-zinc-100 transition-colors">
              I accept the <span class="text-teal-400">terms of service</span>.
            </span>
          </label>

          <div v-if="error" class="text-sm text-red-400 bg-red-400/10 border border-red-400/20 rounded-lg px-3 py-2">
            {{ error }}
          </div>

          <button
            type="submit"
            :disabled="loading || !form.privacy_consent || !form.terms_accepted"
            class="w-full py-2.5 bg-teal-500 hover:bg-teal-400 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-lg transition-colors"
          >
            {{ loading ? 'Creating account…' : 'Create account' }}
          </button>
        </form>

        <p class="text-sm text-center text-zinc-500 mt-6">
          Already have an account?
          <RouterLink to="/login" class="text-teal-400 hover:text-teal-300 font-medium">Sign in</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>
