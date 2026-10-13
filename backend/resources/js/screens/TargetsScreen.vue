<template>
  <section class="screen-stack targets-screen">
    <AppPageHeader title="Targets" subtitle="Manage the records your workspace assesses. Facilities are just one target type.">
      <template #actions><QdsButton icon="plus" @click="startCreate">New target</QdsButton></template>
    </AppPageHeader>
    <p v-if="notice" class="builder-notice">{{ notice }}</p>
    <p v-if="errorMessage" class="auth-error">{{ errorMessage }}</p>

    <QdsCard padded>
      <div class="target-toolbar">
        <QdsField v-model="selectedType" label="Filter by type" :options="typeOptions" />
        <QdsButton variant="secondary" icon="refresh-cw" @click="loadData">Refresh</QdsButton>
      </div>
      <div class="targets-list">
        <div v-for="target in targets" :key="target.uuid" class="target-row">
          <div><strong>{{ target.name }}</strong><small>{{ labelForType(target.type) }}<span v-if="target.external_id"> · {{ target.external_id }}</span></small></div>
          <span class="target-description">{{ target.description || 'No description' }}</span>
          <QdsButton variant="tiny" @click="startEdit(target)">Edit</QdsButton>
          <QdsButton variant="tiny" @click="archive(target)">Archive</QdsButton>
        </div>
      </div>
      <p v-if="!targets.length" class="qds-muted">No targets match this filter.</p>
    </QdsCard>

    <div v-if="editorOpen" class="field-editor-modal" @click.self="editorOpen = false">
      <QdsCard padded class="target-editor">
        <div class="qds-card-header"><div><h2>{{ editing ? 'Edit target' : 'New target' }}</h2><p>Target type is fixed after creation so assignments remain consistent.</p></div><QdsButton variant="secondary" @click="editorOpen = false">Close</QdsButton></div>
        <QdsField v-if="!editing" v-model="form.type" label="Target type" :options="recordTypeOptions" />
        <QdsField v-model="form.name" label="Name" />
        <QdsField v-model="form.external_id" label="External ID" />
        <QdsField v-model="form.description" label="Description" textarea />
        <QdsButton icon="save" block @click="save">{{ editing ? 'Save target' : 'Create target' }}</QdsButton>
      </QdsCard>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AppPageHeader from '../components/layout/AppPageHeader.vue';
import QdsButton from '../components/qds/QdsButton.vue';
import QdsCard from '../components/qds/QdsCard.vue';
import QdsField from '../components/qds/QdsField.vue';
import { archiveTarget, createTarget, loadTargetTypes, loadTargets, updateTarget, type OdkTarget, type TargetType } from '../services/targets';

const types = ref<TargetType[]>([]);
const targets = ref<OdkTarget[]>([]);
const selectedType = ref('');
const editorOpen = ref(false);
const editing = ref<OdkTarget | null>(null);
const notice = ref('');
const errorMessage = ref('');
const form = reactive({ type: 'site', name: '', external_id: '', description: '' });
const typeOptions = computed(() => [{ label: 'All target types', value: '' }, ...types.value.filter((type) => type.requires_record).map((type) => ({ label: type.label, value: type.key }))]);
const recordTypeOptions = computed(() => types.value.filter((type) => type.requires_record).map((type) => ({ label: type.label, value: type.key })));

const labelForType = (type: string) => types.value.find((item) => item.key === type)?.label || type;
const loadData = async () => {
  try { targets.value = await loadTargets(selectedType.value); } catch (error) { errorMessage.value = error instanceof Error ? error.message : 'Unable to load targets.'; }
};
const startCreate = () => { editing.value = null; Object.assign(form, { type: recordTypeOptions.value[0]?.value || 'site', name: '', external_id: '', description: '' }); editorOpen.value = true; };
const startEdit = (target: OdkTarget) => { editing.value = target; Object.assign(form, { type: target.type, name: target.name, external_id: target.external_id || '', description: target.description || '' }); editorOpen.value = true; };
const save = async () => {
  try {
    if (!form.name.trim()) throw new Error('Target name is required.');
    if (editing.value) await updateTarget(editing.value, { name: form.name, external_id: form.external_id || null, description: form.description || null });
    else await createTarget({ type: form.type, name: form.name, external_id: form.external_id || null, description: form.description || null });
    editorOpen.value = false; notice.value = editing.value ? 'Target updated.' : 'Target created.'; await loadData();
  } catch (error) { errorMessage.value = error instanceof Error ? error.message : 'Unable to save target.'; }
};
const archive = async (target: OdkTarget) => { if (!window.confirm(`Archive ${target.name}?`)) return; try { await archiveTarget(target); notice.value = 'Target archived.'; await loadData(); } catch (error) { errorMessage.value = error instanceof Error ? error.message : 'Unable to archive target.'; } };
watch(selectedType, loadData);
onMounted(async () => { try { types.value = await loadTargetTypes(); await loadData(); } catch (error) { errorMessage.value = error instanceof Error ? error.message : 'Unable to load target types.'; } });
</script>

<style scoped>
.target-toolbar { display: flex; align-items: end; justify-content: space-between; gap: 14px; margin-bottom: 12px; }
.target-toolbar :deep(.qds-field) { min-width: 240px; }
.targets-list { display: grid; }
.target-row { display: grid; grid-template-columns: 1.3fr 1.5fr auto auto; gap: 14px; align-items: center; padding: 14px 0; border-bottom: 1px solid #e1e8e2; }
.target-row strong, .target-row small { display: block; }
.target-row small { margin-top: 4px; color: #718079; font-size: 11px; }
.target-description { color: #617169; font-size: 13px; }
.target-editor { width: min(560px, calc(100vw - 32px)); display: grid; gap: 14px; }
@media (max-width: 720px) { .target-toolbar { align-items: stretch; flex-direction: column; } .target-row { grid-template-columns: 1fr auto; } .target-description { grid-column: 1 / -1; } }
</style>
