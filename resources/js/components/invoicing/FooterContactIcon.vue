<template>
  <span
    class="inline-flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full border border-gray-400"
    aria-hidden="true"
  >
    <svg
      v-if="icon === 'phone'"
      xmlns="http://www.w3.org/2000/svg"
      class="h-2 w-2 text-gray-500"
      viewBox="0 0 24 24"
      fill="currentColor"
    >
      <path
        d="M20 15.5c-1.25 0-2.45-.2-3.57-.57a1 1 0 0 0-1.02.24l-2.2 2.2a15.35 15.35 0 0 1-6.59-6.59l2.2-2.21a1 1 0 0 0 .24-1.02A11.36 11.36 0 0 1 8.5 4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1 17 17 0 0 0 17 17h3a1 1 0 0 0 1-1v-3.5a1 1 0 0 0-1-1z"
      />
    </svg>
    <svg
      v-else-if="icon === 'web'"
      xmlns="http://www.w3.org/2000/svg"
      class="h-2 w-2 text-gray-500"
      viewBox="0 0 24 24"
      fill="currentColor"
    >
      <path
        d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.93 9h-3.18a15.7 15.7 0 0 0-1.07-4.52A8.03 8.03 0 0 1 19.93 11zM12 4c.95 1.37 1.66 3.1 2 5H10c.34-1.9 1.05-3.63 2-5zM4.07 13h3.18c.18 1.58.48 3.09.92 4.5A8.03 8.03 0 0 1 4.07 13zm3.18-2H4.07a8.03 8.03 0 0 1 4.1-4.52c-.44 1.41-.74 2.92-.92 4.52z"
      />
    </svg>
    <svg
      v-else
      xmlns="http://www.w3.org/2000/svg"
      class="h-2 w-2 text-gray-500"
      viewBox="0 0 24 24"
      fill="currentColor"
    >
      <path
        d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"
      />
    </svg>
  </span>
</template>

<script setup lang="ts">
defineProps<{
  icon: "phone" | "web" | "email";
}>();
</script>
