<template>
  <section class="screen-stack workspace-settings-screen">
    <AppPageHeader title="Workspace settings" subtitle="Manage workspace identity, members, and invitations.">
      <template #actions>
        <QdsButton variant="secondary" @click="loadData">Refresh</QdsButton>
      </template>
    </AppPageHeader>

    <p v-if="notice" class="builder-notice">{{ notice }}</p>
    <p v-if="errorMessage" class="auth-error">{{ errorMessage }}</p>

    <QdsCard v-if="subscription" padded class="subscription-panel">
      <div class="qds-card-header">
        <div>
          <h2>{{ subscription.plan.name }} plan</h2>
          <p>{{ subscription.plan.description }}</p>
        </div>
        <span class="subscription-status">{{ subscription.subscription.status }}</span>
      </div>
      <div class="subscription-usage-grid">
        <div v-for="item in subscription.usage" :key="item.metric" class="subscription-usage-item">
          <div class="subscription-usage-label">
            <span>{{ metricLabel(item.metric) }}</span>
            <strong>{{ item.used }}<small v-if="!item.unlimited"> / {{ item.limit }}</small></strong>
          </div>
          <div class="subscription-usage-track">
            <span :style="{ width: `${usagePercent(item)}%` }"></span>
          </div>
        </div>
      </div>
      <div class="subscription-actions">
        <QdsButton
          v-if="subscription.subscription.cancel_at_period_end"
          variant="secondary"
          @click="resumeSubscription"
        >Resume subscription</QdsButton>
        <QdsButton
          v-else-if="subscription.plan.code !== 'free'"
          variant="secondary"
          @click="cancelSubscription"
        >Cancel at period end</QdsButton>
      </div>
    </QdsCard>

    <QdsCard v-if="plans.length" padded class="billing-panel">
      <div class="qds-card-header">
        <div>
          <h2>Plans</h2>
          <p>Upgrade the workspace when your team needs more capacity.</p>
        </div>
      </div>
      <div class="billing-plan-list">
        <div v-for="plan in plans" :key="plan.code" class="billing-plan-row">
          <div>
            <strong>{{ plan.name }}</strong>
            <small>{{ plan.description }}</small>
          </div>
          <span class="billing-plan-price">{{ formatMoney(plan.price_amount, plan.currency) }} / {{ plan.billing_interval }}</span>
          <QdsButton
            v-if="subscription && plan.code !== subscription.plan.code && Number(plan.price_amount) > Number(subscription.plan.price_amount)"
            icon="arrow-right"
            @click="upgrade(plan.code)"
          >Upgrade</QdsButton>
          <span v-else-if="subscription?.plan.code === plan.code" class="workspace-owner">Current plan</span>
        </div>
      </div>
    </QdsCard>

    <QdsCard v-if="invoices.length" padded class="billing-panel">
      <div class="qds-card-header">
        <div>
          <h2>Billing history</h2>
          <p>Recent workspace invoices and payment status.</p>
        </div>
      </div>
      <div class="billing-invoice-list">
        <div v-for="invoice in invoices" :key="invoice.uuid" class="billing-invoice-row">
          <span><strong>{{ invoice.plan?.name || 'Workspace plan' }}</strong><small>{{ invoice.reference }} Â· {{ formatDate(invoice.created_at) }}</small></span>
          <span>{{ formatMoney(invoice.amount, invoice.currency) }}</span>
          <span class="billing-invoice-status" :data-status="invoice.status">{{ invoice.status }}</span>
        </div>
      </div>
    </QdsCard>

    <div class="workspace-settings-grid">
      <QdsCard class="workspace-panel">
        <div class="qds-card-header">
          <div>
            <h2>Workspace profile</h2>
            <p>Use a clear name so your team can identify the active workspace.</p>
          </div>
        </div>
        <div class="workspace-form">
          <QdsField v-model="workspaceForm.name" label="Workspace name" />
          <QdsField v-model="workspaceForm.slug" label="Workspace slug" />
          <div class="workspace-readonly-field">
            <span>Account type</span>
            <strong>{{ workspaceTypeLabel }}</strong>
          </div>
        </div>
        <QdsButton icon="save" @click="saveWorkspace">Save workspace</QdsButton>
      </QdsCard>

      <QdsCard class="workspace-panel">
        <div class="qds-card-header">
          <div>
            <h2>Invite a member</h2>
            <p>Invite an existing DataKit user to join this workspace.</p>
          </div>
        </div>
        <div class="workspace-form workspace-invite-form">
          <QdsField v-model="inviteEmail" label="Email address" type="email" />
          <QdsField v-model="inviteRole" label="Workspace role" :options="roleOptions" />
        </div>
        <QdsButton icon="plus" @click="inviteMember">Create invitation</QdsButton>
        <div v-if="latestInviteUrl" class="invite-result">
          <strong>Invitation link</strong>
          <p v-if="inviteEmailSent" class="invite-status invite-status-success">Invitation email sent to the invited person.</p>
          <p v-else class="invite-status invite-status-warning">Email delivery failed. Share this link manually.</p>
          <p>This link expires in seven days.</p>
          <code>{{ latestInviteUrl }}</code>
        </div>
      </QdsCard>
    </div>

    <QdsCard class="workspace-panel">
      <div class="qds-card-header">
        <div>
          <h2>Members</h2>
          <p>{{ members.length }} active workspace members</p>
        </div>
      </div>
      <div class="qds-table workspace-members-table">
        <div class="qds-table__head">
          <span>Member</span>
          <span>Workspace role</span>
          <span>Portal role</span>
          <span>Action</span>
        </div>
        <div v-for="member in members" :key="member.id" class="qds-table__row">
          <span class="table-title">{{ member.name }}<small>{{ member.email }}</small></span>
          <QdsField
            v-if="member.pivot?.role_key !== 'owner'"
            v-model="memberRoles[member.id]"
            label=""
            :options="roleOptions"
          />
          <span v-else class="workspace-owner">Owner</span>
          <span>{{ member.role?.name || 'Portal user' }}</span>
          <span class="row-actions" v-if="member.pivot?.role_key !== 'owner'">
            <QdsButton variant="tiny" @click="saveMemberRole(member)">Save role</QdsButton>
            <QdsButton variant="tiny" @click="removeMember(member)">Remove</QdsButton>
          </span>
          <span v-else class="workspace-owner">Workspace owner</span>
        </div>
      </div>
    </QdsCard>

    <QdsCard class="workspace-panel">
      <div class="qds-card-header">
        <div>
          <h2>Pending invitations</h2>
          <p>{{ invitations.length }} invitations waiting for acceptance</p>
        </div>
      </div>
      <div v-if="invitations.length" class="workspace-invitations">
        <div v-for="invitation in invitations" :key="invitation.uuid" class="workspace-invitation">
          <span><strong>{{ invitation.email }}</strong><small>{{ invitation.role_key }} · expires {{ formatDate(invitation.expires_at) }}</small></span>
          <QdsButton variant="tiny" @click="revokeInvitation(invitation)">Revoke</QdsButton>
        </div>
      </div>
      <p v-else class="qds-muted">No pending invitations.</p>
    </QdsCard>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import AppPageHeader from '../components/layout/AppPageHeader.vue';
