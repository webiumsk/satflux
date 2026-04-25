<template>
  <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain custom-scrollbar">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-white">User Management</h1>
      <p class="text-gray-400 mt-1">Manage users, roles, and email addresses</p>
    </div>

    <!-- Search and Filter -->
    <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label
            for="search"
            class="block text-sm font-medium text-gray-300 mb-2"
            >Search by Email</label
          >
          <input
            id="search"
            v-model="searchQuery"
            type="text"
            placeholder="Search users..."
            class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            @input="handleSearch"
          />
        </div>
        <div>
          <label
            for="role-filter"
            class="block text-sm font-medium text-gray-300 mb-2"
            >Filter by Role</label
          >
          <Select
            id="role-filter"
            v-model="selectedRole"
            :options="roleOptions"
            placeholder="All Roles"
            @change="loadUsers"
          />
        </div>
      </div>
    </div>

    <!-- Users Table -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-700">
          <thead class="bg-gray-900/50">
            <tr>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
              >
                ID
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
              >
                Email
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
              >
                Role
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
              >
                Stores
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
              >
                Status
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
              >
                Created
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
              >
                Last login
              </th>
              <th
                class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider"
              >
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-gray-800 divide-y divide-gray-700">
            <tr v-if="loading" class="bg-gray-800">
              <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                <svg
                  class="animate-spin h-8 w-8 text-indigo-500 mx-auto"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                  ></circle>
                  <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  ></path>
                </svg>
              </td>
            </tr>
            <tr v-else-if="users.length === 0" class="bg-gray-800">
              <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                No users found.
              </td>
            </tr>
            <tr
              v-else
              v-for="user in users"
              :key="user.id"
              class="hover:bg-gray-700/50 transition-colors"
            >
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                {{ user.id }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <router-link
                  :to="`/admin/users/${user.id}`"
                  class="text-indigo-400 hover:text-indigo-300 transition-colors"
                >
                  {{ user.email || "–" }}
                </router-link>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2 py-1 text-xs font-semibold rounded-full"
                  :class="getRoleBadgeClass(user.role || 'free')"
                >
                  {{ formatRole(user.role || "free") }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                {{ user.stores_count ?? 0 }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  v-if="user.email_verified_at"
                  class="px-2 py-1 text-xs font-semibold rounded-full bg-green-500/20 text-green-400"
                >
                  Verified
                </span>
                <span
                  v-else
                  class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-500/20 text-yellow-400"
                >
                  Unverified
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                {{ formatDate(user.created_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                {{ user.last_login_at ? formatDate(user.last_login_at) : "–" }}
              </td>
              <td
                class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"
              >
                <button
                  @click="openEditModal(user)"
                  class="text-indigo-400 hover:text-indigo-300 mr-4 transition-colors"
                >
                  Edit
                </button>
                <button
                  v-if="user.id !== currentUser?.id"
                  @click="confirmDelete(user)"
                  class="text-red-400 hover:text-red-300 transition-colors"
                >
                  Delete
                </button>
                <span v-else class="text-gray-500 text-xs">Cannot delete</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div
        v-if="meta && meta.last_page > 1"
        class="bg-gray-900/50 px-6 py-4 border-t border-gray-700 flex items-center justify-between"
      >
        <div class="text-sm text-gray-400">
          Showing {{ (meta.current_page - 1) * meta.per_page + 1 }} to
          {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of
          {{ meta.total }} results
        </div>
        <div class="flex gap-2">
          <button
            @click="changePage(meta.current_page - 1)"
            :disabled="meta.current_page === 1"
            class="px-4 py-2 text-sm font-medium text-gray-300 bg-gray-700 rounded-lg hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            Previous
          </button>
          <button
            @click="changePage(meta.current_page + 1)"
            :disabled="meta.current_page === meta.last_page"
            class="px-4 py-2 text-sm font-medium text-gray-300 bg-gray-700 rounded-lg hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Edit User Modal -->
    <div
      v-if="showEditModal"
      class="fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
      @click.self="closeEditModal"
    >
      <div
        class="bg-gray-800 rounded-2xl border border-gray-700 shadow-2xl max-w-md w-full p-6"
      >
        <h2 class="text-2xl font-bold text-white mb-6">Edit User</h2>

        <form @submit.prevent="handleUpdateUser" class="space-y-4">
          <div>
            <label
              for="edit-email"
              class="block text-sm font-medium text-gray-300 mb-2"
              >Email</label
            >
            <input
              id="edit-email"
              v-model="editForm.email"
              type="email"
              required
              class="appearance-none block w-full px-4 py-2 border border-gray-600 rounded-lg shadow-sm placeholder-gray-500 text-white bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            />
          </div>

          <div>
            <label
              for="edit-role"
              class="block text-sm font-medium text-gray-300 mb-2"
              >Role</label
            >
            <Select
              id="edit-role"
              v-model="editForm.role"
              :options="roleEditOptions"
              placeholder="Select role"
            />
          </div>

          <div
            v-if="editError"
            class="rounded-lg bg-red-500/10 border border-red-500/20 p-3"
          >
            <p class="text-sm text-red-400">{{ editError }}</p>
          </div>

          <div class="flex justify-end gap-3 pt-4">
            <button
              type="button"
              @click="closeEditModal"
              class="px-4 py-2 text-sm font-medium text-gray-300 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="updateLoading"
              class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {{ updateLoading ? "Saving..." : "Save Changes" }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="userToDelete"
      class="fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
      @click.self="cancelDelete"
    >
      <div
        class="bg-gray-800 rounded-2xl border border-gray-700 shadow-2xl max-w-md w-full p-6"
      >
        <h2 class="text-2xl font-bold text-white mb-4">Delete User</h2>
        <p class="text-gray-300 mb-6">
          Are you sure you want to delete user
          <strong>{{ userToDelete.email }}</strong
          >? This action cannot be undone and will also delete all associated
          stores.
        </p>
        <div
          v-if="deleteError"
          class="rounded-lg bg-red-500/10 border border-red-500/20 p-3 mb-4"
        >
          <p class="text-sm text-red-400">{{ deleteError }}</p>
        </div>
        <div class="flex justify-end gap-3">
          <button
            @click="cancelDelete"
            class="px-4 py-2 text-sm font-medium text-gray-300 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="handleDeleteUser"
            :disabled="deleteLoading"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {{ deleteLoading ? "Deleting..." : "Delete User" }}
          </button>
        </div>
      </div>
    </div>
  </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import api from "../../services/api";
import { useAuthStore } from "../../store/auth";
import Select from "../../components/ui/Select.vue";

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const users = ref<any[]>([]);
const meta = ref<any>(null);
const loading = ref(false);
const searchQuery = ref("");
const selectedRole = ref("all");

const roleOptions = [
  { label: "All Roles", value: "all" },
  { label: "Free", value: "free" },
  { label: "Pro", value: "pro" },
  { label: "Enterprise", value: "enterprise" },
  { label: "Support", value: "support" },
  { label: "Admin", value: "admin" },
];

const roleEditOptions = roleOptions.filter((o) => o.value !== "all");

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const showEditModal = ref(false);
const editForm = ref({
  id: null as number | null,
  email: "",
  role: "free",
});
const updateLoading = ref(false);
const editError = ref("");

const userToDelete = ref<any>(null);
const deleteLoading = ref(false);
const deleteError = ref("");

const loadUsers = async (page = 1) => {
  loading.value = true;
  try {
    const params: any = { page };
    if (searchQuery.value) {
      params.search = searchQuery.value;
    }
    if (selectedRole.value !== "all") {
      params.role = selectedRole.value;
    }

    const response = await api.get("/admin/users", { params });
    users.value = response.data.data || [];
    meta.value = response.data.meta || null;
  } catch (error: any) {
    console.error("Failed to load users:", error);
    users.value = [];
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(() => {
    loadUsers(1);
  }, 500);
};

const changePage = (page: number) => {
  if (page >= 1 && meta.value && page <= meta.value.last_page) {
    loadUsers(page);
  }
};

const openEditModal = (user: any) => {
  editForm.value = {
    id: user.id,
    email: user.email,
    role: user.role || "merchant",
  };
  editError.value = "";
  showEditModal.value = true;
};

const closeEditModal = () => {
  showEditModal.value = false;
  editForm.value = {
    id: null,
    email: "",
    role: "free",
  };
  editError.value = "";
};

const handleUpdateUser = async () => {
  if (!editForm.value.id) return;

  updateLoading.value = true;
  editError.value = "";

  try {
    await api.put(`/admin/users/${editForm.value.id}`, {
      email: editForm.value.email,
      role: editForm.value.role,
    });

    closeEditModal();
    await loadUsers(meta.value?.current_page || 1);
  } catch (error: any) {
    editError.value =
      error.response?.data?.message ||
      "Failed to update user. Please try again.";
  } finally {
    updateLoading.value = false;
  }
};

const confirmDelete = (user: any) => {
  userToDelete.value = user;
  deleteError.value = "";
};

const cancelDelete = () => {
  userToDelete.value = null;
  deleteError.value = "";
};

const handleDeleteUser = async () => {
  if (!userToDelete.value) return;

  deleteLoading.value = true;
  deleteError.value = "";

  try {
    await api.delete(`/admin/users/${userToDelete.value.id}`);

    cancelDelete();
    await loadUsers(meta.value?.current_page || 1);
  } catch (error: any) {
    deleteError.value =
      error.response?.data?.message ||
      "Failed to delete user. Please try again.";
  } finally {
    deleteLoading.value = false;
  }
};

const formatRole = (role: string) => {
  return role.charAt(0).toUpperCase() + role.slice(1);
};

const getRoleBadgeClass = (role: string) => {
  const classes: Record<string, string> = {
    free: "bg-gray-500/20 text-gray-300",
    pro: "bg-blue-500/20 text-blue-300",
    enterprise: "bg-purple-500/20 text-purple-300",
    support: "bg-yellow-500/20 text-yellow-300",
    admin: "bg-red-500/20 text-red-300",
  };
  return classes[role] || classes.free;
};

const formatDate = (dateString: string) => {
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

onMounted(() => {
  loadUsers();
});
</script>
