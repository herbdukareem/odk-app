<template>
  <section class="screen-stack platform-admin-screen">
    <AppPageHeader title="Platform administration" subtitle="Monitor workspaces and provide controlled support across DataKit.">
      <template #actions>
        <QdsButton icon="plus" @click="newPlanOpen = !newPlanOpen">New plan</QdsButton>
        <QdsButton variant="secondary" icon="refresh-cw" @click="loadData">Refresh</QdsButton>
      </template>
    </AppPageHeader>

    <p v-if="notice" class="builder-notice">{{ notice }}</p>
    <p v-if="errorMessage" class="auth-error">{{ errorMessage }}</p>

    <QdsCard padded>
      <div class="qds-card-header">
        <div>
          <h2>Plans and pricing</h2>
          <p>Set the plans shown to workspaces. Plan codes stay fixed so existing subscriptions and invoices remain connected.</p>
        </div>
      </div>
      <div class="platform-plan-list">
        <div v-for="plan in plans" :key="plan.id" class="platform-plan-row">
          <div class="platform-plan-heading">
            <strong>{{ plan.name }}</strong>
            <small>{{ plan.code }} · {{ plan.subscriptions_count || 0 }} subscriptions · {{ plan.invoices_count || 0 }} invoices</small>
          </div>
          <div class="platform-plan-fields">
            <QdsField v-model="planDrafts[plan.id].name" label="Plan name" />
            <QdsField v-model="planDrafts[plan.id].price_amount" label="Price" type="number" />
            <QdsField v-model="planDrafts[plan.id].currency" label="Currency" />
            <QdsField v-model="planDrafts[plan.id].billing_interval" label="Billing interval" :options="billingIntervalOptions" />
            <QdsField v-model="planDrafts[plan.id].active" label="Availability" :options="planStatusOptions" />
            <QdsField v-model="planDrafts[plan.id].sort_order" label="Display order" type="number" />
            <QdsField v-model="planDrafts[plan.id].provider_plan_code" label="Paystack plan code" />
            <QdsField v-model="planDrafts[plan.id].description" label="Description" textarea :rows="2" />
            <QdsField v-model="planDrafts[plan.id].features" label="Features (comma separated)" textarea :rows="2" />
          </div>
          <div class="platform-plan-limits">
            <span class="platform-plan-limits-title">Usage limits (blank means unlimited)</span>
            <QdsField v-model="planDrafts[plan.id].limits.members" label="Members" type="number" />
            <QdsField v-model="planDrafts[plan.id].limits.forms" label="Forms" type="number" />
            <QdsField v-model="planDrafts[plan.id].limits.exercises" label="Exercises" type="number" />
            <QdsField v-model="planDrafts[plan.id].limits.assignments" label="Assignments" type="number" />
            <QdsField v-model="planDrafts[plan.id].limits.submissions" label="Submissions / month" type="number" />
          </div>
          <div class="platform-plan-actions">
            <span class="platform-plan-preview">{{ formatMoney(planDrafts[plan.id].price_amount, planDrafts[plan.id].currency) }} / {{ planDrafts[plan.id].billing_interval }}</span>
            <div class="action-row">
              <QdsButton variant="secondary" icon="save" @click="savePlan(plan)">Save plan</QdsButton>
              <QdsButton variant="tiny" :icon="plan.active ? 'archive' : 'refresh-cw'" @click="togglePlanArchive(plan)">{{ plan.active ? 'Archive' : 'Restore' }}</QdsButton>
              <QdsButton variant="tiny" icon="clipboard" @click="togglePlanHistory(plan)">{{ historyOpen[plan.id] ? 'Hide history' : 'History' }}</QdsButton>
            </div>
          </div>
          <div v-if="historyOpen[plan.id]" class="platform-plan-history">
            <div v-for="version in planHistory[plan.id] || []" :key="version.id" class="platform-plan-history-row">
              <strong>{{ titleCase(version.action) }}</strong>
              <span>{{ version.changed_by?.name || 'System' }}</span>
              <small>{{ formatDate(version.created_at) }}</small>
            </div>
            <p v-if="!(planHistory[plan.id] || []).length" class="qds-muted">No plan history yet.</p>
          </div>
        </div>
      </div>
      <p v-if="!plans.length" class="qds-muted">No plans configured.</p>
    </QdsCard>

    <QdsCard v-if="newPlanOpen" padded>
      <div class="qds-card-header">
        <div>
          <h2>Create plan</h2>
          <p>Add a new plan without changing existing subscriptions.</p>
        </div>
      </div>
      <div class="platform-plan-fields">
        <QdsField v-model="newPlanDraft.code" label="Plan code" />
        <QdsField v-model="newPlanDraft.name" label="Plan name" />
        <QdsField v-model="newPlanDraft.price_amount" label="Price" type="number" />
        <QdsField v-model="newPlanDraft.currency" label="Currency" />
        <QdsField v-model="newPlanDraft.billing_interval" label="Billing interval" :options="billingIntervalOptions" />
        <QdsField v-model="newPlanDraft.provider_plan_code" label="Paystack plan code" />
        <QdsField v-model="newPlanDraft.sort_order" label="Display order" type="number" />
        <QdsField v-model="newPlanDraft.description" label="Description" textarea :rows="2" />
        <QdsField v-model="newPlanDraft.features" label="Features (comma separated)" textarea :rows="2" />
      </div>
      <div class="platform-plan-limits">
        <span class="platform-plan-limits-title">Usage limits (blank means unlimited)</span>
        <QdsField v-model="newPlanDraft.limits.members" label="Members" type="number" />
        <QdsField v-model="newPlanDraft.limits.forms" label="Forms" type="number" />
        <QdsField v-model="newPlanDraft.limits.exercises" label="Exercises" type="number" />
        <QdsField v-model="newPlanDraft.limits.assignments" label="Assignments" type="number" />
        <QdsField v-model="newPlanDraft.limits.submissions" label="Submissions / month" type="number" />
      </div>
      <QdsButton icon="save" @click="createPlan">Create plan</QdsButton>
    </QdsCard>

    <div class="platform-summary-grid">
      <QdsCard v-for="stat in summary" :key="stat.label" padded>
        <span class="platform-stat-label">{{ stat.label }}</span>
        <strong class="platform-stat-value">{{ stat.value }}</strong>
      </QdsCard>
    </div>

    <QdsCard padded>
      <div class="qds-card-header">
        <div>
          <h2>Workspaces</h2>
          <p>Suspend access when a workspace needs platform review. Impersonation creates a time-limited support session.</p>
        </div>
      </div>
      <div class="platform-table">
        <div class="platform-table-head"><span>Workspace</span><span>Owner</span><span>Usage</span><span>Status</span><span>Actions</span></div>
        <div v-for="workspace in workspaces" :key="workspace.id" class="platform-table-row">
          <span class="platform-primary">{{ workspace.name }}<small>{{ workspace.slug }} · {{ workspace.type }}</small></span>
          <span>{{ workspace.owner?.name || 'No owner' }}<small>{{ workspace.owner?.email || '' }}</small></span>
          <span>{{ workspace.users_count || 0 }} users · {{ workspace.forms_count || 0 }} forms · {{ workspace.submissions_count || 0 }} submissions</span>
          <QdsField v-model="statusDrafts[workspace.id]" label="" :options="statusOptions" @change="saveStatus(workspace)" />
          <QdsButton variant="tiny" :disabled="!workspace.owner" @click="impersonate(workspace)">Support as owner</QdsButton>
        </div>
      </div>
      <p v-if="!workspaces.length" class="qds-muted">No workspaces found.</p>
    </QdsCard>

    <QdsCard padded>
      <div class="qds-card-header">
        <div>
          <h2>Platform users</h2>
          <p>{{ users.length }} users across all workspaces.</p>
        </div>
      </div>
      <div class="platform-users-list">
        <div v-for="user in users" :key="user.id" class="platform-user-row">
          <span class="platform-primary">{{ user.name }}<small>{{ user.email }}</small></span>
          <span>{{ user.role?.name || 'Portal user' }}</span>
          <span>{{ user.workspaces?.length || 0 }} workspace(s)</span>
          <QdsField v-model="userStatusDrafts[user.id]" label="" :options="userStatusOptions" @change="saveUserStatus(user)" />
        </div>
      </div>
    </QdsCard>

    <QdsCard padded>
      <div class="qds-card-header">
        <div>
          <h2>Audit activity</h2>
          <p>Recent platform actions are retained for support and accountability.</p>
        </div>
      </div>
      <div v-if="auditLogs.length" class="platform-audit-list">
        <div v-for="log in auditLogs" :key="log.id" class="platform-audit-row">
          <strong>{{ log.action.replaceAll('_', ' ') }}</strong>
          <span>{{ log.workspace?.name || 'Platform' }}</span>
          <span>{{ log.actor?.name || 'System' }}</span>
          <small>{{ formatDate(log.created_at) }}</small>
        </div>
      </div>
      <p v-else class="qds-muted">No platform activity recorded yet.</p>
    </QdsCard>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppPageHeader from '../components/layout/AppPageHeader.vue';
