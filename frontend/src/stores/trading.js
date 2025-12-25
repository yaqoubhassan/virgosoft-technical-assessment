import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../composables/api'

export const useTradingStore = defineStore('trading', () => {
  const profile = ref(null)
  const orders = ref([])
  const myOrders = ref([])
  const selectedSymbol = ref('BTC')
  const loading = ref(false)
  const error = ref(null)

  const balance = computed(() => profile.value?.balance || '0.00000000')
  const assets = computed(() => profile.value?.assets || [])

  const buyOrders = computed(() => orders.value.filter(o => o.side === 'buy'))
  const sellOrders = computed(() => orders.value.filter(o => o.side === 'sell'))

  async function fetchProfile() {
    try {
      const response = await api.get('/api/profile')
      profile.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch profile'
      throw err
    }
  }

  async function fetchOrders(symbol = null) {
    try {
      const params = symbol ? { symbol } : {}
      const response = await api.get('/api/orders', { params })
      orders.value = [...response.data.buy_orders, ...response.data.sell_orders]
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch orders'
      throw err
    }
  }

  async function fetchMyOrders(filters = {}) {
    try {
      const response = await api.get('/api/my-orders', { params: filters })
      myOrders.value = response.data.orders
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch orders'
      throw err
    }
  }

  async function createOrder(orderData) {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/api/orders', orderData)
      await fetchProfile()
      await fetchOrders(orderData.symbol)
      await fetchMyOrders()
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create order'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function cancelOrder(orderId) {
    loading.value = true
    error.value = null
    try {
      const response = await api.post(`/api/orders/${orderId}/cancel`)
      await fetchProfile()
      await fetchOrders(selectedSymbol.value)
      await fetchMyOrders()
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to cancel order'
      throw err
    } finally {
      loading.value = false
    }
  }

  function updateFromMatch(data) {
    if (data.wallet) {
      if (profile.value) {
        profile.value.balance = data.wallet.balance
        profile.value.assets = data.wallet.assets
      }
    }
    if (data.order) {
      const orderIndex = myOrders.value.findIndex(o => o.id === data.order.id)
      if (orderIndex !== -1) {
        myOrders.value[orderIndex] = data.order
      }
    }
    fetchOrders(selectedSymbol.value)
  }

  function setSelectedSymbol(symbol) {
    selectedSymbol.value = symbol
  }

  return {
    profile,
    orders,
    myOrders,
    selectedSymbol,
    loading,
    error,
    balance,
    assets,
    buyOrders,
    sellOrders,
    fetchProfile,
    fetchOrders,
    fetchMyOrders,
    createOrder,
    cancelOrder,
    updateFromMatch,
    setSelectedSymbol,
  }
})
