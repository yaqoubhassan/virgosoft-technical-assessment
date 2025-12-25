<script setup>
import { ref, computed } from 'vue'
import { useTradingStore } from '../stores/trading'

const tradingStore = useTradingStore()

const statusFilter = ref('all')
const sideFilter = ref('all')

const filteredOrders = computed(() => {
  let orders = tradingStore.myOrders

  if (statusFilter.value !== 'all') {
    orders = orders.filter(o => o.status === parseInt(statusFilter.value))
  }

  if (sideFilter.value !== 'all') {
    orders = orders.filter(o => o.side === sideFilter.value)
  }

  return orders
})

function formatNumber(value, decimals = 8) {
  return parseFloat(value).toFixed(decimals)
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleString()
}

function getStatusClass(status) {
  switch (status) {
    case 1: return 'bg-yellow-500/20 text-yellow-400'
    case 2: return 'bg-green-500/20 text-green-400'
    case 3: return 'bg-gray-500/20 text-gray-400'
    default: return 'bg-gray-500/20 text-gray-400'
  }
}

async function cancelOrder(orderId) {
  if (confirm('Are you sure you want to cancel this order?')) {
    try {
      await tradingStore.cancelOrder(orderId)
    } catch (error) {
      console.error('Failed to cancel order:', error)
    }
  }
}
</script>

<template>
  <div class="bg-gray-800 rounded-lg p-6">
    <h2 class="text-xl font-semibold text-white mb-4">My Orders</h2>

    <!-- Filters -->
    <div class="flex space-x-2 mb-4">
      <select
        v-model="statusFilter"
        class="px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="all">All Status</option>
        <option value="1">Open</option>
        <option value="2">Filled</option>
        <option value="3">Cancelled</option>
      </select>

      <select
        v-model="sideFilter"
        class="px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="all">All Sides</option>
        <option value="buy">Buy</option>
        <option value="sell">Sell</option>
      </select>
    </div>

    <!-- Orders List -->
    <div class="space-y-2 max-h-96 overflow-y-auto">
      <div
        v-for="order in filteredOrders"
        :key="order.id"
        class="bg-gray-700 rounded-md p-3"
      >
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center space-x-2">
            <span
              :class="[
                'px-2 py-0.5 rounded text-xs font-medium',
                order.side === 'buy' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
              ]"
            >
              {{ order.side.toUpperCase() }}
            </span>
            <span class="text-white font-medium">{{ order.symbol }}</span>
          </div>
          <span :class="['px-2 py-0.5 rounded text-xs', getStatusClass(order.status)]">
            {{ order.status_label }}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-2 text-sm mb-2">
          <div>
            <span class="text-gray-400">Price:</span>
            <span class="text-white ml-1">${{ formatNumber(order.price, 2) }}</span>
          </div>
          <div>
            <span class="text-gray-400">Amount:</span>
            <span class="text-white ml-1">{{ formatNumber(order.amount, 4) }}</span>
          </div>
          <div>
            <span class="text-gray-400">Total:</span>
            <span class="text-white ml-1">${{ formatNumber(order.total, 2) }}</span>
          </div>
          <div>
            <span class="text-gray-400 text-xs">{{ formatDate(order.created_at) }}</span>
          </div>
        </div>

        <div v-if="order.status === 1" class="mt-2">
          <button
            @click="cancelOrder(order.id)"
            :disabled="tradingStore.loading"
            class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-sm rounded disabled:opacity-50"
          >
            Cancel
          </button>
        </div>
      </div>

      <div v-if="filteredOrders.length === 0" class="text-center text-gray-500 py-8">
        No orders found
      </div>
    </div>
  </div>
</template>
