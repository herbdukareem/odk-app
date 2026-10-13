<template>
  <section class="screen-stack">
    <AppPageHeader title="Team & access" subtitle="Manage ODK portal users, roles, and assessment teams">
      <template #actions>
        <div class="action-row">
          <QdsButton v-if="activeTab === 'users'" icon="plus" @click="openUserModal">New user</QdsButton>
          <QdsButton v-if="activeTab === 'teams'" icon="plus" @click="openTeamModal()">New team</QdsButton>
          <QdsButton variant="secondary" @click="loadAccess">Refresh</QdsButton>
        </div>
      </template>
    </AppPageHeader>

    <p v-if="notice" class="builder-notice">{{ notice }}</p>
    <QdsTabs v-model="activeTab" :tabs="tabs" wide />

    <QdsCard v-if="activeTab === 'users'" class="team-access-panel">
      <div class="qds-card-header">
        <div>
          <h2>Users</h2>
          <p>{{ users.length }} people configured for ODK access</p>
        </div>
      </div>

      <div class="qds-table team-users-table">
        <div class="qds-table__head">
          <span>Name</span>
          <span>Role</span>
          <span>Coverage</span>
          <span>Status</span>
          <span>Actions</span>
        </div>
        <div v-for="user in users" :key="user.id" class="qds-table__row">
          <span class="table-title">{{ user.name }}<small>{{ user.email }}</small></span>
          <span>{{ user.role?.name ?? 'No role' }}<small>{{ user.designation ?? 'No designation' }}</small></span>
          <span>{{ user.assigned_lga || 'All LGAs' }}<small>{{ user.assigned_ward || 'All wards' }}</small></span>
          <QdsBadge :tone="statusTone(user.status)">{{ titleCase(user.status) }}</QdsBadge>
          <span class="row-actions">
            <QdsButton variant="tiny" icon="pencil" @click="openUserModal(user)">Edit</QdsButton>
            <QdsButton variant="tiny" icon="lock" @click="openPasswordModal(user)">Reset password</QdsButton>
            <QdsButton variant="tiny" icon="switch" @click="impersonateUser(user)">Impersonate</QdsButton>
          </span>
        </div>
      </div>
    </QdsCard>

    <QdsCard v-else-if="activeTab === 'teams'" class="team-access-panel">
      <div class="qds-card-header">
        <div>
          <h2>Teams</h2>
          <p>{{ teams.length }} operational assessment teams</p>
        </div>
      </div>
      <div class="qds-table team-teams-table">
        <div class="qds-table__head">
          <span>Team</span>
          <span>Lead</span>
          <span>Coverage</span>
          <span>Members</span>
          <span>Status</span>
          <span>Action</span>
        </div>
        <div v-for="team in teams" :key="team.id" class="qds-table__row">
          <span class="table-title">{{ team.name }}<small>{{ team.description || 'No description' }}</small></span>
          <span>{{ team.lead?.name ?? 'Not assigned' }}<small>{{ team.lead?.email ?? 'No lead email' }}</small></span>
          <span>{{ team.assigned_lga || 'All LGAs' }}<small>Exercise-ready</small></span>
          <span>{{ team.members_count ?? team.members?.length ?? 0 }}</span>
          <QdsBadge :tone="statusTone(team.status)">{{ titleCase(team.status) }}</QdsBadge>
          <span class="row-actions">
            <QdsButton variant="tiny" @click="openTeamModal(team)">Edit</QdsButton>
          </span>
        </div>
      </div>
    </QdsCard>

    <QdsCard v-else class="team-access-panel">
      <div class="qds-card-header">
        <div>
          <h2>Roles</h2>
          <p>Foundation roles for upcoming exercises, assignments, and review permissions.</p>
        </div>
      </div>
      <div class="role-strip">
        <div v-for="role in roles" :key="role.key" class="role-card">
          <strong>{{ role.name }}</strong>
          <span>{{ role.description }}</span>
        </div>
      </div>
    </QdsCard>

    <Teleport to="body">
      <div v-if="showUserModal" class="field-editor-modal" @click.self="closeUserModal">
        <div class="field-editor field-editor-modal__panel team-modal-panel" role="dialog" aria-modal="true" @click.stop>
          <div class="qds-card-header">
            <div>
              <h2>{{ editingUserId ? 'Edit user' : 'Create user' }}</h2>
              <p>{{ editingUserId ? 'Update profile, access, and coverage details.' : 'Email and password are required. Profile details can be completed later.' }}</p>
            </div>
            <QdsButton variant="tiny" @click="closeUserModal">Close</QdsButton>
          </div>
          <div class="team-form-grid">
            <QdsField v-model="userDraft.email" label="Email address" type="email" />
            <QdsField v-if="!editingUserId" v-model="userDraft.password" label="Password" type="password" />
            <QdsField v-model="userDraft.name" label="Full name" />
            <QdsField v-model="userDraft.role_key" label="Role" :options="roleOptions" />
            <QdsField v-model="userDraft.phone" label="Phone number" type="tel" />
            <QdsField v-model="userDraft.staff_code" label="Staff code" />
            <QdsField v-model="userDraft.designation" label="Designation" />
            <QdsField v-model="userDraft.assigned_lga" label="Assigned LGA" />
            <QdsField v-model="userDraft.assigned_ward" label="Assigned ward" />
            <QdsField v-model="userDraft.status" label="Status" :options="userStatusOptions" />
          </div>
          <QdsButton :icon="editingUserId ? 'save' : 'plus'" block @click="saveUser">{{ editingUserId ? 'Save changes' : 'Create user' }}</QdsButton>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="showPasswordModal" class="field-editor-modal" @click.self="closePasswordModal">
        <div class="field-editor field-editor-modal__panel team-modal-panel" role="dialog" aria-modal="true" @click.stop>
          <div class="qds-card-header">
            <div>
              <h2>Reset password</h2>
              <p>Set a new password for {{ passwordTarget?.name || passwordTarget?.email }}. Existing sessions will be signed out.</p>
            </div>
            <QdsButton variant="tiny" @click="closePasswordModal">Close</QdsButton>
          </div>
          <div class="team-form-grid">
            <QdsField v-model="passwordDraft.password" label="New password" type="password" />
            <QdsField v-model="passwordDraft.password_confirmation" label="Confirm new password" type="password" />
          </div>
          <QdsButton icon="save" block @click="resetPassword">Reset password</QdsButton>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="showTeamModal" class="field-editor-modal" @click.self="closeTeamModal">
        <div class="field-editor field-editor-modal__panel team-modal-panel" role="dialog" aria-modal="true" @click.stop>
          <div class="qds-card-header">
            <div>
              <h2>{{ editingTeamId ? 'Edit team' : 'Create team' }}</h2>
              <p>Assign a team lead and update members for future exercises.</p>
            </div>
            <QdsButton variant="tiny" @click="closeTeamModal">Close</QdsButton>
          </div>
          <div class="team-form-grid">
            <QdsField v-model="teamDraft.name" label="Team name" />
            <QdsField v-model="teamDraft.assigned_lga" label="Assigned LGA" />
            <QdsField v-model="teamDraft.lead_user_id" label="Team lead" :options="userOptions" />
            <QdsField v-model="teamDraft.status" label="Status" :options="statusOptions" />
            <QdsField v-model="teamDraft.description" label="Description" textarea :rows="3" />
          </div>
          <div class="member-picker">
            <h3>Members</h3>
            <label v-for="user in users" :key="user.id" class="member-choice">
              <input
                type="checkbox"
                :checked="teamDraft.member_ids.includes(user.id)"
                @change="toggleMember(user.id)"
              >
              <span>{{ user.name }}</span>
              <small>{{ user.role?.name ?? 'No role' }}</small>
            </label>
          </div>
          <QdsButton icon="save" block @click="saveTeam">{{ editingTeamId ? 'Save team' : 'Create team' }}</QdsButton>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import AppPageHeader from '../components/layout/AppPageHeader.vue';