import QdsButton from '../components/qds/QdsButton.vue';
import QdsCard from '../components/qds/QdsCard.vue';
import QdsField from '../components/qds/QdsField.vue';
import {
  inviteWorkspaceMember,
  loadCurrentOdkWorkspace,
  loadWorkspaceInvitations,
  loadWorkspaceMembers,
  removeWorkspaceMember,
  revokeWorkspaceInvitation,
  updateCurrentOdkWorkspace,
  updateWorkspaceMember,
  type Workspace,
  type WorkspaceInvitation,
  type WorkspaceMember,
} from '../services/workspaces';
import { loadCurrentWorkspaceSubscription, type WorkspaceSubscriptionSnapshot } from '../services/subscription';
import { cancelBillingSubscription, loadBillingInvoices, loadBillingPlans, resumeBillingSubscription, startBillingCheckout, verifyBillingPayment, type BillingInvoice, type BillingPlan } from '../services/billing';

const emit = defineEmits<{
  'workspace-updated': [workspace: Workspace];
}>();

const workspace = ref<Workspace | null>(null);
const members = ref<WorkspaceMember[]>([]);
const invitations = ref<WorkspaceInvitation[]>([]);
const subscription = ref<WorkspaceSubscriptionSnapshot | null>(null);
const plans = ref<BillingPlan[]>([]);
const invoices = ref<BillingInvoice[]>([]);
const workspaceForm = reactive({ name: '', slug: '' });
const memberRoles = ref<Record<number, string>>({});
const inviteEmail = ref('');
const inviteRole = ref<'admin' | 'member'>('member');
const latestInviteUrl = ref('');
const inviteEmailSent = ref(false);
const notice = ref('');
const errorMessage = ref('');
const roleOptions = [
  { label: 'Member', value: 'member' },
  { label: 'Admin', value: 'admin' },
];

const workspaceTypeLabel = computed(() => workspace.value?.type === 'individual' ? 'Individual' : 'Organization');

