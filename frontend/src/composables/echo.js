import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

let echoInstance = null

export function createEcho() {
  if (echoInstance) return echoInstance

  echoInstance = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY || 'your-app-key',
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
    forceTLS: true,
    authEndpoint: '/broadcasting/auth',
    auth: {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('token')}`,
      },
    },
  })

  return echoInstance
}

export function getEcho() {
  return echoInstance
}

export function destroyEcho() {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
  }
}

export function updateEchoAuth() {
  if (echoInstance) {
    echoInstance.connector.pusher.config.auth = {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('token')}`,
      },
    }
  }
}
