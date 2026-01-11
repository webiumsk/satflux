<template>
  <div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
          <h3 class="text-lg leading-6 font-medium text-gray-900 mb-6">Account Settings</h3>

          <!-- Profile Information -->
          <div class="mb-8">
            <h4 class="text-md font-medium text-gray-900 mb-4">Profile Information</h4>
            <form @submit.prevent="handleUpdateProfile">
              <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                  <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                  <input
                    id="name"
                    v-model="profileForm.name"
                    type="text"
                    required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
                <div>
                  <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                  <input
                    id="email"
                    v-model="profileForm.email"
                    type="email"
                    required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
              </div>
              <div class="mt-6">
                <button
                  type="submit"
                  :disabled="profileLoading"
                  class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                >
                  {{ profileLoading ? 'Saving...' : 'Save Profile' }}
                </button>
              </div>
            </form>
          </div>

          <!-- Change Password -->
          <div>
            <h4 class="text-md font-medium text-gray-900 mb-4">Change Password</h4>
            <form @submit.prevent="handleUpdatePassword">
              <div class="space-y-4">
                <div>
                  <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                  <input
                    id="current_password"
                    v-model="passwordForm.current_password"
                    type="password"
                    required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
                <div>
                  <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                  <input
                    id="password"
                    v-model="passwordForm.password"
                    type="password"
                    required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
                <div>
                  <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                  <input
                    id="password_confirmation"
                    v-model="passwordForm.password_confirmation"
                    type="password"
                    required
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                </div>
              </div>
              <div class="mt-6">
                <button
                  type="submit"
                  :disabled="passwordLoading"
                  class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                >
                  {{ passwordLoading ? 'Saving...' : 'Change Password' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAuthStore } from '../../store/auth';
import api from '../../services/api';

const authStore = useAuthStore();

const profileForm = ref({
  name: '',
  email: '',
});

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const profileLoading = ref(false);
const passwordLoading = ref(false);

onMounted(() => {
  if (authStore.user) {
    profileForm.value.name = authStore.user.name;
    profileForm.value.email = authStore.user.email;
  }
});

async function handleUpdateProfile() {
  profileLoading.value = true;
  try {
    await api.put('/user', profileForm.value);
    await authStore.fetchUser();
    alert('Profile updated successfully');
  } catch (error) {
    alert('Failed to update profile');
  } finally {
    profileLoading.value = false;
  }
}

async function handleUpdatePassword() {
  passwordLoading.value = true;
  try {
    await api.put('/user/password', passwordForm.value);
    passwordForm.value = {
      current_password: '',
      password: '',
      password_confirmation: '',
    };
    alert('Password updated successfully');
  } catch (error) {
    alert('Failed to update password');
  } finally {
    passwordLoading.value = false;
  }
}
</script>

