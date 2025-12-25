<script setup>
import { ref, computed } from 'vue'
import { useTradingStore } from '../stores/trading'

const emit = defineEmits(['order-created'])
const tradingStore = useTradingStore()

const side = ref('buy')
const price = ref('')
const amount = ref('')
const error = ref('')

const totalValue = computed(() => {
  const p = parseFloat(price.value) || 0
  const a = parseFloat(amount.value) || 0
  return (p * a).toFixed(8)
})

const commission = computed(() => {
  const total = parseFloat(totalValue.value) || 0
  return (total * 0.015).toFixed(8)
})

async function handleSubmit() {
  error.value = ''

  if (!price.value || !amount.value) {
    error.value = 'Please fill in all fields'
    return
  }

  try {
    await tradingStore.createOrder({
      symbol: tradingStore.selectedSymbol,
      side: side.value,
      price: price.value,
      amount: amount.value,
    })

    price.value = ''
    amount.value = ''
    emit('order-created')
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to create order'
  }
}
</script>

<template>
  <div class="bg-gray-800 rounded-lg p-6">
    <h2 class="text-xl font-semibold text-white mb-4">Place Order</h2>

    <div v-if="error" class="mb-4 bg-red-500/10 border border-red-500 text-red-500 px-4 py-2 rounded text-sm">
      {{ error }}
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-4">
      <!-- Side Toggle -->
      <div class="flex rounded-md overflow-hidden">
        <button
          type="button"
          @click="side = 'buy'"
          :class="[
            'flex-1 py-2 text-sm font-medium transition-colors',
            side === 'buy'
              ? 'bg-green-600 text-white'
              : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
          ]"
        >
          Buy
        </button>
        <button
          type="button"
          @click="side = 'sell'"
          :class="[
            'flex-1 py-2 text-sm font-medium transition-colors',
            side === 'sell'
              ? 'bg-red-600 text-white'
              : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
          ]"
        >
          Sell
        </button>
      </div>

      <!-- Symbol Display -->
      <div>
        <label class="block text-sm font-medium text-gray-400 mb-1">Symbol</label>
        <div class="px-3 py-2 bg-gray-700 rounded-md text-white">
          {{ tradingStore.selectedSymbol }}/USD
        </div>
      </div>

      <!-- Price Input -->
      <div>
        <label for="price" class="block text-sm font-medium text-gray-400 mb-1">Price (USD)</label>
        <input
          id="price"
          v-model="price"
          type="number"
          step="0.00000001"
          min="0"
          required
          class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="0.00"
        />
      </div>

      <!-- Amount Input -->
      <div>
        <label for="amount" class="block text-sm font-medium text-gray-400 mb-1">Amount ({{ tradingStore.selectedSymbol }})</label>
        <input
          id="amount"
          v-model="amount"
          type="number"
          step="0.00000001"
          min="0"
          required
          class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="0.00"
        />
      </div>

      <!-- Order Summary -->
      <div class="bg-gray-700 rounded-md p-3 space-y-2 text-sm">
        <div class="flex justify-between text-gray-400">
          <span>Total</span>
          <span class="text-white">${{ totalValue }}</span>
        </div>
        <div class="flex justify-between text-gray-400">
          <span>Commission (1.5%)</span>
          <span class="text-white">${{ commission }}</span>
        </div>
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        :disabled="tradingStore.loading"
        :class="[
          'w-full py-3 rounded-md font-medium transition-colors disabled:opacity-50',
          side === 'buy'
            ? 'bg-green-600 hover:bg-green-700 text-white'
            : 'bg-red-600 hover:bg-red-700 text-white'
        ]"
      >
        <span v-if="tradingStore.loading">Processing...</span>
        <span v-else>{{ side === 'buy' ? 'Buy' : 'Sell' }} {{ tradingStore.selectedSymbol }}</span>
      </button>
    </form>
  </div>
</template>
