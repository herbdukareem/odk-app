<template>
  <section class="screen-stack">
    <AppPageHeader title="Facilities" subtitle="Synced active facilities for the DataKit workspace">
      <template #actions>
        <div class="qds-toolbar">
          <QdsButton icon="wifi" :variant="syncing ? 'secondary' : 'primary'" @click="syncReferences">
            {{ syncing ? 'Syncing...' : 'Sync reference data' }}
          </QdsButton>
          <QdsButton variant="secondary" @click="loadFacilities">Refresh</QdsButton>
        </div>
      </template>
    </AppPageHeader>

    <div v-if="notice" class="builder-notice">{{ notice }}</div>

    <div class="metric-grid">
      <QdsCard class="metric-card metric-card--green">
        <span class="metric-icon"><AppIcon name="building" /></span>
        <div><strong>{{ stats.active }}</strong><span>Active facilities</span><small>Available for assignment</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--blue">
        <span class="metric-icon"><AppIcon name="facilities" /></span>
        <div><strong>{{ stats.total }}</strong><span>Total synced</span><small>All reference facilities</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--green">
        <span class="metric-icon"><AppIcon name="reports" /></span>
        <div><strong>{{ stats.lgas }}</strong><span>LGAs</span><small>Reference coverage</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--amber">
        <span class="metric-icon"><AppIcon name="forms" /></span>
        <div><strong>{{ stats.wards }}</strong><span>Wards</span><small>Reference coverage</small></div>
      </QdsCard>
    </div>

    <QdsCard padded>
      <div class="facility-toolbar">
        <QdsSearch v-model="filters.q" compact placeholder="Search facility name, code, address..." />
        <QdsField v-model="filters.lga_id" label="LGA" :options="lgaOptions" />
        <QdsField v-model="filters.ward_id" label="Ward" :options="wardOptions" />
        <QdsField v-model="filters.active" label="Status" :options="statusOptions" />
      </div>
      <div class="facility-sync-meta">
        <span>Last sync: {{ formatDateTime(lastSync?.finished_at || lastSync?.created_at) }}</span>
        <QdsBadge :tone="lastSyncTone">{{ lastSync?.status || 'not synced' }}</QdsBadge>
      </div>
    </QdsCard>

    <QdsCard class="qds-table-card">
      <div class="qds-table reference-facilities-table">
        <div class="qds-table__head">
          <span>Facility</span>
          <span>LGA / Ward</span>
          <span>Type</span>
          <span>Contact</span>
          <span>Status</span>
          <span>Synced</span>
        </div>
        <div v-for="facility in facilities" :key="facility.id" class="qds-table__row">
          <span class="table-title">
            {{ facility.name }}
            <small>{{ facility.source_code || `DATAKIT-${facility.source_id}` }}</small>
          </span>
          <span>
            {{ facility.lga?.name || 'No LGA' }}
            <small>{{ facility.ward?.name || 'No ward' }}</small>
          </span>
          <span>
            {{ facility.type || facility.level_of_care || 'Facility' }}
            <small>{{ facility.ownership || 'Ownership not set' }}</small>
          </span>
          <span>
            {{ facility.phone || 'No phone' }}
            <small>{{ facility.email || facility.address || 'No contact detail' }}</small>
          </span>
          <QdsBadge :tone="facilityStatusTone(facility)">
            {{ facility.active ? facility.accreditation_status || 'active' : 'inactive' }}
          </QdsBadge>
          <span>{{ formatDateTime(facility.last_synced_at) }}</span>
        </div>
        <div v-if="!loading && facilities.length === 0" class="table-empty">
          No synced facilities match the current filters.
        </div>
        <div v-if="loading" class="table-empty">Loading facilities...</div>
      </div>
    </QdsCard>

    <div class="pagination-row">
      <QdsButton variant="secondary" :disabled="page <= 1" @click="page -= 1">Previous</QdsButton>
      <span>Page {{ page }} of {{ lastPage }}</span>
      <QdsButton variant="secondary" :disabled="page >= lastPage" @click="page += 1">Next</QdsButton>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AppIcon from '../AppIcon.vue';
