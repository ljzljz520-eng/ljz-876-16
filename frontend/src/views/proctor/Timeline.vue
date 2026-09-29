<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <button @click="$router.back()" class="text-sm text-gray-500 hover:text-indigo-600 mb-2">&larr; 返回</button>
        <h1 class="text-2xl font-bold text-gray-900">监考回放 · {{ examPaper?.title }}</h1>
        <p class="text-sm text-gray-500 mt-1">
          学生：{{ record?.user?.real_name || record?.user?.username }} ｜
          开考：{{ formatDateTime(record?.start_time) }} ｜
          时长：{{ examPaper?.total_time }} 分钟
        </p>
      </div>
      <div v-if="record?.scratch_waiver_status && record.scratch_waiver_status !== 'none'" class="text-sm px-3 py-1.5 rounded-full font-semibold"
        :class="waiverBadgeClass">
        {{ waiverBadgeText }}
      </div>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <template v-else>
      <!-- 完成度概览 -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-xs text-gray-500">空白草稿纸照片</p>
          <p class="text-2xl font-bold mt-1" :class="photoCount('pre') > 0 ? 'text-green-600' : 'text-red-500'">{{ photoCount('pre') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-xs text-gray-500">最终页面照片</p>
          <p class="text-2xl font-bold mt-1" :class="photoCount('final') > 0 ? 'text-green-600' : 'text-red-500'">{{ photoCount('final') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-xs text-gray-500">作答记录</p>
          <p class="text-2xl font-bold mt-1 text-indigo-600">{{ events.filter(e => e.type === 'answer').length }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-xs text-gray-500">最终得分</p>
          <p class="text-2xl font-bold mt-1 text-gray-900">{{ record?.score ?? '-' }}</p>
        </div>
      </div>

      <div v-if="record?.scratch_waiver_reason" class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm">
        <p class="font-semibold text-amber-800 mb-1">学生“交给老师处理”申请</p>
        <p class="text-gray-700">原因：{{ record.scratch_waiver_reason }}</p>
        <p v-if="record.scratch_waiver_note" class="text-gray-600 mt-1">老师备注：{{ record.scratch_waiver_note }}</p>
        <p v-if="record.waiver_handler" class="text-gray-400 text-xs mt-1">
          处理人：{{ record.waiver_handler.real_name || record.waiver_handler.username }} ｜ {{ formatDateTime(record.scratch_waiver_at) }}
        </p>
      </div>

      <!-- 照片区 -->
      <div v-if="photos.length" class="bg-white rounded-lg shadow p-5">
        <h2 class="text-base font-semibold text-gray-900 mb-4">草稿纸照片（按拍摄时间）</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="photo in photos" :key="photo.id" class="border rounded-lg overflow-hidden">
            <a :href="tokenizeUrl(photo.url)" target="_blank" rel="noopener">
              <img :src="tokenizeUrl(photo.url)" class="w-full h-44 object-cover hover:opacity-90" :alt="photo.phase_label">
            </a>
            <div class="p-2.5 text-xs text-gray-600 flex items-center justify-between">
              <span class="font-medium" :class="photo.phase === 'pre' ? 'text-amber-700' : 'text-indigo-700'">{{ photo.phase_label }}</span>
              <span>{{ formatElapsed(photo.elapsed_seconds) }}</span>
            </div>
            <div class="px-2.5 pb-2.5 text-xs text-gray-400">{{ formatDateTime(photo.created_at) }}</div>
          </div>
        </div>
      </div>

      <!-- 时间轴 -->
      <div class="bg-white rounded-lg shadow p-5">
        <h2 class="text-base font-semibold text-gray-900 mb-4">拍照 / 答题 时间轴</h2>
        <div v-if="events.length === 0" class="text-gray-400 text-sm">暂无事件</div>
        <ol class="relative border-l-2 border-gray-100 ml-2 space-y-5">
          <li v-for="(event, idx) in events" :key="idx" class="ml-5">
            <span class="absolute -left-[9px] mt-1 w-4 h-4 rounded-full ring-4 ring-white" :class="dotClass(event.type)"></span>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-mono text-gray-400">T+{{ formatElapsed(event.elapsed_seconds) }}</span>
              <span class="text-xs px-2 py-0.5 rounded-full font-semibold" :class="tagClass(event.type)">{{ event.label }}</span>
            </div>

            <div v-if="event.type === 'scratch_photo'" class="mt-2">
              <a :href="tokenizeUrl(event.photo.url)" target="_blank" rel="noopener">
                <img :src="tokenizeUrl(event.photo.url)" class="w-40 h-28 object-cover rounded border hover:opacity-90" alt="草稿纸照片">
              </a>
            </div>

            <div v-else-if="event.type === 'answer'" class="mt-1 text-sm text-gray-600">
              <span class="mr-2">{{ event.answer ? event.answer : '（未作答）' }}</span>
              <span v-if="event.is_correct !== null && event.at" class="text-xs" :class="event.is_correct ? 'text-green-600' : 'text-red-500'">
                {{ event.is_correct ? '✓ 正确' : '✗ 错误' }} · {{ event.score }} 分
              </span>
            </div>
          </li>
        </ol>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../api'

const route = useRoute()
const record = ref(null)
const examPaper = ref(null)
const events = ref([])
const photos = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get(`/exams/${route.params.paperId}/records/${route.params.recordId}/timeline`)
    record.value = data.record
    examPaper.value = data.exam_paper
    events.value = data.events
    photos.value = data.photos
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})

const tokenizeUrl = (url) => {
  const token = localStorage.getItem('token')
  if (!token) return url
  return url + (url.includes('?') ? '&' : '?') + 'token=' + encodeURIComponent(token)
}

const photoCount = (phase) => photos.value.filter((p) => p.phase === phase).length

const formatDateTime = (iso) => iso ? new Date(iso).toLocaleString() : '-'

const formatElapsed = (seconds) => {
  if (seconds === null || seconds === undefined) return '--:--'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
}

const dotClass = (type) => ({
  exam_start: 'bg-gray-400',
  exam_end: 'bg-gray-900',
  scratch_photo: 'bg-amber-500',
  answer: 'bg-indigo-500'
}[type] || 'bg-gray-300')

const tagClass = (type) => ({
  exam_start: 'bg-gray-100 text-gray-600',
  exam_end: 'bg-gray-900 text-white',
  scratch_photo: 'bg-amber-100 text-amber-800',
  answer: 'bg-indigo-100 text-indigo-800'
}[type] || 'bg-gray-100')

const waiverBadgeClass = computed(() => ({
  pending: 'bg-amber-100 text-amber-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-700'
}[record.value?.scratch_waiver_status] || 'bg-gray-100 text-gray-600'))

const waiverBadgeText = computed(() => ({
  pending: '免拍申请：待老师处理',
  approved: '免拍申请：老师已批准',
  rejected: '免拍申请：已驳回'
}[record.value?.scratch_waiver_status] || ''))
</script>
