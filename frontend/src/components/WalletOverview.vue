<script setup>
import { useTradingStore } from '../stores/trading'

const tradingStore = useTradingStore()

function formatNumber(value) {
  return parseFloat(value).toFixed(8)
}
</script>

<template>
  <div class="bg-gray-800 rounded-lg p-6">
    <h2 class="text-xl font-semibold text-white mb-4">Wallet</h2>

    <div class="space-y-4">
      <!-- USD Balance -->
      <div class="bg-gray-700 rounded-md p-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
              $
            </div>
            <span class="text-white font-medium">USD</span>
          </div>
          <div class="text-right">
            <div class="text-white font-medium">${{ formatNumber(tradingStore.balance) }}</div>
            <div class="text-gray-400 text-sm">Available</div>
          </div>
        </div>
      </div>

      <!-- Assets -->
      <div
        v-for="asset in tradingStore.assets"
        :key="asset.symbol"
        class="bg-gray-700 rounded-md p-4"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <div
              :class="[
                'w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm',
                asset.symbol === 'BTC' ? 'bg-orange-500' : 'bg-blue-500'
              ]"
            >
              {{ asset.symbol.charAt(0) }}
            </div>
            <span class="text-white font-medium">{{ asset.symbol }}</span>
          </div>
          <div class="text-right">
            <div class="text-white font-medium">{{ formatNumber(asset.amount) }}</div>
            <div class="text-gray-400 text-sm">
              <span v-if="parseFloat(asset.locked_amount) > 0">
                Locked: {{ formatNumber(asset.locked_amount) }}
              </span>
              <span v-else>Available</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="tradingStore.assets.length === 0" class="text-gray-400 text-center py-4">
        No assets found
      </div>
    </div>
  </div>
</template>