import QdsButton from '../components/qds/QdsButton.vue';
import QdsCard from '../components/qds/QdsCard.vue';
import QdsField from '../components/qds/QdsField.vue';
import { impersonateOdkWorkspaceOwner, type OdkUser } from '../services/auth';
import { titleCase } from '../services/teamAccess';
import { archivePlatformPlan, createPlatformPlan, loadPlatformAuditLogs, loadPlatformPlanHistory, loadPlatformPlans, loadPlatformUsers, loadPlatformWorkspaces, restorePlatformPlan, updatePlatformPlan, updatePlatformUserStatus, updatePlatformWorkspaceStatus, type PlatformAuditLog, type PlatformPlan, type PlatformPlanHistory, type PlatformUser, type PlatformWorkspace } from '../services/platform';

const emit = defineEmits<{ 'session-updated': [user: OdkUser, impersonating: boolean, original?: OdkUser | null] }>();
const workspaces = ref<PlatformWorkspace[]>([]);
const users = ref<PlatformUser[]>([]);
const auditLogs = ref<PlatformAuditLog[]>([]);
const plans = ref<PlatformPlan[]>([]);
const statusDrafts = ref<Record<number, string>>({});
const userStatusDrafts = ref<Record<number, string>>({});
type PlanDraft = {
  code?: string;
  name: string;
  description: string;
  price_amount: string;
  currency: string;
  billing_interval: string;
  provider_plan_code: string;
  active: string;
  sort_order: string;
  features: string;
  limits: Record<'members' | 'forms' | 'exercises' | 'assignments' | 'submissions', string>;
};
const planDrafts = ref<Record<number, PlanDraft>>({});
const newPlanOpen = ref(false);
const planHistory = ref<Record<number, PlatformPlanHistory[]>>({});
const historyOpen = ref<Record<number, boolean>>({});
const notice = ref('');
const errorMessage = ref('');
const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Suspended', value: 'suspended' },
  { label: 'Archived', value: 'archived' },
];
const userStatusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
  { label: 'Suspended', value: 'suspended' },
];
const billingIntervalOptions = [
  { label: 'Monthly', value: 'monthly' },
  { label: 'Yearly', value: 'yearly' },
];
const planStatusOptions = [
  { label: 'Active', value: 'true' },
  { label: 'Hidden', value: 'false' },
];
const emptyPlanDraft = (): PlanDraft => ({
  name: '',
  description: '',
  price_amount: '0',
  currency: 'NGN',
  billing_interval: 'monthly',
  provider_plan_code: '',
  active: 'true',
  sort_order: '10',
  features: '',
  limits: { members: '', forms: '', exercises: '', assignments: '', submissions: '' },
});
const newPlanDraft = ref<PlanDraft & { code: string }>({ ...emptyPlanDraft(), code: '' });
const summary = computed(() => [
  { label: 'Workspaces', value: workspaces.value.length },
  { label: 'Active workspaces', value: workspaces.value.filter((item) => item.status === 'active').length },
  { label: 'Platform users', value: users.value.length },
  { label: 'Active plans', value: plans.value.filter((item) => item.active).length },
]);

