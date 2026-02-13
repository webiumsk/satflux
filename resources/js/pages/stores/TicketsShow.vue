<template>
  <AppShowLayout ref="layoutRef" :store="store" :app="app">
    <template #default="{ app, store }">
      <!-- Header -->
      <div class="sticky top-0 z-20 bg-gray-900/80 backdrop-blur-md border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <!-- Error/Success -->
          <div v-if="errorMsg || successMsg" class="mb-4">
            <div v-if="errorMsg" class="rounded-xl bg-red-500/10 border border-red-500/20 p-4">
              <div class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <div class="text-sm text-red-400 font-medium">{{ errorMsg }}</div>
              </div>
            </div>
            <div v-if="successMsg" class="rounded-xl bg-green-500/10 border border-green-500/20 p-4">
              <div class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <div class="text-sm text-green-400 font-medium">{{ successMsg }}</div>
              </div>
            </div>
          </div>

          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center">
              <button @click="goBack" class="mr-4 text-gray-400 hover:text-white transition-colors">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
              </button>
              <div>
                <h1 class="text-2xl font-bold text-white mb-1">{{ app.name || t('tickets.title') }}</h1>
                <p class="text-sm text-gray-400">{{ t('tickets.subtitle') }} <span class="text-indigo-400">{{ store.name }}</span></p>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <button
                type="button"
                @click="showCreateForm = !showCreateForm; expandedEventId = null"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 transition-all hover:scale-105"
              >
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                {{ t('tickets.create_event') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- ────── Create / Edit Event Form ────── -->
        <div v-if="showCreateForm" class="bg-gray-800 shadow-xl rounded-2xl border border-gray-700 overflow-hidden">
          <div class="p-6 sm:p-8">
            <div class="flex items-center justify-between mb-6">
              <h2 class="text-lg font-semibold text-white">
                {{ editingEvent ? t('tickets.edit_event') : t('tickets.new_event') }}
              </h2>
              <button @click="cancelForm" class="text-gray-400 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>

            <form @submit.prevent="handleSubmitEvent" class="space-y-6">
              <!-- Title & Event Type -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">
                    {{ t('tickets.event_title') }} <span class="text-red-400">*</span>
                  </label>
                  <input v-model="eventForm.title" type="text" required class="input-field" :placeholder="t('tickets.event_title_placeholder')" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('tickets.event_type') }}</label>
                  <div class="flex gap-3">
                    <button type="button" @click="eventForm.eventType = 'Physical'" :class="['flex-1 px-4 py-3 rounded-xl border-2 text-sm font-medium transition-all', eventForm.eventType === 'Physical' ? 'border-indigo-500 bg-indigo-500/10 text-white' : 'border-gray-600 bg-gray-900 text-gray-400 hover:border-gray-500']">{{ t('tickets.physical') }}</button>
                    <button type="button" @click="eventForm.eventType = 'Virtual'" :class="['flex-1 px-4 py-3 rounded-xl border-2 text-sm font-medium transition-all', eventForm.eventType === 'Virtual' ? 'border-indigo-500 bg-indigo-500/10 text-white' : 'border-gray-600 bg-gray-900 text-gray-400 hover:border-gray-500']">{{ t('tickets.virtual') }}</button>
                  </div>
                </div>
              </div>

              <!-- Description & Event Image -->
              <div class="grid grid-cols-1 md:grid-cols-[1fr,auto] gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('tickets.description') }}</label>
                  <textarea v-model="eventForm.description" rows="5" class="input-field resize-none" :placeholder="t('tickets.description_placeholder')"></textarea>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('tickets.event_image') }}</label>
                  <div class="flex flex-col items-center gap-3">
                    <!-- Preview -->
                    <div v-if="eventForm.eventLogoUrl || imagePreview" class="relative group">
                      <div class="w-32 h-32 rounded-xl bg-gray-700 flex items-center justify-center overflow-hidden border border-gray-600">
                        <img :src="imagePreview || eventForm.eventLogoUrl || ''" alt="Event image" class="w-full h-full object-cover" @error="imagePreview = null" />
                      </div>
                      <button type="button" @click="clearEventImage" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow-lg hover:bg-red-600 transition-colors" :title="t('common.delete')">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                      </button>
                    </div>
                    <div v-else class="w-32 h-32 rounded-xl bg-gray-700/50 flex items-center justify-center border-2 border-dashed border-gray-600 text-gray-500">
                      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </div>
                    <!-- Upload -->
                    <div class="w-32">
                      <input ref="eventImageInput" type="file" accept="image/*" class="hidden" @change="onEventImageChange" />
                      <button type="button" @click="eventImageInput?.click()" :disabled="uploadingImage" class="w-full px-3 py-1.5 text-xs font-medium rounded-lg text-gray-300 bg-gray-700 border border-gray-600 hover:bg-gray-600 hover:text-white disabled:opacity-50 transition-colors">
                        <svg v-if="uploadingImage" class="animate-spin inline-block w-3 h-3 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        {{ uploadingImage ? t('tickets.uploading') : t('tickets.upload_image') }}
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Location & Currency -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('tickets.location') }}</label>
                  <input v-model="eventForm.location" type="text" class="input-field" :placeholder="t('tickets.location_placeholder')" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('tickets.currency') }}</label>
                  <input v-model="eventForm.currency" type="text" maxlength="10" class="input-field" placeholder="EUR" />
                  <p class="mt-1 text-xs text-gray-500">{{ t('tickets.currency_hint') }}</p>
                </div>
              </div>

              <!-- Start Date & End Date -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('tickets.start_date') }} <span class="text-red-400">*</span></label>
                  <DatePicker v-model="eventForm.startDate" type="datetime" :placeholder="t('tickets.start_date')" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('tickets.end_date') }}</label>
                  <DatePicker v-model="eventForm.endDate" type="datetime" :placeholder="t('tickets.end_date')" />
                </div>
              </div>

              <!-- Capacity -->
              <div class="bg-gray-900/50 rounded-xl p-5 border border-gray-700/50">
                <div class="flex items-center justify-between mb-3">
                  <label class="text-sm font-medium text-gray-300">{{ t('tickets.maximum_capacity') }}</label>
                  <button type="button" @click="eventForm.hasMaximumCapacity = !eventForm.hasMaximumCapacity" :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors', eventForm.hasMaximumCapacity ? 'bg-indigo-600' : 'bg-gray-600']">
                    <span :class="['inline-block h-4 w-4 transform rounded-full bg-white transition-transform', eventForm.hasMaximumCapacity ? 'translate-x-6' : 'translate-x-1']" />
                  </button>
                </div>
                <div v-if="eventForm.hasMaximumCapacity">
                  <input v-model.number="eventForm.maximumEventCapacity" type="number" min="1" required class="input-field" :placeholder="t('tickets.capacity_placeholder')" />
                </div>
              </div>

              <!-- Redirect URL -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('tickets.redirect_url') }}</label>
                <input v-model="eventForm.redirectUrl" type="url" class="input-field" :placeholder="t('tickets.redirect_url_placeholder')" />
                <p class="mt-1 text-xs text-gray-500">{{ t('tickets.redirect_url_hint') }}</p>
              </div>

              <!-- Email Settings (collapsible) -->
              <div class="border border-gray-700/50 rounded-xl overflow-hidden">
                <button type="button" @click="showEmailSettings = !showEmailSettings" class="w-full flex items-center justify-between px-5 py-4 text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700/30 transition-all">
                  <span>{{ t('tickets.email_settings') }}</span>
                  <svg :class="['w-5 h-5 transition-transform', showEmailSettings ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-if="showEmailSettings" class="px-5 pb-5 space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('tickets.email_subject') }}</label>
                    <input v-model="eventForm.emailSubject" type="text" class="input-field" placeholder="Your ticket for {{Title}}" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ t('tickets.email_body') }}</label>
                    <textarea v-model="eventForm.emailBody" rows="4" class="input-field resize-none" placeholder="Hello {{Name}}, here is your ticket for {{Title}} at {{Location}}."></textarea>
                    <p class="mt-1 text-xs text-gray-500">{{ t('tickets.email_placeholders_hint') }}</p>
                  </div>
                </div>
              </div>

              <!-- Submit -->
              <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-700/50">
                <button type="button" @click="cancelForm" class="px-4 py-2 border border-gray-600 rounded-xl text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition-colors">{{ t('common.cancel') }}</button>
                <button type="submit" :disabled="submitting" class="btn-primary">
                  <svg v-if="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  {{ submitting ? t('tickets.saving') : (editingEvent ? t('tickets.update_event') : t('tickets.create_event')) }}
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- ────── Events List ────── -->
        <div>
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white">{{ t('tickets.events') }}</h2>
            <button v-if="!loadingEvents" @click="loadEvents" class="text-sm text-gray-400 hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            </button>
          </div>

          <!-- Loading -->
          <div v-if="loadingEvents" class="flex items-center justify-center py-12">
            <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          </div>

          <!-- Empty State -->
          <div v-else-if="events.length === 0" class="text-center py-16">
            <div class="bg-gray-800/50 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6">
              <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
            </div>
            <h3 class="text-xl font-medium text-white mb-2">{{ t('tickets.no_events') }}</h3>
            <p class="text-gray-400 mb-8 max-w-sm mx-auto">{{ t('tickets.no_events_description') }}</p>
            <button @click="showCreateForm = true" class="inline-flex items-center px-6 py-3 border border-transparent rounded-xl shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">{{ t('tickets.create_first_event') }}</button>
          </div>

          <!-- Events Cards -->
          <div v-else class="space-y-4">
            <div
              v-for="event in events"
              :key="event.id"
              class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden transition-all"
              :class="expandedEventId === event.id ? 'border-indigo-500/40' : 'hover:border-gray-600'"
            >
              <!-- Event Header (clickable to expand) -->
              <div
                class="p-6 cursor-pointer"
                @click="toggleExpandEvent(event)"
              >
                <div class="flex items-center gap-4">
                  <!-- Event image thumbnail -->
                  <div v-if="event.eventLogoUrl || (event as any).logoUrl" class="flex-shrink-0 w-14 h-14 rounded-lg overflow-hidden bg-gray-700 border border-gray-600">
                    <img :src="event.eventLogoUrl || (event as any).logoUrl" alt="" class="w-full h-full object-cover" @error="(e: Event) => (e.target as HTMLImageElement).style.display = 'none'" />
                  </div>
                  <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center gap-3">
                    <span :class="[
                      'px-2.5 py-1 text-xs font-medium rounded-full',
                      event.eventState === 'Active'
                        ? 'bg-green-500/20 text-green-400 border border-green-500/30'
                        : 'bg-gray-600/20 text-gray-400 border border-gray-600/30'
                    ]">{{ event.eventState }}</span>
                    <span class="text-xs text-gray-500 uppercase tracking-wider">{{ event.eventType }}</span>
                  </div>
                  <svg :class="['w-5 h-5 text-gray-400 transition-transform', expandedEventId === event.id ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </div>

                <h3 class="text-lg font-medium text-white mb-2">{{ event.title }}</h3>

                <div class="flex flex-wrap gap-x-6 gap-y-1">
                  <div v-if="event.location" class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    {{ event.location }}
                  </div>
                  <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    {{ formatDate(event.startDate) }}
                  </div>
                  <div v-if="event.ticketsSold != null" class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                    {{ event.ticketsSold }} {{ t('tickets.sold') }}<span v-if="event.hasMaximumCapacity && event.maximumEventCapacity">&nbsp;/ {{ event.maximumEventCapacity }}</span>
                  </div>
                </div>

                <!-- Actions row -->
                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-700/50" @click.stop>
                  <button @click="handleToggleEvent(event)" :title="event.eventState === 'Active' ? t('tickets.disable') : t('tickets.activate')" :class="['p-2 rounded-lg transition-colors text-sm', event.eventState === 'Active' ? 'text-yellow-400 hover:bg-yellow-500/10' : 'text-green-400 hover:bg-green-500/10']">
                    <svg v-if="event.eventState === 'Active'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  </button>
                  <button @click="handleEditEvent(event)" :title="t('common.edit')" class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                  </button>
                  <a v-if="event.purchaseLink" :href="event.purchaseLink" target="_blank" :title="t('tickets.open_purchase_link')" class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                  </a>
                  <div class="flex-1"></div>
                  <button @click="handleDeleteEvent(event)" :title="t('common.delete')" class="p-2 rounded-lg text-gray-400 hover:text-red-400 hover:bg-red-500/10 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                  </button>
                </div>
                  </div>
                </div>
              </div>

              <!-- ── Expanded: Tabs ── -->
              <div v-if="expandedEventId === event.id" class="border-t border-gray-700 bg-gray-850">
                <!-- Tab Nav -->
                <div class="flex border-b border-gray-700">
                  <button
                    @click="activeTab = 'types'"
                    :class="['px-6 py-3 text-sm font-medium transition-colors border-b-2', activeTab === 'types' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-gray-400 hover:text-white']"
                  >{{ t('tickets.ticket_types') }}</button>
                  <button
                    @click="activeTab = 'tickets'; loadTicketsIfNeeded(event.id)"
                    :class="['px-6 py-3 text-sm font-medium transition-colors border-b-2', activeTab === 'tickets' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-gray-400 hover:text-white']"
                  >{{ t('tickets.tickets_tab') }}</button>
                </div>

                <!-- ═══ TAB: Ticket Types ═══ -->
                <div v-if="activeTab === 'types'" class="p-6 space-y-5">
                  <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-200 uppercase tracking-wider">{{ t('tickets.ticket_types') }}</h4>
                    <button
                      v-if="!showTicketTypeForm"
                      @click="openTicketTypeForm(event.id)"
                      class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg text-indigo-300 bg-indigo-500/10 border border-indigo-500/30 hover:bg-indigo-500/20 transition-colors"
                    >
                      <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                      {{ t('tickets.add_ticket_type') }}
                    </button>
                  </div>

                  <!-- Loading ticket types -->
                  <div v-if="loadingTicketTypes" class="flex items-center justify-center py-6">
                    <svg class="animate-spin h-6 w-6 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  </div>

                  <!-- Ticket types list -->
                  <div v-else-if="eventTicketTypes.length > 0" class="space-y-3">
                    <div
                      v-for="tt in eventTicketTypes"
                      :key="tt.id"
                      class="flex items-center justify-between p-4 bg-gray-900/60 rounded-xl border border-gray-700/50"
                    >
                      <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                          <span class="text-white font-medium truncate">{{ tt.name }}</span>
                          <span v-if="tt.isDefault" class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 uppercase">{{ t('tickets.default') }}</span>
                          <span :class="[
                            'px-1.5 py-0.5 text-[10px] font-medium rounded uppercase',
                            tt.ticketTypeState === 'Active'
                              ? 'bg-green-500/20 text-green-400 border border-green-500/30'
                              : 'bg-gray-600/20 text-gray-500 border border-gray-600/30'
                          ]">{{ tt.ticketTypeState }}</span>
                        </div>
                        <div class="flex items-center gap-4 text-sm text-gray-400">
                          <span class="font-semibold text-indigo-300">{{ tt.price }} {{ event.currency || '' }}</span>
                          <span v-if="tt.quantity != null">{{ tt.quantitySold || 0 }} / {{ tt.quantity }} {{ t('tickets.sold') }}</span>
                          <span v-if="tt.description" class="truncate text-gray-500">{{ tt.description }}</span>
                        </div>
                      </div>
                      <div class="flex items-center gap-1 ml-4">
                        <button @click="handleToggleTicketType(event.id, tt)" :title="tt.ticketTypeState === 'Active' ? t('tickets.disable') : t('tickets.activate')" :class="['p-1.5 rounded-lg transition-colors', tt.ticketTypeState === 'Active' ? 'text-yellow-400 hover:bg-yellow-500/10' : 'text-green-400 hover:bg-green-500/10']">
                          <svg v-if="tt.ticketTypeState === 'Active'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                          <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </button>
                        <button @click="handleEditTicketType(event.id, tt)" :title="t('common.edit')" class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition-colors">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </button>
                        <button @click="handleDeleteTicketType(event.id, tt)" :title="t('common.delete')" class="p-1.5 rounded-lg text-gray-400 hover:text-red-400 hover:bg-red-500/10 transition-colors">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- No ticket types -->
                  <div v-else-if="!showTicketTypeForm" class="text-center py-6">
                    <p class="text-gray-500 text-sm mb-3">{{ t('tickets.no_ticket_types') }}</p>
                    <button @click="openTicketTypeForm(event.id)" class="text-sm text-indigo-400 hover:text-indigo-300 transition-colors">{{ t('tickets.add_first_ticket_type') }}</button>
                  </div>

                  <!-- ── Add / Edit Ticket Type Form ── -->
                  <div v-if="showTicketTypeForm && ticketTypeFormEventId === event.id" class="bg-gray-900/50 rounded-xl p-5 border border-gray-700/50 space-y-4">
                    <div class="flex items-center justify-between">
                      <h5 class="text-sm font-medium text-white">{{ editingTicketType ? t('tickets.edit_ticket_type') : t('tickets.new_ticket_type') }}</h5>
                      <button @click="cancelTicketTypeForm" class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                      </button>
                    </div>

                    <form @submit.prevent="handleSubmitTicketType(event.id)" class="space-y-4">
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                          <label class="block text-xs font-medium text-gray-400 mb-1">{{ t('tickets.tt_name') }} <span class="text-red-400">*</span></label>
                          <input v-model="ttForm.name" type="text" required class="input-field-sm" :placeholder="t('tickets.tt_name_placeholder')" />
                        </div>
                        <div>
                          <label class="block text-xs font-medium text-gray-400 mb-1">{{ t('tickets.tt_price') }} <span class="text-red-400">*</span></label>
                          <input v-model.number="ttForm.price" type="number" step="0.01" min="0.01" required class="input-field-sm" placeholder="20.00" />
                        </div>
                      </div>

                      <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1">{{ t('tickets.tt_description') }}</label>
                        <input v-model="ttForm.description" type="text" class="input-field-sm" :placeholder="t('tickets.tt_description_placeholder')" />
                      </div>

                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                          <label class="block text-xs font-medium text-gray-400 mb-1">{{ t('tickets.tt_quantity') }}</label>
                          <input v-model.number="ttForm.quantity" type="number" min="1" class="input-field-sm" :placeholder="t('tickets.tt_quantity_placeholder')" />
                        </div>
                        <div class="flex items-end">
                          <label class="flex items-center gap-2 cursor-pointer pb-3">
                            <input v-model="ttForm.isDefault" type="checkbox" class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-300">{{ t('tickets.tt_is_default') }}</span>
                          </label>
                        </div>
                      </div>

                      <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="cancelTicketTypeForm" class="px-3 py-1.5 text-xs border border-gray-600 rounded-lg text-gray-300 hover:text-white hover:bg-gray-700 transition-colors">{{ t('common.cancel') }}</button>
                        <button type="submit" :disabled="submittingTT" class="inline-flex items-center px-4 py-1.5 text-xs font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                          <svg v-if="submittingTT" class="animate-spin -ml-1 mr-1.5 h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                          {{ editingTicketType ? t('tickets.update_ticket_type') : t('tickets.add_ticket_type') }}
                        </button>
                      </div>
                    </form>
                  </div>
                </div>

                <!-- ═══ TAB: Tickets (sold tickets list) ═══ -->
                <div v-if="activeTab === 'tickets'" class="p-6 space-y-5">
                  <!-- Toolbar: search, check-in link, CSV export -->
                  <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <!-- Search -->
                    <div class="relative flex-1">
                      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                      <input
                        v-model="ticketSearch"
                        @input="debouncedSearchTickets(event.id)"
                        type="text"
                        class="input-field-sm pl-9"
                        :placeholder="t('tickets.search_tickets_placeholder')"
                      />
                      <button v-if="ticketSearch" @click="ticketSearch = ''; loadTickets(event.id)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                      </button>
                    </div>
                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                      <button
                        @click="openPanelCheckIn(event)"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg text-emerald-300 bg-emerald-500/10 border border-emerald-500/30 hover:bg-emerald-500/20 transition-colors"
                        :title="t('tickets.check_in')"
                      >
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ t('tickets.check_in') }}
                      </button>
                      <a
                        v-if="btcPayUrl"
                        :href="getCheckInUrl(event)"
                        target="_blank"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition-colors"
                        :title="t('tickets.open_checkin')"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                      </a>
                      <button
                        @click="exportTicketsCsv(event)"
                        :disabled="eventTickets.length === 0"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg text-gray-300 bg-gray-700/50 border border-gray-600 hover:bg-gray-700 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                        :title="t('tickets.export_csv')"
                      >
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        {{ t('tickets.export_csv') }}
                      </button>
                      <button @click="loadTickets(event.id)" class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition-colors" title="Refresh">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                      </button>
                    </div>
                  </div>

                  <!-- Loading tickets -->
                  <div v-if="loadingTickets" class="flex items-center justify-center py-8">
                    <svg class="animate-spin h-6 w-6 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  </div>

                  <!-- Tickets table -->
                  <div v-else-if="eventTickets.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead>
                        <tr class="text-left text-xs font-medium text-gray-400 uppercase tracking-wider border-b border-gray-700">
                          <th class="pb-3 pr-4">{{ t('tickets.col_ticket_number') }}</th>
                          <th class="pb-3 pr-4">{{ t('tickets.col_name') }}</th>
                          <th class="pb-3 pr-4">{{ t('tickets.col_email') }}</th>
                          <th class="pb-3 pr-4">{{ t('tickets.col_type') }}</th>
                          <th class="pb-3 pr-4">{{ t('tickets.col_amount') }}</th>
                          <th class="pb-3 pr-4">{{ t('tickets.col_status') }}</th>
                          <th class="pb-3 pr-4">{{ t('tickets.col_checked_in') }}</th>
                          <th class="pb-3 pr-4">{{ t('tickets.col_date') }}</th>
                          <th class="pb-3">{{ t('tickets.col_actions') }}</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-700/50">
                        <tr v-for="ticket in eventTickets" :key="ticket.id" class="hover:bg-gray-900/40 transition-colors">
                          <td class="py-3 pr-4">
                            <span class="font-mono text-xs text-gray-300" :title="ticket.ticketNumber">{{ shortTicketNumber(ticket.ticketNumber) }}</span>
                          </td>
                          <td class="py-3 pr-4 text-white">{{ ticket.firstName }} {{ ticket.lastName }}</td>
                          <td class="py-3 pr-4 text-gray-400 truncate max-w-[180px]">{{ ticket.email }}</td>
                          <td class="py-3 pr-4 text-gray-400">{{ ticket.ticketTypeName }}</td>
                          <td class="py-3 pr-4 text-indigo-300 font-medium">{{ ticket.amount }} {{ event.currency || '' }}</td>
                          <td class="py-3 pr-4">
                            <span :class="[
                              'px-1.5 py-0.5 text-[10px] font-medium rounded uppercase',
                              ticket.paymentStatus === 'Settled'
                                ? 'bg-green-500/20 text-green-400 border border-green-500/30'
                                : 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30'
                            ]">{{ ticket.paymentStatus }}</span>
                          </td>
                          <td class="py-3 pr-4">
                            <span v-if="ticket.checkedIn" class="flex items-center gap-1 text-green-400 text-xs">
                              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                              {{ ticket.checkedInAt ? formatDate(ticket.checkedInAt) : t('tickets.yes') }}
                            </span>
                            <span v-else class="text-gray-500 text-xs">{{ t('tickets.no') }}</span>
                          </td>
                          <td class="py-3 pr-4 text-gray-400 text-xs whitespace-nowrap">{{ formatDate(ticket.createdAt) }}</td>
                          <td class="py-3">
                            <div class="flex items-center gap-1">
                              <button
                                v-if="!ticket.checkedIn"
                                @click="handleCheckIn(event.id, ticket)"
                                :title="t('tickets.check_in')"
                                class="p-1.5 rounded-lg text-emerald-400 hover:bg-emerald-500/10 transition-colors"
                              >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                              </button>
                              <span v-else class="p-1.5 text-gray-600">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                              </span>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>

                    <!-- Summary -->
                    <div class="flex items-center justify-between pt-4 mt-4 border-t border-gray-700/50 text-xs text-gray-400">
                      <span>{{ t('tickets.total_tickets', { count: eventTickets.length }) }}</span>
                      <span>{{ t('tickets.checked_in_count', { count: eventTickets.filter(tk => tk.checkedIn).length, total: eventTickets.length }) }}</span>
                    </div>
                  </div>

                  <!-- No tickets -->
                  <div v-else class="text-center py-8">
                    <p class="text-gray-500 text-sm">{{ ticketSearch ? t('tickets.no_tickets_search') : t('tickets.no_tickets') }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </AppShowLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useTicketsStore, type TicketEvent, type TicketType, type Ticket } from '../../store/tickets';
