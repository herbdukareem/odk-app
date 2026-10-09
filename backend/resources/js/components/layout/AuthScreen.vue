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
      <form class="qds-card auth-card" @submit.prevent="$emit('signed-in')">
        <p class="qds-kicker">W01</p>
        <h2>Welcome back</h2>
        <p class="qds-muted">Sign in to access NiCare Quality Assurance.</p>
        <QdsField v-model="email" label="Email address" type="email" />
        <QdsField v-model="password" label="Password" type="password" />
        <div class="auth-row">
          <label class="qds-check">
            <input type="checkbox" checked>
            <span>Remember me</span>
          </label>
          <a href="#">Forgot password?</a>
        </div>
        <QdsButton type="submit" block>Sign in</QdsButton>
        <p class="auth-note">By signing in, you agree to the NiCare terms of use.</p>
      </form>
    </section>
  </main>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import QdsButton from '../qds/QdsButton.vue';
import QdsField from '../qds/QdsField.vue';
import BrandMark from './BrandMark.vue';

defineEmits<{
  'signed-in': [];
}>();

const email = ref('amina.yusuf@health.gov.ng');
const password = ref('password');
</script>
