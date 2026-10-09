<template>
  <section class="screen-stack">
    <AppPageHeader title="ODK/mobile sync" subtitle="Monitor mobile devices, pending uploads, and offline form availability">
      <template #actions>
        <QdsButton icon="wifi">Sync now</QdsButton>
      </template>
    </AppPageHeader>

    <div class="metric-grid">
      <QdsCard class="metric-card metric-card--green">
        <span class="metric-icon"><AppIcon name="wifi" /></span>
        <div><strong>3</strong><span>Devices</span><small>Connected</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--amber">
        <span class="metric-icon"><AppIcon name="upload" /></span>
        <div><strong>6</strong><span>Pending uploads</span><small>Saved offline</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--blue">
        <span class="metric-icon"><AppIcon name="forms" /></span>
        <div><strong>1</strong><span>Published form</span><small>Available offline</small></div>
      </QdsCard>
      <QdsCard class="metric-card metric-card--green">
        <span class="metric-icon"><AppIcon name="building" /></span>
        <div><strong>18</strong><span>Facilities</span><small>Cached on device</small></div>
      </QdsCard>
    </div>

    <QdsCard class="qds-table-card">
      <div class="qds-table sync-table">
        <div class="qds-table__head">
          <span>Device</span>
          <span>Officer</span>
          <span>Last sync</span>
          <span>Pending</span>
          <span>Status</span>
          <span>Action</span>
        </div>
        <div v-for="device in devices" :key="device.id" class="qds-table__row">
          <span class="table-title">{{ device.device }}</span>
          <span>{{ device.officer }}</span>
          <span>{{ device.lastSync }}</span>
          <span>{{ device.pendingUploads }}</span>
          <QdsBadge :tone="device.tone">{{ device.status }}</QdsBadge>
          <span class="row-actions">
            <QdsButton variant="tiny">Inspect</QdsButton>
          </span>
        </div>
      </div>
    </QdsCard>
  </section>
</template>

<script setup lang="ts">
import AppIcon from '../AppIcon.vue';
import AppPageHeader from '../components/layout/AppPageHeader.vue';
import QdsBadge from '../components/qds/QdsBadge.vue';
import QdsButton from '../components/qds/QdsButton.vue';
import QdsCard from '../components/qds/QdsCard.vue';
import { syncDevices as devices } from '../services/workflows';
</script>
