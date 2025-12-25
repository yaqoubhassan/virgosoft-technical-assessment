import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../composables/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token') || null)

  const isAuthenticated = computed(() => !!token.value)

  async function login(email, password) {
    const response = await api.post('/api/login', { email, password })
    token.value = response.data.token
    user.value = response.data.user
    localStorage.setItem('token', response.data.token)
    return response.data
  }

  async function register(name, email, password, password_confirmation) {
    const response = await api.post('/api/register', {
      name,
      email,
      password,
      password_confirmation,
    })
    token.value = response.data.token
    user.value = response.data.user
    localStorage.setItem('token', response.data.token)
    return response.data
  }

  async function logout() {
    try {
      await api.post('/api/logout')
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('token')
    }
  }

  async function fetchUser() {
    if (!token.value) return null
    try {
      const response = await api.get('/api/user')
      user.value = response.data.user
      return response.data.user
    } catch (error) {
      token.value = null
      user.value = null
      localStorage.removeItem('token')
      throw error
    }
  }

  return {
    user,
    token,
    isAuthenticated,
    login,
    register,
    logout,
    fetchUser,
  }
})