const loadData = async () => {
  errorMessage.value = '';
  try {
    const [loadedWorkspace, loadedMembers, loadedInvitations, loadedSubscription, loadedPlans, loadedInvoices] = await Promise.all([
      loadCurrentOdkWorkspace(),
      loadWorkspaceMembers(),
      loadWorkspaceInvitations(),
      loadCurrentWorkspaceSubscription(),
      loadBillingPlans(),
      loadBillingInvoices(),
    ]);
    workspace.value = loadedWorkspace;
    subscription.value = loadedSubscription;
    plans.value = loadedPlans;
    invoices.value = loadedInvoices;
    workspaceForm.name = loadedWorkspace.name;
    workspaceForm.slug = loadedWorkspace.slug;
    members.value = loadedMembers;
    invitations.value = loadedInvitations;
    memberRoles.value = Object.fromEntries(loadedMembers.map((member) => [member.id, member.pivot?.role_key || 'member']));
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to load workspace settings.';
  }
};

const saveWorkspace = async () => {
  try {
    const updated = await updateCurrentOdkWorkspace(workspaceForm);
    workspace.value = updated;
    notice.value = 'Workspace settings saved.';
    emit('workspace-updated', updated);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to save workspace settings.';
  }
};

const inviteMember = async () => {
  try {
    const result = await inviteWorkspaceMember(inviteEmail.value.trim(), inviteRole.value);
    latestInviteUrl.value = result.invite_url;
    inviteEmailSent.value = result.email_sent;
    inviteEmail.value = '';
    notice.value = result.email_sent
      ? 'Invitation email sent.'
      : 'Invitation created, but the email could not be sent. Share the link manually.';
    invitations.value = [result.invitation, ...invitations.value];
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to create invitation.';
  }
};

const saveMemberRole = async (member: WorkspaceMember) => {
  try {
    await updateWorkspaceMember(member.id, (memberRoles.value[member.id] || 'member') as 'admin' | 'member');
    notice.value = `${member.name}'s workspace role was updated.`;
    await loadData();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to update member role.';
  }
};

const removeMember = async (member: WorkspaceMember) => {
  if (!window.confirm(`Remove ${member.name} from this workspace?`)) return;
  try {
    await removeWorkspaceMember(member.id);
    members.value = members.value.filter((item) => item.id !== member.id);
    notice.value = `${member.name} was removed from the workspace.`;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to remove member.';
  }
};

const revokeInvitation = async (invitation: WorkspaceInvitation) => {
  try {
    await revokeWorkspaceInvitation(invitation.uuid);
    invitations.value = invitations.value.filter((item) => item.uuid !== invitation.uuid);
    notice.value = 'Invitation revoked.';
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to revoke invitation.';
  }
};

const upgrade = async (planCode: string) => {
  try {
    const result = await startBillingCheckout(planCode);
    const authorizationUrl = result.checkout.authorization_url;
    if (!authorizationUrl) throw new Error('The payment provider did not return a checkout URL.');
    window.location.assign(authorizationUrl);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to start checkout.';
  }
};

const verifyPaymentReturn = async () => {
  const query = new URLSearchParams(window.location.search);
  const reference = query.get('billing_reference') || query.get('reference');
  if (!reference) return;

  try {
    await verifyBillingPayment(reference);
    notice.value = 'Payment verified. Your workspace plan is now active.';
    await loadData();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to verify this payment.';
  } finally {
    query.delete('billing_reference');
    query.delete('reference');
    const cleanPath = `${window.location.pathname}${query.toString() ? `?${query.toString()}` : ''}`;
    window.history.replaceState({}, '', cleanPath);
  }
};

const cancelSubscription = async () => {
  try {
    await cancelBillingSubscription();
    notice.value = 'Subscription cancellation scheduled for the end of the current period.';
    await loadData();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to cancel the subscription.';
  }
};

const resumeSubscription = async () => {
  try {
    await resumeBillingSubscription();
    notice.value = 'Subscription renewal resumed.';
    await loadData();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'Unable to resume the subscription.';
  }
};

const formatDate = (value: string) => new Date(value).toLocaleDateString();
const formatMoney = (value: string | number, currency: string) => new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value));
const metricLabel = (metric: string) => metric.replace('_', ' ').replace(/^./, (character) => character.toUpperCase());
const usagePercent = (item: { used: number; limit: number | null; unlimited: boolean }) => {
  if (item.unlimited || !item.limit) return item.used > 0 ? 24 : 0;
  return Math.min(100, Math.max(0, Math.round((item.used / item.limit) * 100)));
};

onMounted(async () => {
  await loadData();
  await verifyPaymentReturn();
});
</script>

<style scoped>
.workspace-settings-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.subscription-panel {
  display: grid;
  gap: 16px;
}

.subscription-actions {
  display: flex;
  justify-content: flex-end;
}

.billing-panel {
  display: grid;
  gap: 14px;
}

.billing-plan-list,
.billing-invoice-list {
  display: grid;
}

.billing-plan-row,
.billing-invoice-row {
  display: grid;
  grid-template-columns: 1.4fr 1fr auto;
  gap: 14px;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #e1e8e2;
}