import QdsBadge from '../components/qds/QdsBadge.vue';
import QdsButton from '../components/qds/QdsButton.vue';
import QdsCard from '../components/qds/QdsCard.vue';
import QdsField from '../components/qds/QdsField.vue';
import QdsTabs from '../components/qds/QdsTabs.vue';
import {
  createOdkTeam,
  createOdkUser,
  loadTeamAccess,
  resetOdkUserPassword,
  type OdkRole,
  type OdkTeam,
  type OdkUser,
  statusTone,
  titleCase,
  updateOdkUser,
  updateOdkTeam,
} from '../services/teamAccess';
import { impersonateOdkUser } from '../services/auth';

const tabs = [
  { label: 'Users', value: 'users' },
  { label: 'Teams', value: 'teams' },
  { label: 'Roles', value: 'roles' },
];
const activeTab = ref('users');
const roles = ref<OdkRole[]>([]);
const users = ref<OdkUser[]>([]);
const teams = ref<OdkTeam[]>([]);
const notice = ref('');
const showUserModal = ref(false);
const showPasswordModal = ref(false);
const showTeamModal = ref(false);
const editingTeamId = ref<number | null>(null);
const editingUserId = ref<number | null>(null);
const passwordTarget = ref<OdkUser | null>(null);
const emit = defineEmits<{
  'session-updated': [user: OdkUser, impersonating: boolean, originalUser?: OdkUser | null];
}>();

