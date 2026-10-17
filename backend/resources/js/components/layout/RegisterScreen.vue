<template>
  <main class="auth-screen register-screen">
    <section class="auth-brand">
      <a class="auth-brand-link" href="/" aria-label="Go to DataKit home">
        <BrandMark theme="light" />
      </a>
      <div class="auth-illustration">
        <img :src="illustrationUrl" alt="A health centre with a field assessor syncing a completed checklist from a phone">
      </div>
      <div>
        <p class="qds-kicker">START WITH A WORKSPACE</p>
        <h1>Better data.<br><span>Stronger healthcare.</span></h1>
        <p>Create a workspace for your team, your projects, or your own fieldwork.</p>
      </div>
    </section>

    <section class="auth-panel">
      <form class="qds-card auth-card register-card" @submit.prevent="submit">
        <p class="qds-kicker">CREATE WORKSPACE</p>
        <h2>Set up DataKit</h2>
        <p class="qds-muted">Start collecting and reviewing structured field data in one workspace.</p>
        <div class="register-grid">
          <QdsField v-model="workspaceName" label="Workspace name" />
          <QdsField v-model="workspaceType" label="Workspace type" :options="workspaceTypeOptions" />
          <QdsField v-model="workspaceSlug" label="Workspace slug (optional)" />
          <QdsField v-model="name" label="Your full name" />
          <QdsField v-model="email" label="Email address" type="email" />
          <QdsField v-model="password" label="Password" type="password" />
          <QdsField v-model="passwordConfirmation" label="Confirm password" type="password" />
        </div>
        <p v-if="errorMessage" class="auth-error">{{ errorMessage }}</p>
        <QdsButton type="submit" block>{{ submitting ? 'Creating workspace...' : 'Create workspace' }}</QdsButton>
        <button class="register-back" type="button" @click="$emit('sign-in')">Already have an account? Sign in</button>
      </form>
    </section>
  </main>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import QdsButton from '../qds/QdsButton.vue';
import QdsField from '../qds/QdsField.vue';
import BrandMark from './BrandMark.vue';
import { registerOdkWorkspace, type WorkspaceRegistration } from '../../services/workspaces';

const emit = defineEmits<{
  registered: [result: Awaited<ReturnType<typeof registerOdkWorkspace>>];
  'sign-in': [];
}>();

const workspaceName = ref('');
const workspaceType = ref<WorkspaceRegistration['workspace_type']>('organization');
const workspaceSlug = ref('');
const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const submitting = ref(false);
const errorMessage = ref('');
const illustrationUrl = '/brand/datakit-login-illustration.svg';
const workspaceTypeOptions = [
  { label: 'Organization', value: 'organization' },
  { label: 'Individual', value: 'individual' },
];

const submit = async () => {
  errorMessage.value = '';
  submitting.value = true;

  try {
    emit('registered', await registerOdkWorkspace({
      workspace_name: workspaceName.value.trim(),
      workspace_type: workspaceType.value,
      workspace_slug: workspaceSlug.value.trim() || undefined,
      name: name.value.trim(),
      email: email.value.trim(),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    }));
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to create workspace.';
  } finally {
    submitting.value = false;
  }
};
</script>

<style scoped>
.register-card {
  max-width: 590px;
}

.register-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin: 24px 0;
}

.register-grid :deep(.qds-field:first-child),
.register-grid :deep(.qds-field:nth-child(3)) {
  grid-column: 1 / -1;
}

.register-back {
  display: block;
  margin: 16px auto 0;
  border: 0;
  color: #087a4a;
  background: transparent;
  font: inherit;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
}

@media (max-width: 640px) {
  .register-grid {
    grid-template-columns: 1fr;
  }

  .register-grid :deep(.qds-field:first-child),
  .register-grid :deep(.qds-field:nth-child(3)) {
    grid-column: auto;
  }
}
</style>