import AppPageHeader from '../components/layout/AppPageHeader.vue';
import QdsBadge from '../components/qds/QdsBadge.vue';
import QdsButton from '../components/qds/QdsButton.vue';
import QdsCard from '../components/qds/QdsCard.vue';
import QdsField from '../components/qds/QdsField.vue';
import QdsSearch from '../components/qds/QdsSearch.vue';
import {
  facilityStatusTone,
  formatDateTime,
  loadReferenceFacilities,
  syncNicareReferenceData,
  type IntegrationSyncLog,
  type ReferenceFacility,
  type ReferenceLga,
  type ReferenceWard,
} from '../services/facilities';

const facilities = ref<ReferenceFacility[]>([]);
const lgas = ref<ReferenceLga[]>([]);
const wards = ref<ReferenceWard[]>([]);
const lastSync = ref<IntegrationSyncLog | null>(null);
const loading = ref(false);
const syncing = ref(false);
const notice = ref('');
const page = ref(1);
const lastPage = ref(1);
const stats = reactive({ total: 0, active: 0, lgas: 0, wards: 0 });
const filters = reactive({
  q: '',
  lga_id: '',
  ward_id: '',
  active: '1' as '1' | '0' | 'all',
});

const statusOptions = [
  { label: 'Active only', value: '1' },
  { label: 'Inactive only', value: '0' },
  { label: 'All statuses', value: 'all' },
];

const lgaOptions = computed(() => [
  { label: 'All LGAs', value: '' },
  ...lgas.value.map((lga) => ({ label: lga.name, value: String(lga.id) })),
]);

const wardOptions = computed(() => {
  const source = filters.lga_id
    ? wards.value.filter((ward) => String(ward.reference_lga_id) === filters.lga_id)
    : wards.value;

  return [
    { label: 'All wards', value: '' },
    ...source.map((ward) => ({ label: ward.name, value: String(ward.id) })),
  ];
});

const lastSyncTone = computed<'green' | 'amber' | 'red' | 'slate'>(() => {
  if (lastSync.value?.status === 'completed') return 'green';
  if (lastSync.value?.status === 'running') return 'amber';
  if (lastSync.value?.status === 'failed') return 'red';
  return 'slate';
});

const loadFacilities = async () => {
  loading.value = true;
  notice.value = '';

  try {
    const payload = await loadReferenceFacilities({
      ...filters,
      page: page.value,
      per_page: 20,
    });

    facilities.value = payload.facilities.data;
    lastPage.value = payload.facilities.last_page || 1;
    lgas.value = payload.lgas;
    wards.value = payload.wards;
    lastSync.value = payload.last_sync || null;
    Object.assign(stats, payload.stats);
  } catch (error) {
    notice.value = error instanceof Error ? error.message : 'Unable to load facilities.';
  } finally {
    loading.value = false;
  }
};

const syncReferences = async () => {
  syncing.value = true;
  notice.value = '';

  try {
    const counts = await syncNicareReferenceData();
    notice.value = `Reference sync completed: ${counts.facilities || 0} facilities, ${counts.lgas || 0} LGAs, ${counts.wards || 0} wards.`;
    page.value = 1;
    await loadFacilities();
  } catch (error) {
    notice.value = error instanceof Error ? error.message : 'Unable to sync reference data.';
  } finally {
    syncing.value = false;
  }
};

watch(() => ({ ...filters }), () => {
  page.value = 1;
  void loadFacilities();
});

watch(page, () => {
  void loadFacilities();
});

watch(() => filters.lga_id, () => {
  filters.ward_id = '';
});

onMounted(() => {
  void loadFacilities();
});
</script>
