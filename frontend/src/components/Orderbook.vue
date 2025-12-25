<script setup>
import { computed } from 'vue'
import { useTradingStore } from '../stores/trading'

const tradingStore = useTradingStore()

const buyOrders = computed(() => {
  return tradingStore.orders
    .filter(o => o.side === 'buy')
    .sort((a, b) => parseFloat(b.price) - parseFloat(a.price))
    .slice(0, 10)
})

const sellOrders = computed(() => {
  return tradingStore.orders
    .filter(o => o.side === 'sell')
    .sort((a, b) => parseFloat(a.price) - parseFloat(b.price))
    .slice(0, 10)
})

function formatNumber(value, decimals = 8) {
  return parseFloat(value).toFixed(decimals)
}
</script>

<template>
  <div class="bg-gray-800 rounded-lg p-6">
    <h2 class="text-xl font-semibold text-white mb-4">
      Orderbook - {{ tradingStore.selectedSymbol }}/USD
    </h2>

    <div class="space-y-4">
      <!-- Sell Orders (Asks) -->
      <div>
        <div class="flex justify-between text-xs text-gray-500 mb-2 px-2">
          <span>Price (USD)</span>
          <span>Amount ({{ tradingStore.selectedSymbol }})</span>
          <span>Total (USD)</span>
        </div>

        <div class="space-y-1">
          <div
            v-for="order in sellOrders.slice().reverse()"
            :key="order.id"
            class="flex justify-between text-sm px-2 py-1 rounded hover:bg-gray-700"
          >
            <span class="text-red-400 font-mono">${{ formatNumber(order.price, 2) }}</span>
            <span class="text-gray-300 font-mono">{{ formatNumber(order.amount, 4) }}</span>
            <span class="text-gray-400 font-mono">${{ formatNumber(order.total, 2) }}</span>
          </div>

          <div v-if="sellOrders.length === 0" class="text-center text-gray-500 py-2 text-sm">
            No sell orders
          </div>
        </div>
      </div>

      <!-- Spread indicator -->
      <div class="border-t border-b border-gray-700 py-2 text-center">
        <span class="text-gray-400 text-sm">Spread</span>
      </div>

      <!-- Buy Orders (Bids) -->
      <div>
        <div class="space-y-1">
          <div
            v-for="order in buyOrders"
            :key="order.id"
            class="flex justify-between text-sm px-2 py-1 rounded hover:bg-gray-700"
          >
            <span class="text-green-400 font-mono">${{ formatNumber(order.price, 2) }}</span>
            <span class="text-gray-300 font-mono">{{ formatNumber(order.amount, 4) }}</span>
            <span class="text-gray-400 font-mono">${{ formatNumber(order.total, 2) }}</span>
          </div>

          <div v-if="buyOrders.length === 0" class="text-center text-gray-500 py-2 text-sm">
            No buy orders
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
