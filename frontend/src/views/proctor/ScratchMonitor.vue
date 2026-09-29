<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">草稿纸监考回放</h1>
      <div class="flex gap-2">
        <button
          class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
          :class="tab === 'exceptions' ? 'bg-amber-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'"
          @click="switchTab('exceptions')"
        >
          异常处理
          <span v-if="pendingCount" class="ml-1 bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5">{{ pendingCount }}</span>
        </button>
        <button
          class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
          :class="tab === 'records' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'"
          @click="switchTab('records')"
        >
          监考回放
        </button>
      </div>
    </div>

    <!-- 待处理异常 -->
    <div v-if="tab === 'exceptions'">
      <div v-if="loadingExceptions" class="text-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-amber-500 mx-auto"></div>
      </div>
      <div v-else-if="exceptions.length === 0" class="text-center py-12 text-gray-500 bg-white rounded-lg shadow">
        暂无待处理的草稿纸异常
      </div>
      <div v-else class="space-y-4">
        <div v-for="rec in exceptions" :key="rec.id" class="bg-white rounded-lg shadow p-5 border-l-4 border-amber-400">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="font-semibold text-gray-900">
                {{ rec.user?.real_name || rec.user?.username }}
                <span class="text-sm font-normal text-gray-500">({{ rec.user?.email }})</span>
              </p>
              <p class="text-sm text-gray-600 mt-1">试卷：{{ rec.exam_paper?.title }}</p>
              <p class="text-xs text-gray-400 mt-1">开始 {{ formatTime(rec.start_time) }} · 挂起 {{ formatTime(rec.end_time) }}</p>
            </div>
            <button class="text-sm text-indigo-600 hover:underline" @click="openReview(rec.id)">查看监考回放</button>
          </div>
          <div class="mt-3 bg-amber-50 rounded p-3 text-sm text-amber-900">
            <span class="font-medium">学生说明：</span>{{ rec.scratch_exception_reason || '（未填写）' }}
          </div>
          <div class="mt-3 flex gap-3">
            <button
              :disabled="handlingId === rec.id"
              class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 text-sm disabled:opacity-50"
              @click="handleException(rec, 'approve')"
            >
              放行（学生可正常交卷计分）
            </button>
            <button
              :disabled="handlingId === rec.id"
              class="flex-1 bg-white border border-red-300 text-red-600 py-2 rounded-lg hover:bg-red-50 text-sm disabled:opacity-50"
              @click="handleException(rec, 'reject')"
            >
              驳回（要求补拍）
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 监考回放：记录列表 -->
    <div v-else-if="tab === 'records' && !reviewData">
      <div class="bg-white rounded-lg shadow p-4 mb-4 flex flex-wrap gap-3 items-center">
        <label class="text-sm text-gray-600">只看需草稿纸的考试</label>
        <input type="checkbox" v-model="onlyScratch" class="h-4 w-4 text-indigo-600 rounded" @change="loadRecords">
        <div class="flex-1"></div>
        <button @click="loadRecords" class="text-sm text-indigo-600 hover:underline">刷新</button>
      </div>
      <div v-if="loadingRecords" class="text-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
      </div>
      <div v-else-if="records.length === 0" class="text-center py-12 text-gray-500 bg-white rounded-lg shadow">
        暂无考试记录
      </div>
      <div v-else class="bg-white shadow overflow-hidden rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">学生</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">试卷</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">草稿照片</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="rec in records" :key="rec.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm">{{ rec.user?.real_name || rec.user?.username || rec.user_id }}</td>
              <td class="px-4 py-3 text-sm">{{ rec.exam_paper?.title }}</td>
              <td class="px-4 py-3 text-sm">
                <span class="px-2 py-0.5 rounded-full text-xs" :class="recordStatusClass(rec.status)">
                  {{ recordStatusLabel(rec.status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm">
                <span v-if="rec.exam_paper?.allow_scratch_paper" :class="(rec.scratch_photos || []).length === 2 ? 'text-green-600' : 'text-amber-600'">
                  {{ (rec.scratch_photos || []).length }}/2
                </span>
                <span v-else class="text-gray-400">不需要</span>
              </td>
              <td class="px-4 py-3 text-sm">
                <button class="text-indigo-600 hover:underline" @click="openReview(rec.id)">回放</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 监考回放详情 -->
    <div v-else-if="reviewData" class="space-y-4">
      <button class="text-sm text-indigo-600 hover:underline" @click="reviewData = null">← 返回列表</button>

      <div class="bg-white rounded-lg shadow p-5">
        <div class="flex flex-wrap justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold text-gray-900">
              {{ reviewData.record.student?.real_name || reviewData.record.student?.username }}
              · {{ reviewData.record.exam_paper?.title }}
            </h2>
            <p class="text-sm text-gray-500 mt-1">
              开始 {{ formatTime(reviewData.record.start_time) }} ｜ 结束 {{ formatTime(reviewData.record.end_time) }} ｜
              得分 {{ reviewData.record.score ?? '—' }}
            </p>
          </div>
          <span class="px-3 py-1 rounded-full text-sm h-fit" :class="recordStatusClass(reviewData.record.status)">
            {{ reviewData.record.status_label }}
          </span>
        </div>
        <div v-if="reviewData.record.scratch_exception_status !== 'none'" class="mt-3 text-sm bg-gray-50 rounded p-3 space-y-1">
          <p><span class="font-medium">异常状态：</span>{{ reviewData.record.scratch_exception_status_label }}</p>
          <p v-if="reviewData.record.scratch_exception_reason"><span class="font-medium">学生说明：</span>{{ reviewData.record.scratch_exception_reason }}</p>
          <p v-if="reviewData.record.scratch_exception_remark"><span class="font-medium">教师备注：</span>{{ reviewData.record.scratch_exception_remark }}</p>
          <p v-if="reviewData.record.exception_handler">
            <span class="font-medium">处理人：</span>{{ reviewData.record.exception_handler.real_name || reviewData.record.exception_handler.username }}
            · {{ formatTime(reviewData.record.scratch_exception_handled_at) }}
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- 两张照片 -->
        <div class="bg-white rounded-lg shadow p-5 space-y-4">
          <h3 class="font-semibold text-gray-900">草稿纸照片</h3>
          <div v-for="photo in reviewData.scratch_photos" :key="photo.id" class="border rounded-lg p-3">
            <div class="flex justify-between items-center mb-2">
              <span class="text-sm font-medium" :class="photo.phase === 'before_start' ? 'text-indigo-700' : 'text-emerald-700'">
                {{ photo.phase === 'before_start' ? '① 开考前 · 空白页' : '② 交卷前 · 最终页' }}
              </span>
              <span class="text-xs text-gray-400">{{ formatTime(photo.taken_at) }}</span>
            </div>
            <img
              :src="photo.data_uri || photo.url"
              :alt="photo.phase_label"
              class="w-full rounded border border-gray-200 cursor-zoom-in"
              @click="previewUrl = photo.data_uri || photo.url"
            />
          </div>
          <div v-if="reviewData.scratch_photos.length === 0" class="text-sm text-gray-400 py-6 text-center">
            无草稿纸照片
          </div>
        </div>

        <!-- 时间线：照片 + 答题时间对应 -->
        <div class="bg-white rounded-lg shadow p-5">
          <h3 class="font-semibold text-gray-900 mb-4">监考时间线（照片 ↔ 答题时间对应）</h3>
          <div class="relative pl-6">
            <div class="absolute left-2 top-1 bottom-1 w-px bg-gray-200"></div>
            <div v-for="(event, idx) in reviewData.timeline" :key="idx" class="relative mb-4">
              <div
                class="absolute -left-[18px] top-1 w-2.5 h-2.5 rounded-full ring-4 ring-white"
                :class="event.type === 'scratch_photo'
                  ? (event.phase === 'before_start' ? 'bg-indigo-500' : 'bg-emerald-500')
                  : 'bg-gray-400'"
              ></div>
              <div class="text-xs text-gray-400">{{ formatTime(event.time) }}</div>
              <template v-if="event.type === 'scratch_photo'">
                <p class="text-sm font-medium" :class="event.phase === 'before_start' ? 'text-indigo-700' : 'text-emerald-700'">
                  📷 {{ event.phase_label }}
                </p>
                <img
                  :src="event.photo_data_uri || event.photo_url"
                  class="mt-1 w-40 rounded border border-gray-200 cursor-zoom-in"
                  @click="previewUrl = event.photo_data_uri || event.photo_url"
                />
              </template>
              <template v-else>
                <p class="text-sm text-gray-700">
                  ✍️ 作答：<span class="font-medium">{{ event.question_title || ('题目#' + event.question_id) }}</span>
                  <span :class="event.is_correct ? 'text-green-600' : 'text-red-500'" class="ml-1 text-xs">
                    {{ event.is_correct ? '✓' : '✗' }} {{ event.score }}分
                  </span>
                </p>
                <p class="text-xs text-gray-400 truncate">{{ event.answer_excerpt }}</p>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 图片大图预览 -->
    <Teleport to="body">
      <div v-if="previewUrl" class="fixed inset-0 z-[95] bg-black/85 flex items-center justify-center p-6" @click="previewUrl = ''">
        <img :src="previewUrl" class="max-w-full max-h-full rounded-lg shadow-2xl" />
        <button class="absolute top-5 right-6 text-white text-3xl">&times;</button>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'

const tab = ref('exceptions')
const exceptions = ref([])
const records = ref([])
const pendingCount = ref(0)
const loadingExceptions = ref(false)
const loadingRecords = ref(false)
const handlingId = ref(null)
const reviewData = ref(null)
const previewUrl = ref('')
const onlyScratch = ref(true)

onMounted(() => {
  loadExceptions()
})

const switchTab = (t) => {
  tab.value = t
  if (t === 'exceptions') loadExceptions()
  if (t === 'records') loadRecords()
}

const loadExceptions = async () => {
  loadingExceptions.value = true
  try {
    const res = await api.get('/exams/scratch-exceptions')
    exceptions.value = res.data.records.data || []
    pendingCount.value = exceptions.value.length
  } catch (e) {
    // 错误由全局拦截器提示
  } finally {
    loadingExceptions.value = false
  }
}

const loadRecords = async () => {
  loadingRecords.value = true
  reviewData.value = null
  try {
    const res = await api.get('/scores/scratch-records', {
      params: { only_scratch: onlyScratch.value ? 1 : 0 }
    })
    records.value = res.data.records.data || res.data.records || []
  } catch (e) {
    records.value = []
  } finally {
    loadingRecords.value = false
  }
}

const openReview = async (recordId) => {
  try {
    const res = await api.get(`/exams/records/${recordId}/proctor-review`)
    reviewData.value = res.data
  } catch (e) {
    // global handler
  }
}

const handleException = async (rec, action) => {
  const remark = prompt(action === 'approve'
    ? '确认放行？可填写备注（可选）：'
    : '请填写驳回原因（将提示学生补拍）：', action === 'reject' ? '草稿纸照片缺失，请补拍完整后重新交卷。' : '')
  if (remark === null) return

  handlingId.value = rec.id
  try {
    await api.post(`/exams/records/${rec.id}/scratch-exception`, { action, remark })
    await loadExceptions()
  } catch (e) {
    // global handler
  } finally {
    handlingId.value = null
  }
}

const formatTime = (t) => t ? new Date(t).toLocaleString() : '—'

const recordStatusLabel = (s) => ({
  in_progress: '进行中',
  submitted: '已提交',
  graded: '已评分',
  scratch_pending: '草稿待处理'
}[s] || s)

const recordStatusClass = (s) => ({
  in_progress: 'bg-blue-100 text-blue-800',
  submitted: 'bg-gray-100 text-gray-800',
  graded: 'bg-green-100 text-green-800',
  scratch_pending: 'bg-amber-100 text-amber-800'
}[s] || 'bg-gray-100 text-gray-800')
</script>
