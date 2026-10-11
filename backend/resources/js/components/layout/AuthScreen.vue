<template>
  <main class="auth-screen">
    <section class="auth-brand">
      <BrandMark theme="light" />
      <div>
        <p class="qds-kicker">W01</p>
        <h1>Better data. Stronger healthcare.</h1>
        <p>Digitize quality assessments, sync facility records, and keep review teams aligned from one secure workspace.</p>
      </div>
    </section>

    <section class="auth-panel">
      <form class="qds-card auth-card" @submit.prevent="submit">
        <p class="qds-kicker">W01</p>
        <h2>Welcome back</h2>
        <p class="qds-muted">Sign in to access DataKit Quality Assurance.</p>
        <QdsField v-model="email" label="Email address" type="email" />
        <QdsField v-model="password" label="Password" type="password" />
        <p v-if="errorMessage" class="auth-error">{{ errorMessage }}</p>
        <div class="auth-row">
          <label class="qds-check">
            <input type="checkbox" checked>
            <span>Remember me</span>
          </label>
          <a href="#">Forgot password?</a>
        </div>
        <QdsButton type="submit" block>{{ submitting ? 'Signing in...' : 'Sign in' }}</QdsButton>
        <p class="auth-note">By signing in, you agree to the DataKit terms of use.</p>
      </form>
    </section>
  </main>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import QdsButton from '../qds/QdsButton.vue';
import QdsField from '../qds/QdsField.vue';
import BrandMark from './BrandMark.vue';
import { loginOdkUser } from '../../services/auth';

const emit = defineEmits<{
  'signed-in': [user: Awaited<ReturnType<typeof loginOdkUser>>];
}>();
const email = ref('');
const password = ref('');
const submitting = ref(false);
const errorMessage = ref('');

const submit = async () => {
  errorMessage.value = '';
  submitting.value = true;
  try {
    emit('signed-in', await loginOdkUser(email.value.trim(), password.value));
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to sign in.';
  } finally {
    submitting.value = false;
  }
};
</script>