.billing-plan-row strong,
.billing-plan-row small,
.billing-invoice-row strong,
.billing-invoice-row small {
  display: block;
}

.billing-plan-row small,
.billing-invoice-row small {
  margin-top: 4px;
  color: #718079;
  font-size: 11px;
}

.billing-plan-price {
  color: #064231;
  font-size: 13px;
  font-weight: 800;
}

.billing-invoice-status {
  color: #617169;
  font-size: 12px;
  font-weight: 800;
  text-transform: capitalize;
}

.billing-invoice-status[data-status="paid"] {
  color: #087a4a;
}

.billing-invoice-status[data-status="failed"] {
  color: #b42318;
}

.subscription-status {
  padding: 5px 10px;
  border-radius: 999px;
  color: #087a4a;
  background: #e0f5e9;
  font-size: 11px;
  font-weight: 800;
  text-transform: capitalize;
}

.subscription-usage-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 14px;
}

.subscription-usage-item {
  display: grid;
  gap: 8px;
}

.subscription-usage-label {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  color: #617169;
  font-size: 12px;
  font-weight: 700;
  text-transform: capitalize;
}

.subscription-usage-label strong {
  color: #064231;
  font-size: 16px;
}

.subscription-usage-label small {
  color: #718079;
  font-size: 11px;
  font-weight: 600;
}

.subscription-usage-track {
  height: 7px;
  overflow: hidden;
  border-radius: 999px;
  background: #e5ece8;
}

.subscription-usage-track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #087a4a;
}

.workspace-panel {
  min-width: 0;
  padding: 16px;
}

.workspace-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}

.workspace-form :deep(.qds-field:last-child) {
  grid-column: 1 / -1;
}

.workspace-invite-form :deep(.qds-field:last-child) {
  grid-column: auto;
}

.workspace-readonly-field {
  display: grid;
  gap: 6px;
  color: #475467;
  font-size: 12px;
  font-weight: 500;
}

.workspace-readonly-field strong {
  display: flex;
  align-items: center;
  min-height: 32px;
  padding: 0 10px;
  border: 1px solid #d7e0e7;
  border-radius: 6px;
  color: #06152a;
  background: #f8fafc;
  font-size: 12px;
  font-weight: 600;
}

.invite-result {
  display: grid;
  gap: 5px;
  margin-top: 18px;
  padding: 12px;
  border: 1px solid #b7dec6;
  border-radius: 7px;
  background: #edf8f0;
}

.invite-result p,
.workspace-invitation small {
  margin: 0;
  color: #617169;
  font-size: 12px;
}

.invite-result .invite-status {
  font-weight: 700;
}

.invite-status-success {
  color: #087a4a !important;
}

.invite-status-warning {
  color: #a15c00 !important;
}

.invite-result code {
  overflow-wrap: anywhere;
  color: #0e5a3a;
  font-size: 11px;
}

.workspace-members-table :deep(.qds-field) {
  min-width: 140px;
}

.workspace-members-table :deep(.qds-table__head),
.workspace-members-table :deep(.qds-table__row) {
  min-width: 0;
  grid-template-columns: minmax(220px, 1.5fr) minmax(150px, .9fr) minmax(170px, 1fr) minmax(210px, 1.2fr);
}

.workspace-members-table :deep(.qds-table__head) {
  padding: 0 12px;
}

.workspace-members-table :deep(.qds-table__row) {
  padding: 10px 12px;
}

.workspace-owner {
  color: #087a4a;
  font-size: 12px;
  font-weight: 800;
}

.workspace-invitations {
  display: grid;
  gap: 10px;
}

.workspace-invitation {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  padding: 12px 0;
  border-bottom: 1px solid #e1e8e2;
}

.workspace-invitation span {
  display: grid;
  gap: 5px;
}

@media (max-width: 900px) {
  .subscription-usage-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .workspace-settings-grid {
    grid-template-columns: 1fr;
  }

  .workspace-members-table {
    overflow-x: auto;
  }

  .workspace-members-table :deep(.qds-table__head),
  .workspace-members-table :deep(.qds-table__row) {
    min-width: 760px;
  }

  .billing-plan-row,
  .billing-invoice-row {
    grid-template-columns: 1fr auto;
  }

  .billing-plan-price {
    grid-column: 1;
  }

  .billing-plan-row :deep(.qds-button) {
    grid-column: 2;
    grid-row: 1 / span 2;
  }
}

@media (max-width: 640px) {
  .subscription-usage-grid {
    grid-template-columns: 1fr;
  }

  .workspace-panel {
    padding: 13px;
  }

  .workspace-form {
    grid-template-columns: 1fr;
  }

  .workspace-form :deep(.qds-field:last-child),
  .workspace-invite-form :deep(.qds-field:last-child) {
    grid-column: auto;
  }
}
</style>
