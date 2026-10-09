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
        </div>
        <div v-for="user in users" :key="user.id" class="qds-table__row">
          <span class="table-title">{{ user.name }}<small>{{ user.email }}</small></span>
          <span>{{ user.role?.name ?? 'No role' }}<small>{{ user.designation ?? 'No designation' }}</small></span>
          <span>{{ user.assigned_lga || 'All LGAs' }}<small>{{ user.assigned_ward || 'All wards' }}</small></span>
          <QdsBadge :tone="statusTone(user.status)">{{ titleCase(user.status) }}</QdsBadge>
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
              <h2>Create user</h2>
              <p>Email and password are required. Profile details can be completed later.</p>
            </div>
            <QdsButton variant="tiny" @click="closeUserModal">Close</QdsButton>
          </div>
          <div class="team-form-grid">
            <QdsField v-model="userDraft.email" label="Email address" type="email" />
            <QdsField v-model="userDraft.password" label="Password" type="password" />
            <QdsField v-model="userDraft.name" label="Full name" />
            <QdsField v-model="userDraft.role_key" label="Role" :options="roleOptions" />
            <QdsField v-model="userDraft.designation" label="Designation" />
            <QdsField v-model="userDraft.assigned_lga" label="Assigned LGA" />
            <QdsField v-model="userDraft.assigned_ward" label="Assigned ward" />
          </div>
          <QdsButton icon="plus" block @click="saveUser">Create user</QdsButton>
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
  type OdkRole,
  type OdkTeam,
  type OdkUser,
  statusTone,
  titleCase,
  updateOdkTeam,
} from '../services/teamAccess';

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
const showTeamModal = ref(false);
const editingTeamId = ref<number | null>(null);

const userDraft = reactive({
  name: '',
  email: '',
  password: '',
  role_key: 'assessor',
  designation: '',
  assigned_lga: '',
  assigned_ward: '',
});

const teamDraft = reactive({
  name: '',
  description: '',
  lead_user_id: '',
  assigned_lga: '',
  status: 'active',
  member_ids: [] as number[],
});

const roleOptions = computed(() => roles.value.map((role) => ({ label: role.name, value: role.key })));
const userOptions = computed(() => [
  { label: 'No team lead', value: '' },
  ...users.value.map((user) => ({ label: user.name, value: String(user.id) })),
]);
const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
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
    role_key: 'assessor',
    designation: '',
    assigned_lga: '',
    assigned_ward: '',
  });
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

const openUserModal = () => {
  resetUserDraft();
  showUserModal.value = true;
};

const closeUserModal = () => {
  showUserModal.value = false;
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
  if (!userDraft.email.trim() || !userDraft.password.trim()) {
    notice.value = 'Email and password are required.';
    return;
  }

  await createOdkUser({
    name: userDraft.name.trim() || null,
    email: userDraft.email.trim(),
    password: userDraft.password,
    role_key: userDraft.role_key,
    designation: userDraft.designation.trim() || null,
    assigned_lga: userDraft.assigned_lga.trim() || null,
    assigned_ward: userDraft.assigned_ward.trim() || null,
    status: 'active',
  });

  await loadAccess();
  closeUserModal();
  notice.value = 'User created.';
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