const userDraft = reactive({
  name: '',
  email: '',
  password: '',
  phone: '',
  staff_code: '',
  role_key: 'assessor',
  designation: '',
  assigned_lga: '',
  assigned_ward: '',
  status: 'active',
});

const passwordDraft = reactive({
  password: '',
  password_confirmation: '',
});

const teamDraft = reactive({
  name: '',
  description: '',
  lead_user_id: '',
  assigned_lga: '',
  status: 'active',
  member_ids: [] as number[],
});

const roleOptions = computed(() => roles.value.map((role) => ({
  label: role.key === 'admin' ? 'Workspace admin' : role.name,
  value: role.key,
})));
const userOptions = computed(() => [
  { label: 'No team lead', value: '' },
  ...users.value.map((user) => ({ label: user.name, value: String(user.id) })),
]);
const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
];
const userStatusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
  { label: 'Suspended', value: 'suspended' },
];

const loadAccess = async () => {
  const payload = await loadTeamAccess();
  roles.value = payload.roles;
  users.value = payload.users;
  teams.value = payload.teams;
};

const resetUserDraft = () => {
  Object.assign(userDraft, {
    name: '',
    email: '',
    password: '',
    phone: '',
    staff_code: '',
    role_key: 'assessor',
    designation: '',
    assigned_lga: '',
    assigned_ward: '',
    status: 'active',
  });
  editingUserId.value = null;
};

const resetTeamDraft = () => {
  Object.assign(teamDraft, {
    name: '',
    description: '',
    lead_user_id: '',
    assigned_lga: '',
    status: 'active',
    member_ids: [],
  });
  editingTeamId.value = null;
};

const openUserModal = (user?: OdkUser) => {
  resetUserDraft();
  if (user) {
    Object.assign(userDraft, {
      name: user.name ?? '',
      email: user.email,
      phone: user.phone ?? '',
      staff_code: user.staff_code ?? '',
      role_key: user.role?.key ?? 'assessor',
      designation: user.designation ?? '',
      assigned_lga: user.assigned_lga ?? '',
      assigned_ward: user.assigned_ward ?? '',
      status: user.status,
    });
    editingUserId.value = user.id;
  }
  showUserModal.value = true;
};

const closeUserModal = () => {
  showUserModal.value = false;
  resetUserDraft();
};