const hydratePlanDraft = (plan: PlatformPlan) => {
  planDrafts.value[plan.id] = {
    name: plan.name,
    description: plan.description || '',
    price_amount: String(plan.price_amount),
    currency: plan.currency,
    billing_interval: plan.billing_interval,
    provider_plan_code: plan.provider_plan_code || '',
    active: plan.active ? 'true' : 'false',
    sort_order: String(plan.sort_order),
    features: (plan.features || []).join(', '),
    limits: {
      members: plan.limits?.members == null ? '' : String(plan.limits.members),
      forms: plan.limits?.forms == null ? '' : String(plan.limits.forms),
      exercises: plan.limits?.exercises == null ? '' : String(plan.limits.exercises),
      assignments: plan.limits?.assignments == null ? '' : String(plan.limits.assignments),
      submissions: plan.limits?.submissions == null ? '' : String(plan.limits.submissions),
    },
  };
};

const loadData = async () => {
  errorMessage.value = '';
  try {
    const [loadedWorkspaces, loadedUsers, loadedAuditLogs, loadedPlans] = await Promise.all([loadPlatformWorkspaces(), loadPlatformUsers(), loadPlatformAuditLogs(), loadPlatformPlans()]);
    workspaces.value = loadedWorkspaces;
    users.value = loadedUsers;
    auditLogs.value = loadedAuditLogs;
    plans.value = loadedPlans;
    statusDrafts.value = Object.fromEntries(loadedWorkspaces.map((workspace) => [workspace.id, workspace.status]));
    userStatusDrafts.value = Object.fromEntries(loadedUsers.map((user) => [user.id, user.status]));
    loadedPlans.forEach(hydratePlanDraft);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load platform administration.';
  }
};

