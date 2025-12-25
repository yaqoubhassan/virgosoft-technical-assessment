<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useTradingStore } from '../stores/trading'
import { createEcho, destroyEcho } from '../composables/echo'
import OrderForm from '../components/OrderForm.vue'
import WalletOverview from '../components/WalletOverview.vue'
import Orderbook from '../components/Orderbook.vue'
import MyOrders from '../components/MyOrders.vue'

const router = useRouter()
const authStore = useAuthStore()
const tradingStore = useTradingStore()

const notification = ref(null)
let echoChannel = null

onMounted(async () => {
  try {
    await tradingStore.fetchProfile()
    await tradingStore.fetchOrders(tradingStore.selectedSymbol)
    await tradingStore.fetchMyOrders()

    // Set up real-time updates
    setupEcho()
  } catch (error) {
    console.error('Failed to load data:', error)
  }
})

onUnmounted(() => {
  if (echoChannel) {
    echoChannel.stopListening('.order.matched')
  }
  destroyEcho()
})

function setupEcho() {
  try {
    const echo = createEcho()
    const userId = authStore.user?.id

    if (userId) {
      echoChannel = echo.private(`user.${userId}`)
      echoChannel.listen('.order.matched', (data) => {
        tradingStore.updateFromMatch(data)
        showNotification('Order matched! Your order has been filled.')
      })
    }
  } catch (error) {
    console.error('Failed to set up Echo:', error)
  }
}

function showNotification(message) {
  notification.value = message
  setTimeout(() => {
    notification.value = null
  }, 5000)
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}

function handleSymbolChange(symbol) {
  tradingStore.setSelectedSymbol(symbol)
  tradingStore.fetchOrders(symbol)
}
</script>

<template>
  <div class="min-h-screen bg-gray-900">
    <!-- Notification -->
    <div
      v-if="notification"
      class="fixed top-4 right-4 z-50 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg"
    >
      {{ notification }}
    </div>

    <!-- Header -->
    <header class="bg-gray-800 border-b border-gray-700">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex items-center justify-between">
          <h1 class="text-2xl font-bold text-white">Limit Order Exchange</h1>
          <div class="flex items-center space-x-4">
            <span class="text-gray-300">{{ authStore.user?.name }}</span>
            <button
              @click="handleLogout"
              class="px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-700 rounded-md transition-colors"
            >
              Logout
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Symbol Selector -->
      <div class="mb-6 flex space-x-2">
        <button
          v-for="symbol in ['BTC', 'ETH']"
          :key="symbol"
          @click="handleSymbolChange(symbol)"
          :class="[
            'px-4 py-2 rounded-md font-medium transition-colors',
            tradingStore.selectedSymbol === symbol
              ? 'bg-blue-600 text-white'
              : 'bg-gray-800 text-gray-300 hover:bg-gray-700'
          ]"
        >
          {{ symbol }}/USD
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Order Form & Wallet -->
        <div class="space-y-6">
          <OrderForm @order-created="showNotification('Order created successfully!')" />
          <WalletOverview />
        </div>

        <!-- Middle Column: Orderbook -->
        <div>
          <Orderbook />
        </div>

        <!-- Right Column: My Orders -->
        <div>
          <MyOrders />
        </div>
      </div>
    </main>
  </div>
</template>
