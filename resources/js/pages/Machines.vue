<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Vending Machines</h2>
        <p class="mt-1 text-sm text-gray-500">Manage your fleet of vending machines.</p>
      </div>
      <span class="text-sm text-gray-400">{{ machines.length }} machine{{ machines.length !== 1 ? 's' : '' }}</span>
    </div>

    <!-- Error -->
    <div v-if="error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
      {{ error }}
    </div>

    <!-- Create / Edit Form -->
    <div class="mb-6 bg-white border border-gray-200 rounded-lg p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ editing ? 'Edit Machine' : 'Add New Machine' }}</h3>
      <form @submit.prevent="editing ? updateMachine() : createMachine()" class="flex items-center gap-3">
        <input
          v-model="form.name"
          placeholder="Machine name"
          required
          class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        />
        <button
          type="submit"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >{{ editing ? 'Update' : 'Create' }}</button>
        <button
          v-if="editing"
          type="button"
          @click="cancelEdit"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
        >Cancel</button>
      </form>
    </div>

    <!-- Table -->
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">ID</th>
            <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Name</th>
            <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Status</th>
            <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Usage</th>
            <th class="text-right px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="m in machines" :key="m.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ m.id }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ m.name }}</td>
            <td class="px-4 py-3">
              <span :class="statusClass(m.status)" class="inline-block px-2.5 py-0.5 text-xs font-medium rounded-full">
                {{ statusLabel(m.status) }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ m.usage_count }}</td>
            <td class="px-4 py-3 text-right">
              <button
                @click="editMachine(m)"
                class="inline-block px-3 py-1 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-md hover:bg-amber-100 mr-1"
              >Edit</button>
              <button
                @click="resetMachine(m.id)"
                class="inline-block px-3 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 mr-1"
              >Reset</button>
              <button
                @click="deleteMachine(m.id)"
                class="inline-block px-3 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100"
              >Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="machines.length === 0" class="px-4 py-10 text-center text-gray-400 text-sm">
        No machines yet. Create one above.
      </div>
    </div>
  </div>
</template>

<script>
import api from '../api';

export default {
  data() {
    return {
      machines: [],
      form: { name: '' },
      editing: null,
      error: '',
    };
  },
  async mounted() {
    await this.fetchMachines();
  },
  methods: {
    statusClass(status) {
      switch (status) {
        case 'idle': return 'bg-green-50 text-green-700 border border-green-200';
        case 'choose_product': return 'bg-blue-50 text-blue-700 border border-blue-200';
        case 'processing': return 'bg-amber-50 text-amber-700 border border-amber-200';
        default: return 'bg-gray-50 text-gray-700 border border-gray-200';
      }
    },
    statusLabel(status) {
      switch (status) {
        case 'idle': return 'Idle';
        case 'choose_product': return 'Choose Product';
        case 'processing': return 'Processing';
        default: return status;
      }
    },
    async fetchMachines() {
      try {
        const res = await api.get('/vending-machines');
        this.machines = res.data;
      } catch (e) {
        this.error = 'Failed to load machines.';
      }
    },
    async createMachine() {
      this.error = '';
      try {
        await api.post('/vending-machines', { name: this.form.name });
        this.form.name = '';
        await this.fetchMachines();
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to create machine.';
      }
    },
    editMachine(m) {
      this.editing = m.id;
      this.form.name = m.name;
    },
    cancelEdit() {
      this.editing = null;
      this.form.name = '';
    },
    async updateMachine() {
      this.error = '';
      try {
        await api.put(`/vending-machines/${this.editing}`, { name: this.form.name });
        this.editing = null;
        this.form.name = '';
        await this.fetchMachines();
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to update machine.';
      }
    },
    async resetMachine(id) {
      if (!confirm('Reset this machine to idle?')) return;
      this.error = '';
      try {
        await api.post(`/vending-machines/${id}/reset`);
        await this.fetchMachines();
      } catch (e) {
        this.error = e.response?.data?.error || e.response?.data?.message || 'Failed to reset machine.';
      }
    },
    async deleteMachine(id) {
      if (!confirm('Delete this machine?')) return;
      this.error = '';
      try {
        await api.delete(`/vending-machines/${id}`);
        await this.fetchMachines();
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to delete machine.';
      }
    },
  },
};
</script>