const formatMoney = (amount: string | number, currency: string) => new Intl.NumberFormat(undefined, {
  style: 'currency',
  currency,
  maximumFractionDigits: 2,
}).format(Number(amount || 0));

const draftPayload = (draft: PlanDraft) => {
  const toLimit = (value: string) => value.trim() === '' ? null : Number(value);

  return {
    name: draft.name,
    description: draft.description || null,
    price_amount: Number(draft.price_amount || 0),
    currency: draft.currency.toUpperCase(),
    billing_interval: draft.billing_interval,
    provider_plan_code: draft.provider_plan_code.trim() || null,
    limits: {
      members: toLimit(draft.limits.members),
      forms: toLimit(draft.limits.forms),
      exercises: toLimit(draft.limits.exercises),
      assignments: toLimit(draft.limits.assignments),
      submissions: toLimit(draft.limits.submissions),
    },
    features: draft.features.split(',').map((feature) => feature.trim()).filter(Boolean),
    active: draft.active === 'true',
    sort_order: Number(draft.sort_order || 0),
  };
};

const savePlan = async (plan: PlatformPlan) => {
  const draft = planDrafts.value[plan.id];

  try {
    const updated = await updatePlatformPlan(plan.id, draftPayload(draft));
    const index = plans.value.findIndex((item) => item.id === plan.id);
    if (index !== -1) plans.value[index] = updated;
    hydratePlanDraft(updated);
    delete planHistory.value[plan.id];
    notice.value = `${updated.name} plan updated.`;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to update plan.';
  }
};

const createPlan = async () => {
  try {
    const created = await createPlatformPlan({ code: newPlanDraft.value.code, ...draftPayload(newPlanDraft.value) });
    plans.value = [...plans.value, created].sort((left, right) => left.sort_order - right.sort_order || left.id - right.id);
    hydratePlanDraft(created);
    newPlanDraft.value = { ...emptyPlanDraft(), code: '' };
    newPlanOpen.value = false;
    notice.value = `${created.name} plan created.`;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to create plan.';
  }
};

const togglePlanArchive = async (plan: PlatformPlan) => {
  try {
    const updated = plan.active ? await archivePlatformPlan(plan.id) : await restorePlatformPlan(plan.id);
    const index = plans.value.findIndex((item) => item.id === plan.id);
    if (index !== -1) plans.value[index] = updated;
    hydratePlanDraft(updated);
    delete planHistory.value[plan.id];
    notice.value = `${updated.name} plan ${updated.active ? 'restored' : 'archived'}.`;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to change plan availability.';
  }
};

const togglePlanHistory = async (plan: PlatformPlan) => {
  historyOpen.value[plan.id] = !historyOpen.value[plan.id];
  if (!historyOpen.value[plan.id] || planHistory.value[plan.id]) return;

  try {
    planHistory.value[plan.id] = await loadPlatformPlanHistory(plan.id);
  } catch (error) {
    historyOpen.value[plan.id] = false;
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load plan history.';
  }
};

const formatDate = (value: string) => new Date(value).toLocaleString();

const saveUserStatus = async (user: PlatformUser) => {
  try {
    const updated = await updatePlatformUserStatus(user.id, userStatusDrafts.value[user.id]);
    user.status = updated.status;
    notice.value = `${user.name} is now ${user.status}.`;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to update user status.';
  }
};

