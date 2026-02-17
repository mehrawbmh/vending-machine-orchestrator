<template>
  <div>
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-gray-900">Buy Products</h2>
      <p class="mt-1 text-sm text-gray-500">Get assigned to a vending machine and purchase products.</p>
    </div>

    <!-- Status messages -->
    <div v-if="error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
      {{ error }}
    </div>

    <!-- Step indicator -->
    <div class="mb-6 flex items-center gap-3 text-sm">
      <span class="flex items-center gap-1.5">
        <span :class="step === 'idle' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'" class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold">1</span>
        <span :class="step === 'idle' ? 'text-gray-900 font-medium' : 'text-gray-400'">Start</span>
      </span>
      <span class="w-8 h-px bg-gray-300"></span>
      <span class="flex items-center gap-1.5">
        <span :class="step === 'choose' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'" class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold">2</span>
        <span :class="step === 'choose' ? 'text-gray-900 font-medium' : 'text-gray-400'">Choose Product</span>
      </span>
      <span class="w-8 h-px bg-gray-300"></span>
      <span class="flex items-center gap-1.5">
        <span :class="step === 'done' ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-500'" class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold">3</span>
        <span :class="step === 'done' ? 'text-gray-900 font-medium' : 'text-gray-400'">Done</span>
      </span>
    </div>

    <!-- Step 1: Start -->
    <div v-if="step === 'idle'" class="bg-white border border-gray-200 rounded-lg p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-2">Get a Machine</h3>
      <p class="text-sm text-gray-500 mb-5">
        The system will assign you the least-used idle vending machine. Once assigned, you can select a product to purchase.
      </p>
      <button
        @click="startWork"
        :disabled="loading"
        class="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
      >{{ loading ? 'Finding machine...' : 'Start' }}</button>
    </div>

    <!-- Step 2: Choose Product -->
    <div v-if="step === 'choose'">
      <!-- Machine info -->
      <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 flex items-center justify-between">
        <div class="text-sm">
          <span class="text-blue-600">Assigned machine:</span>
          <span class="ml-1 font-semibold text-blue-900">{{ machine.name }}</span>
          <span class="ml-1 text-blue-400">#{{ machine.id }}</span>
        </div>
        <button
          @click="reset"
          class="text-xs text-blue-600 hover:text-blue-800 underline"
        >Cancel</button>
      </div>

      <!-- Products table -->
      <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
              <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Product</th>
              <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Available</th>
              <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Quantity</th>
              <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Coins Needed</th>
              <th class="text-right px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="p in products" :key="p.id" class="hover:bg-gray-50" :class="{ 'opacity-40': p.stock === 0 }">
              <td class="px-4 py-3 font-medium text-gray-900">{{ p.name }}</td>
              <td class="px-4 py-3">
                <span :class="p.stock > 0 ? 'text-green-700' : 'text-red-500'" class="font-medium">{{ p.stock }}</span>
              </td>
              <td class="px-4 py-3">
                <input
                  v-model.number="p._qty"
                  type="number"
                  min="1"
                  :max="p.stock"
                  :disabled="p.stock === 0"
                  class="w-20 px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 disabled:bg-gray-100 disabled:text-gray-400"
                />
              </td>
              <td class="px-4 py-3 text-gray-600 font-mono">
                {{ p.stock > 0 ? (p._qty || 1) : '-' }}
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  @click="chooseProduct(p)"
                  :disabled="loading || p.stock === 0"
                  class="px-4 py-1.5 text-xs font-medium text-white bg-emerald-600 rounded-md hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed"
                >{{ loading ? 'Processing...' : 'Buy' }}</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="products.length === 0" class="px-4 py-10 text-center text-gray-400 text-sm">
          No products available in inventory.
        </div>
      </div>
    </div>

    <!-- Step 3: Done -->
    <div v-if="step === 'done'" class="bg-white border border-gray-200 rounded-lg p-6">
      <div class="flex items-start gap-3 mb-5">
        <span class="flex-shrink-0 w-8 h-8 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
        </span>
        <div>
          <h3 class="text-lg font-semibold text-gray-900">Purchase Complete</h3>
          <p class="text-sm text-gray-500 mt-1">Your order is being processed by the vending machine.</p>
        </div>
      </div>

      <div class="bg-gray-50 rounded-lg p-4 text-sm space-y-2 mb-5">
        <div class="flex justify-between">
          <span class="text-gray-500">Machine</span>
          <span class="font-medium text-gray-900">{{ result.machine.name }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-500">Product</span>
          <span class="font-medium text-gray-900">{{ result.product.name }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-500">Remaining Stock</span>
          <span class="font-medium text-gray-900">{{ result.product.stock }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-500">Machine Status</span>
          <span class="inline-block px-2 py-0.5 text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200 rounded-full">Processing</span>
        </div>
      </div>

      <button
        @click="reset"
        class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >Buy Again</button>
    </div>
  </div>
</template>

<script>
import api from '../api';

export default {
  data() {
    return {
      step: 'idle',
      machine: null,
      products: [],
      result: null,
      error: '',
      loading: false,
    };
  },
  methods: {
    async startWork() {
      this.error = '';
      this.loading = true;
      try {
        const res = await api.post('/orchestrator/start-work');
        this.machine = res.data.machine;
        await this.fetchProducts();
        this.step = 'choose';
      } catch (e) {
        this.error = e.response?.data?.error || 'No idle machine available. Please try again later.';
      } finally {
        this.loading = false;
      }
    },
    async fetchProducts() {
      const res = await api.get('/products');
      this.products = res.data.map(p => ({ ...p, _qty: 1 }));
    },
    async chooseProduct(p) {
      this.error = '';
      this.loading = true;
      const qty = p._qty || 1;
      try {
        const res = await api.post('/orchestrator/choose-product', {
          machine_id: this.machine.id,
          product_id: p.id,
          count: qty,
          coins: qty,
        });
        this.result = res.data;
        this.step = 'done';
      } catch (e) {
        this.error = e.response?.data?.error || e.response?.data?.message || 'Purchase failed.';
      } finally {
        this.loading = false;
      }
    },
    reset() {
      this.step = 'idle';
      this.machine = null;
      this.products = [];
      this.result = null;
      this.error = '';
    },
  },
};
</script>