const openPasswordModal = (user: OdkUser) => {
  passwordTarget.value = user;
  passwordDraft.password = '';
  passwordDraft.password_confirmation = '';
  showPasswordModal.value = true;
};

const closePasswordModal = () => {
  showPasswordModal.value = false;
  passwordTarget.value = null;
  passwordDraft.password = '';
  passwordDraft.password_confirmation = '';
};

const openTeamModal = (team?: OdkTeam) => {
  if (team) {
    Object.assign(teamDraft, {
      name: team.name,
      description: team.description ?? '',
      lead_user_id: team.lead?.id ? String(team.lead.id) : '',
      assigned_lga: team.assigned_lga ?? '',
      status: team.status,
      member_ids: (team.members ?? []).map((member) => member.id),
    });
    editingTeamId.value = team.id;
  } else {
    resetTeamDraft();
  }
  showTeamModal.value = true;
};

const closeTeamModal = () => {
  showTeamModal.value = false;
  resetTeamDraft();
};

const saveUser = async () => {
  if (!userDraft.email.trim() || (!editingUserId.value && !userDraft.password.trim())) {
    notice.value = editingUserId.value ? 'Email is required.' : 'Email and password are required.';
    return;
  }

  const payload = {
    name: userDraft.name.trim() || null,
    email: userDraft.email.trim(),
    ...(!editingUserId.value ? { password: userDraft.password } : {}),
    phone: userDraft.phone.trim() || null,
    staff_code: userDraft.staff_code.trim() || null,
    role_key: userDraft.role_key,
    designation: userDraft.designation.trim() || null,
    assigned_lga: userDraft.assigned_lga.trim() || null,
    assigned_ward: userDraft.assigned_ward.trim() || null,
    status: userDraft.status,
  };

  if (editingUserId.value) {
    await updateOdkUser(editingUserId.value, payload);
    notice.value = 'User updated.';
  } else {
    await createOdkUser(payload);
    notice.value = 'User created.';
  }

  await loadAccess();
  closeUserModal();
};

const resetPassword = async () => {
  if (!passwordTarget.value) return;
  if (passwordDraft.password.length < 8 || passwordDraft.password !== passwordDraft.password_confirmation) {
    notice.value = 'Use matching passwords with at least 8 characters, including upper/lowercase letters, numbers, and symbols.';
    return;
  }

  await resetOdkUserPassword(passwordTarget.value.id, passwordDraft.password, passwordDraft.password_confirmation);
  closePasswordModal();
  notice.value = 'User password reset. Existing sessions were signed out.';
};

const impersonateUser = async (user: OdkUser) => {
  if (!window.confirm(`Impersonate ${user.name || user.email}?`)) return;

  const payload = await impersonateOdkUser(user.id);
  emit('session-updated', payload.user, true, payload.original_user);
  notice.value = `You are now viewing the portal as ${payload.user.name || payload.user.email}.`;
};

const saveTeam = async () => {
  if (!teamDraft.name.trim()) {
    notice.value = 'Team name is required.';
    return;
  }

  const payload = {
    name: teamDraft.name.trim(),
    description: teamDraft.description.trim() || null,
    lead_user_id: teamDraft.lead_user_id ? Number(teamDraft.lead_user_id) : null,
    assigned_lga: teamDraft.assigned_lga.trim() || null,
    member_ids: teamDraft.member_ids,
    status: teamDraft.status,
  };

  if (editingTeamId.value) {
    await updateOdkTeam(editingTeamId.value, payload);
    notice.value = 'Team updated.';
  } else {
    await createOdkTeam(payload);
    notice.value = 'Team created.';
  }

  await loadAccess();
  closeTeamModal();
};

const toggleMember = (userId: number) => {
  if (teamDraft.member_ids.includes(userId)) {
    teamDraft.member_ids = teamDraft.member_ids.filter((id) => id !== userId);
    return;
  }

  teamDraft.member_ids.push(userId);
};

onMounted(loadAccess);
</script>