import { useBtcPayUrl } from '../../composables/useBtcPayUrl';
import api from '../../services/api';
import AppShowLayout from '../../components/stores/AppShowLayout.vue';
import DatePicker from '../../components/ui/DatePicker.vue';

const { t } = useI18n();
const ticketsStore = useTicketsStore();
const { btcPayUrl, load: loadBtcPayUrl } = useBtcPayUrl();
const isInertia = inject<boolean>('inertia', false);

const props = defineProps<{
  app: any;
  store: any;
}>();

const layoutRef = ref<InstanceType<typeof AppShowLayout> | null>(null);

// ── State ───────────────────────────────────────
const events = ref<TicketEvent[]>([]);
const loadingEvents = ref(false);
const showCreateForm = ref(false);
const showEmailSettings = ref(false);
const submitting = ref(false);
const editingEvent = ref<TicketEvent | null>(null);
const errorMsg = ref('');
const successMsg = ref('');

// Event form
const eventForm = ref({
  title: '',
  description: '',
  eventType: 'Physical' as 'Physical' | 'Virtual',
  location: '',
  startDate: '',
  endDate: '',
  currency: '',
  redirectUrl: '',
  emailSubject: '',
  emailBody: '',
  hasMaximumCapacity: false,
  maximumEventCapacity: null as number | null,
  eventLogoUrl: '' as string,
});

