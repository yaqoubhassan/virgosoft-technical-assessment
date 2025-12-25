<script setup>
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "./stores/auth";

const authStore = useAuthStore();
const router = useRouter();

onMounted(async () => {
  if (authStore.token) {
    try {
      await authStore.fetchUser();
    } catch (error) {
      router.push("/login");
    }
  }
});
</script>

<template>
  <div class="min-h-screen bg-gray-900">
    <router-view />
  </div>
</template>
