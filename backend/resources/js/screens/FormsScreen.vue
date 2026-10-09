<template>
  <section class="screen-stack">
    <AppPageHeader title="Assessment forms" subtitle="Manage forms used for facility assessments">
      <template #actions>
        <QdsButton icon="plus" @click="createForm">New form</QdsButton>
      </template>
    </AppPageHeader>

    <div class="qds-toolbar">
      <QdsTabs v-model="activeTab" :tabs="tabs" />
      <QdsSearch v-model="search" compact placeholder="Search forms..." />
      <button class="qds-filter" type="button">All types</button>
      <button class="qds-filter" type="button">All statuses</button>
    </div>

    <QdsCard class="qds-table-card">
      <div class="qds-table forms-table">
        <div class="qds-table__head">
          <span>Form name</span>
          <span>Version</span>
          <span>Status</span>
          <span>Description</span>
          <span>Updated</span>
          <span>Actions</span>
        </div>
        <div v-for="form in filteredForms" :key="form.slug" class="qds-table__row">
          <span class="table-title">{{ form.title }}</span>
          <span>Version {{ form.current_version?.version ?? 1 }}</span>
          <QdsBadge :tone="statusTone(form.status)">{{ titleCase(form.status) }}</QdsBadge>
          <span>{{ form.description || 'No description provided' }}</span>
          <span>{{ formatDate(form.updated_at) }}<small>Amina Yusuf</small></span>
          <span class="row-actions">
            <QdsButton variant="tiny" @click="$emit('open-settings', form.slug)">Open</QdsButton>
            <QdsButton icon="clone" variant="tiny" @click="cloneForm(form.slug)">Clone</QdsButton>
            <QdsIconButton icon="more" tiny :aria-label="`More actions for ${form.title}`" />
          </span>
        </div>
        <div v-if="filteredForms.length === 0" class="table-empty">
          No forms match the current filters.
        </div>
      </div>
    </QdsCard>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppPageHeader from '../components/layout/AppPageHeader.vue';
import QdsBadge from '../components/qds/QdsBadge.vue';
import QdsButton from '../components/qds/QdsButton.vue';
import QdsCard from '../components/qds/QdsCard.vue';
import QdsIconButton from '../components/qds/QdsIconButton.vue';
import QdsSearch from '../components/qds/QdsSearch.vue';
import QdsTabs from '../components/qds/QdsTabs.vue';
import { cloneAssessmentForm, createAssessmentForm, type AssessmentForm, formatDate, listAssessmentForms, statusTone, titleCase } from '../services/forms';

const emit = defineEmits<{
  'open-settings': [slug: string];
}>();

const forms = ref<AssessmentForm[]>([]);
const search = ref('');
const activeTab = ref('all');

const loadForms = async () => {
  forms.value = await listAssessmentForms();
};

const createForm = async () => {
  const title = window.prompt('Form title', 'New assessment form');
  if (!title) return;

  const form = await createAssessmentForm({
    title,
    description: 'Configurable facility assessment form',
    target_audience: 'Facility assessment teams',
  });
  await loadForms();
  emit('open-settings', form.slug);
};

const cloneForm = async (slug: string) => {
  const form = await cloneAssessmentForm(slug);
  await loadForms();
  emit('open-settings', form.slug);
};

const countByStatus = (status: string) => forms.value.filter((form) => form.status === status).length;

const tabs = computed(() => [
  { label: `All (${forms.value.length})`, value: 'all' },
  { label: `Draft (${countByStatus('draft')})`, value: 'draft' },
  { label: `Published (${countByStatus('published')})`, value: 'published' },
  { label: `Archived (${countByStatus('archived')})`, value: 'archived' },
]);

const filteredForms = computed(() => {
  const needle = search.value.trim().toLowerCase();
  return forms.value.filter((form) => {
    const matchesTab = activeTab.value === 'all' || form.status === activeTab.value;
    const matchesSearch = !needle || [form.title, form.description, form.slug].some((value) => (value ?? '').toLowerCase().includes(needle));
    return matchesTab && matchesSearch;
  });
});

onMounted(loadForms);
</script>