// Image upload
const imagePreview = ref<string | null>(null);
const uploadingImage = ref(false);
const eventImageInput = ref<HTMLInputElement | null>(null);

// Expanded event + tabs
const expandedEventId = ref<string | null>(null);
const activeTab = ref<'types' | 'tickets'>('types');
const eventTicketTypes = ref<TicketType[]>([]);
const loadingTicketTypes = ref(false);

// Tickets tab
const eventTickets = ref<Ticket[]>([]);
const loadingTickets = ref(false);
const ticketSearch = ref('');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

// Ticket type form
const showTicketTypeForm = ref(false);
const ticketTypeFormEventId = ref<string | null>(null);
const editingTicketType = ref<TicketType | null>(null);
const submittingTT = ref(false);
const ttForm = ref({
  name: '',
  price: null as number | null,
  description: '',
  quantity: null as number | null,
  isDefault: false,
});

// ── Helpers ─────────────────────────────────────

function clearMessages() {
  errorMsg.value = '';
  successMsg.value = '';
}

function showSuccess(msg: string) {
  successMsg.value = msg;
  errorMsg.value = '';
  setTimeout(() => { successMsg.value = ''; }, 5000);
}

function showError(msg: string) {
  errorMsg.value = msg;
  successMsg.value = '';
}

