<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Product Inventory</h2>
        <p class="mt-1 text-sm text-gray-500">Manage products and their stock levels.</p>
      </div>
      <span class="text-sm text-gray-400">{{ products.length }} product{{ products.length !== 1 ? 's' : '' }}</span>
    </div>

    <!-- Error -->
    <div v-if="error" class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
      {{ error }}
    </div>

    <!-- Success -->
    <div v-if="success" class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
      {{ success }}
    </div>

    <!-- Create Form -->
    <div class="mb-6 bg-white border border-gray-200 rounded-lg p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-3">Add New Product</h3>
      <form @submit.prevent="createProduct" class="flex items-center gap-3">
        <input
          v-model="form.name"
          placeholder="Product name"
          required
          class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        />
        <input
          v-model.number="form.stock"
          type="number"
          min="0"
          placeholder="Stock"
          required
          class="w-28 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        />
        <button
          type="submit"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >Add Product</button>
      </form>
    </div>

    <!-- Table -->
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">ID</th>
            <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Name</th>
            <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Current Stock</th>
            <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Update Stock</th>
            <th class="text-right px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="p in products" :key="p.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ p.id }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ p.name }}</td>
            <td class="px-4 py-3">
              <span :class="p.stock > 0 ? 'text-green-700 bg-green-50 border-green-200' : 'text-red-700 bg-red-50 border-red-200'"
                    class="inline-block px-2.5 py-0.5 text-xs font-medium rounded-full border">
                {{ p.stock }} in stock
              </span>
            </td>
            <td class="px-4 py-3">
              <form @submit.prevent="updateStock(p)" class="flex items-center gap-2">
                <input
                  v-model.number="p._newStock"
                  type="number"
                  min="0"
                  class="w-20 px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                />
                <button
                  type="submit"
                  class="px-3 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md hover:bg-emerald-100"
                >Set</button>
              </form>
            </td>
            <td class="px-4 py-3 text-right">
              <button
                @click="deleteProduct(p.id)"
                class="px-3 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100"
              >Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="products.length === 0" class="px-4 py-10 text-center text-gray-400 text-sm">
        No products yet. Add one above.
      </div>
    </div>
  </div>
</template>

<script>
import api from '../api';

export default {
  data() {
    return {
      products: [],
      form: { name: '', stock: 0 },
      error: '',
      success: '',
    };
  },
  async mounted() {
    await this.fetchProducts();
  },
  methods: {
    async fetchProducts() {
      try {
        const res = await api.get('/products');
        this.products = res.data.map(p => ({ ...p, _newStock: p.stock }));
      } catch (e) {
        this.error = 'Failed to load products.';
      }
    },
    async createProduct() {
      this.error = '';
      this.success = '';
      try {
        await api.post('/products', { name: this.form.name, stock: this.form.stock });
        this.form.name = '';
        this.form.stock = 0;
        this.success = 'Product added successfully.';
        await this.fetchProducts();
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to create product.';
      }
    },
    async updateStock(p) {
      this.error = '';
      this.success = '';
      try {
        await api.patch(`/products/${p.id}/stock`, { stock: p._newStock });
        this.success = `Stock for "${p.name}" updated to ${p._newStock}.`;
        await this.fetchProducts();
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to update stock.';
      }
    },
    async deleteProduct(id) {
      if (!confirm('Delete this product?')) return;
      this.error = '';
      this.success = '';
      try {
        await api.delete(`/products/${id}`);
        this.success = 'Product deleted.';
        await this.fetchProducts();
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to delete product.';
      }
    },
  },
};
</script>