const saveStatus = async (workspace: PlatformWorkspace) => {
  try {
    const updated = await updatePlatformWorkspaceStatus(workspace.id, statusDrafts.value[workspace.id]);
    workspace.status = updated.status;
    notice.value = `${workspace.name} is now ${workspace.status}.`;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to update workspace status.';
  }
};

const impersonate = async (workspace: PlatformWorkspace) => {
  try {
    const result = await impersonateOdkWorkspaceOwner(workspace.id);
    emit('session-updated', result.user, true, result.original_user);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to start support session.';
  }
};

onMounted(loadData);
</script>

<style scoped>
.platform-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.platform-stat-label { display: block; color: #617169; font-size: 12px; font-weight: 700; }
.platform-stat-value { display: block; margin-top: 8px; color: #064231; font-size: 28px; }
.platform-table, .platform-users-list { display: grid; }
.platform-table-head, .platform-table-row, .platform-user-row { display: grid; grid-template-columns: 1.25fr 1.15fr 1.3fr 0.8fr 0.85fr; gap: 14px; align-items: center; padding: 13px 0; border-bottom: 1px solid #e1e8e2; font-size: 13px; }
.platform-table-head { color: #617169; font-size: 11px; font-weight: 900; text-transform: uppercase; }
.platform-primary { color: #0b2d22; font-weight: 800; }
.platform-primary small, .platform-table-row small { display: block; margin-top: 4px; color: #718079; font-size: 11px; font-weight: 500; }
.platform-user-row { grid-template-columns: 1.5fr 1fr 1fr 1fr; }
.platform-user-row :deep(.qds-field) { min-width: 120px; }
.platform-table-row :deep(.qds-field) { min-width: 120px; }
.platform-audit-list { display: grid; }
.platform-audit-row { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 14px; align-items: center; padding: 12px 0; border-bottom: 1px solid #e1e8e2; font-size: 13px; }
.platform-audit-row strong { color: #0b2d22; text-transform: capitalize; }
.platform-audit-row span, .platform-audit-row small { color: #617169; }
.platform-plan-list { display: grid; gap: 18px; }
.platform-plan-row { display: grid; gap: 14px; padding: 18px 0; border-bottom: 1px solid #e1e8e2; }
.platform-plan-row:last-child { border-bottom: 0; padding-bottom: 0; }
.platform-plan-heading strong { display: block; color: #0b2d22; font-size: 17px; }
.platform-plan-heading small { display: block; margin-top: 4px; color: #718079; font-size: 11px; }
.platform-plan-fields { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; }
.platform-plan-fields :deep(.qds-field:nth-child(8)), .platform-plan-fields :deep(.qds-field:nth-child(9)) { grid-column: span 3; }
.platform-plan-limits { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
.platform-plan-limits-title { grid-column: 1 / -1; color: #617169; font-size: 11px; font-weight: 800; text-transform: uppercase; }
.platform-plan-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.platform-plan-preview { color: #0b2d22; font-size: 13px; font-weight: 800; }
.platform-plan-history { display: grid; gap: 8px; padding-top: 12px; border-top: 1px solid #e1e8e2; }
.platform-plan-history-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; color: #617169; font-size: 12px; }
.platform-plan-history-row strong { color: #0b2d22; }
@media (max-width: 950px) { .platform-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .platform-table-head { display: none; } .platform-table-row, .platform-user-row { grid-template-columns: 1fr 1fr; } .platform-plan-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); } .platform-plan-fields :deep(.qds-field:nth-child(8)), .platform-plan-fields :deep(.qds-field:nth-child(9)) { grid-column: span 2; } .platform-plan-limits { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) { .platform-summary-grid { grid-template-columns: 1fr; } .platform-table-row, .platform-user-row, .platform-audit-row { grid-template-columns: 1fr; gap: 8px; } .platform-plan-fields, .platform-plan-limits { grid-template-columns: 1fr; } .platform-plan-fields :deep(.qds-field:nth-child(8)), .platform-plan-fields :deep(.qds-field:nth-child(9)) { grid-column: auto; } .platform-plan-actions { align-items: stretch; flex-direction: column; } .platform-plan-history-row { grid-template-columns: 1fr; gap: 4px; } }
</style>