function formatDate(dateStr: string): string {
  try {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(dateStr));
  } catch { return dateStr; }
}

function shortTicketNumber(ticketNumber: string): string {
  const parts = ticketNumber.split('-');
  return parts.length > 1 ? parts[parts.length - 1] : ticketNumber;
}

function resetForm() {
  eventForm.value = { title: '', description: '', eventType: 'Physical', location: '', startDate: '', endDate: '', currency: '', redirectUrl: '', emailSubject: '', emailBody: '', hasMaximumCapacity: false, maximumEventCapacity: null, eventLogoUrl: '' };
  editingEvent.value = null;
  showEmailSettings.value = false;
  imagePreview.value = null;
}

function cancelForm() { resetForm(); showCreateForm.value = false; }
function goBack() { window.history.back(); }

// ── Image Upload ────────────────────────────────

async function onEventImageChange(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;

  // Show preview immediately
  const reader = new FileReader();
  reader.onload = (ev) => { imagePreview.value = ev.target?.result as string; };
  reader.readAsDataURL(file);

  // Upload
  uploadingImage.value = true;
  try {
    const formData = new FormData();
    formData.append('image', file);
    const response = await api.post(`/stores/${props.store.id}/products/image`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    eventForm.value.eventLogoUrl = response.data.data?.url || response.data.data?.image_url || '';
    showSuccess(t('tickets.image_uploaded'));
  } catch (err: any) {
    imagePreview.value = null;
    showError(err?.response?.data?.message || err?.message || 'Failed to upload image');
  } finally {
    uploadingImage.value = false;
    if (input) input.value = '';
  }
}

function clearEventImage() {
  eventForm.value.eventLogoUrl = '';
  imagePreview.value = null;
}

function toISOString(localDatetime: string): string {
  if (!localDatetime) return '';
  return new Date(localDatetime).toISOString();
}

function fromISOToLocal(iso: string): string {
  if (!iso) return '';
  const d = new Date(iso);
  const pad = (n: number) => n.toString().padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function resetTicketTypeForm() {
  ttForm.value = { name: '', price: null, description: '', quantity: null, isDefault: false };
  editingTicketType.value = null;
}

function cancelTicketTypeForm() {
  resetTicketTypeForm();
  showTicketTypeForm.value = false;
  ticketTypeFormEventId.value = null;
}

function openTicketTypeForm(eventId: string) {
  resetTicketTypeForm();
  ticketTypeFormEventId.value = eventId;
  showTicketTypeForm.value = true;
}

// ── Data Loading ────────────────────────────────

async function loadEvents() {
  loadingEvents.value = true;
  clearMessages();
  try {
    events.value = await ticketsStore.fetchEvents(props.store.id);
  } catch (err: any) {
    showError(err?.response?.data?.message || err?.message || 'Failed to load events');
  } finally {
    loadingEvents.value = false;
  }
}

async function loadTicketTypes(eventId: string) {
  loadingTicketTypes.value = true;
  try {
    eventTicketTypes.value = await ticketsStore.fetchTicketTypes(props.store.id, eventId);
  } catch (err: any) {
    eventTicketTypes.value = [];
    showError(err?.response?.data?.message || err?.message || 'Failed to load ticket types');
  } finally {
    loadingTicketTypes.value = false;
  }
}

// ── Expand / Collapse ───────────────────────────

function toggleExpandEvent(event: TicketEvent) {
  if (expandedEventId.value === event.id) {
    expandedEventId.value = null;
    eventTicketTypes.value = [];
    eventTickets.value = [];
    ticketSearch.value = '';
    activeTab.value = 'types';
    cancelTicketTypeForm();
  } else {
    expandedEventId.value = event.id;
    activeTab.value = 'types';
    eventTickets.value = [];
    ticketSearch.value = '';
    cancelTicketTypeForm();
    loadTicketTypes(event.id);
  }
}

// ── Event Actions ───────────────────────────────

async function handleSubmitEvent() {
  submitting.value = true;
  clearMessages();

  const data: any = { title: eventForm.value.title, eventType: eventForm.value.eventType, startDate: toISOString(eventForm.value.startDate), hasMaximumCapacity: eventForm.value.hasMaximumCapacity };
  if (eventForm.value.description) data.description = eventForm.value.description;
  if (eventForm.value.location) data.location = eventForm.value.location;
  if (eventForm.value.endDate) data.endDate = toISOString(eventForm.value.endDate);
  if (eventForm.value.currency) data.currency = eventForm.value.currency;
  if (eventForm.value.redirectUrl) data.redirectUrl = eventForm.value.redirectUrl;
  if (eventForm.value.emailSubject) data.emailSubject = eventForm.value.emailSubject;
  if (eventForm.value.emailBody) data.emailBody = eventForm.value.emailBody;
  if (eventForm.value.hasMaximumCapacity && eventForm.value.maximumEventCapacity) data.maximumEventCapacity = eventForm.value.maximumEventCapacity;
  if (eventForm.value.eventLogoUrl) data.eventLogoUrl = eventForm.value.eventLogoUrl;

  try {
    if (editingEvent.value) {
      await ticketsStore.updateEvent(props.store.id, editingEvent.value.id, data);
      showSuccess(t('tickets.event_updated'));
    } else {
      await ticketsStore.createEvent(props.store.id, data);
      showSuccess(t('tickets.event_created'));
    }
    cancelForm();
    await loadEvents();
  } catch (err: any) {
    const msg = err?.response?.data?.message || (Array.isArray(err?.response?.data) ? err.response.data.map((e: any) => e.message).join(', ') : '') || err?.message || 'Failed to save event';
    showError(msg);
  } finally {
    submitting.value = false;
  }
}

function handleEditEvent(event: TicketEvent) {
  editingEvent.value = event;
  eventForm.value = {
    title: event.title, description: event.description || '', eventType: event.eventType || 'Physical', location: event.location || '',
    startDate: fromISOToLocal(event.startDate), endDate: event.endDate ? fromISOToLocal(event.endDate) : '', currency: event.currency || '',
    redirectUrl: event.redirectUrl || '', emailSubject: event.emailSubject || '', emailBody: event.emailBody || '',
    hasMaximumCapacity: event.hasMaximumCapacity || false, maximumEventCapacity: event.maximumEventCapacity || null,
    eventLogoUrl: event.eventLogoUrl || (event as any).logoUrl || '',
  };
  imagePreview.value = null;
  if (event.emailSubject || event.emailBody) showEmailSettings.value = true;
  showCreateForm.value = true;
  expandedEventId.value = null;
}

async function handleToggleEvent(event: TicketEvent) {
  clearMessages();
  try {
    await ticketsStore.toggleEvent(props.store.id, event.id);
    await loadEvents();
    showSuccess(event.eventState === 'Active' ? t('tickets.event_disabled') : t('tickets.event_activated'));
  } catch (err: any) {
    showError(err?.response?.data?.message || err?.message || 'Failed to toggle event');
  }
}

async function handleDeleteEvent(event: TicketEvent) {
  if (!confirm(t('tickets.delete_confirm', { title: event.title }))) return;
  clearMessages();
  try {
    await ticketsStore.deleteEvent(props.store.id, event.id);
    if (expandedEventId.value === event.id) expandedEventId.value = null;
    await loadEvents();
    showSuccess(t('tickets.event_deleted'));
  } catch (err: any) {
    showError(err?.response?.data?.message || err?.message || 'Failed to delete event');
  }
}

// ── Ticket Type Actions ─────────────────────────

async function handleSubmitTicketType(eventId: string) {
  submittingTT.value = true;
  clearMessages();

  const data: any = { name: ttForm.value.name, price: ttForm.value.price };
  if (ttForm.value.description) data.description = ttForm.value.description;
  if (ttForm.value.quantity != null && ttForm.value.quantity > 0) data.quantity = ttForm.value.quantity;
  if (ttForm.value.isDefault) data.isDefault = true;

  try {
    if (editingTicketType.value) {
      await ticketsStore.updateTicketType(props.store.id, eventId, editingTicketType.value.id, data);
      showSuccess(t('tickets.ticket_type_updated'));
    } else {
      await ticketsStore.createTicketType(props.store.id, eventId, data);
      showSuccess(t('tickets.ticket_type_created'));
    }
    cancelTicketTypeForm();
    await loadTicketTypes(eventId);
  } catch (err: any) {
    const msg = err?.response?.data?.message || (Array.isArray(err?.response?.data) ? err.response.data.map((e: any) => e.message).join(', ') : '') || err?.message || 'Failed to save ticket type';
    showError(msg);
  } finally {
    submittingTT.value = false;
  }
}

function handleEditTicketType(eventId: string, tt: TicketType) {
  editingTicketType.value = tt;
  ttForm.value = { name: tt.name, price: tt.price, description: tt.description || '', quantity: tt.quantity || null, isDefault: tt.isDefault };
  ticketTypeFormEventId.value = eventId;
  showTicketTypeForm.value = true;
}

async function handleToggleTicketType(eventId: string, tt: TicketType) {
  clearMessages();
  try {
    await ticketsStore.toggleTicketType(props.store.id, eventId, tt.id);
    await loadTicketTypes(eventId);
    showSuccess(tt.ticketTypeState === 'Active' ? t('tickets.ticket_type_disabled') : t('tickets.ticket_type_activated'));
  } catch (err: any) {
    showError(err?.response?.data?.message || err?.message || 'Failed to toggle ticket type');
  }
}

async function handleDeleteTicketType(eventId: string, tt: TicketType) {
  if (!confirm(t('tickets.delete_ticket_type_confirm', { name: tt.name }))) return;
  clearMessages();
  try {
    await ticketsStore.deleteTicketType(props.store.id, eventId, tt.id);
    await loadTicketTypes(eventId);
    showSuccess(t('tickets.ticket_type_deleted'));
  } catch (err: any) {
    showError(err?.response?.data?.message || err?.message || 'Failed to delete ticket type');
  }
}

// ── Tickets Actions ─────────────────────────────

async function loadTickets(eventId: string, search?: string) {
  loadingTickets.value = true;
  try {
    eventTickets.value = await ticketsStore.fetchTickets(props.store.id, eventId, search || undefined);
  } catch (err: any) {
    eventTickets.value = [];
    showError(err?.response?.data?.message || err?.message || 'Failed to load tickets');
  } finally {
    loadingTickets.value = false;
  }
}

function loadTicketsIfNeeded(eventId: string) {
  if (eventTickets.value.length === 0 && !loadingTickets.value) {
    loadTickets(eventId, ticketSearch.value || undefined);
  }
}

function debouncedSearchTickets(eventId: string) {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadTickets(eventId, ticketSearch.value || undefined);
  }, 400);
}

async function handleCheckIn(eventId: string, ticket: Ticket) {
  if (!confirm(t('tickets.checkin_confirm', { name: `${ticket.firstName} ${ticket.lastName}`, number: ticket.ticketNumber }))) return;
  clearMessages();
  try {
    await ticketsStore.checkInTicket(props.store.id, eventId, ticket.ticketNumber);
    showSuccess(t('tickets.checkin_success', { number: ticket.ticketNumber }));
    await loadTickets(eventId, ticketSearch.value || undefined);
  } catch (err: any) {
    showError(err?.response?.data?.message || err?.message || 'Failed to check in ticket');
  }
}

function getCheckInUrl(event: TicketEvent): string {
  const storeId = props.store?.btcpay_store_id;
  if (!btcPayUrl.value || !storeId) return '#';
  return `${btcPayUrl.value}/plugins/${storeId}/ticketevent/${event.id}/tickettype/ticket-checkin`;
}

function openPanelCheckIn(event: TicketEvent) {
  window.location.href = `/stores/${props.store.id}/ticket-check-in/${event.id}`;
}

function exportTicketsCsv(event: TicketEvent) {
  if (eventTickets.value.length === 0) return;

  const headers = [
    'Purchase Date',
    'Ticket Number',
    'First Name',
    'Last Name',
    'Email',
    'Ticket Tier',
    'Amount',
    'Currency',
    'Attended Event',
  ];

  const escCsv = (val: string) => {
    if (val.includes(',') || val.includes('"') || val.includes('\n')) {
      return `"${val.replace(/"/g, '""')}"`;
    }
    return val;
  };

  const formatCsvDate = (dateStr: string): string => {
    try {
      const d = new Date(dateStr);
      const pad = (n: number) => n.toString().padStart(2, '0');
      return `${pad(d.getMonth() + 1)}/${pad(d.getDate())}/${d.getFullYear().toString().slice(-2)} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    } catch { return dateStr; }
  };

  const rows = eventTickets.value.map(ticket => [
    formatCsvDate(ticket.createdAt),
    escCsv(shortTicketNumber(ticket.ticketNumber)),
    escCsv(ticket.firstName),
    escCsv(ticket.lastName),
    escCsv(ticket.email),
    escCsv(ticket.ticketTypeName),
    `${ticket.amount} ${event.currency || ''}`.trim(),
    escCsv(event.currency || ''),
    ticket.checkedIn ? 'True' : 'False',
  ]);

  const csvContent = [headers.join(','), ...rows.map((r: string[]) => r.join(','))].join('\n');
  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `tickets-${event.title.replace(/[^a-zA-Z0-9]/g, '_')}-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

// ── Lifecycle ───────────────────────────────────
onMounted(() => {
  loadEvents();
  loadBtcPayUrl();
});
</script>

<style scoped>
.input-field {
  @apply block w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all;
}
.input-field-sm {
  @apply block w-full px-3 py-2 text-sm bg-gray-900 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all;
}
.btn-primary {
  @apply inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg shadow-indigo-600/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all hover:scale-105;
}
.bg-gray-850 {
  background-color: rgb(30 32 38);
}
</style>
