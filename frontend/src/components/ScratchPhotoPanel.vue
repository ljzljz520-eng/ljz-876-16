<template>
  <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
    <div class="flex items-start gap-3">
      <svg class="w-6 h-6 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <div class="flex-1">
        <h3 class="text-sm font-semibold text-amber-900">草稿纸拍照留存</h3>
        <p class="text-xs text-amber-700 mt-1">
          本场考试允许使用纸质草稿纸。开考前须拍摄<span class="font-semibold">空白草稿页</span>，交卷前须拍摄<span class="font-semibold">最终页</span>，漏拍将无法交卷。
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
      <!-- 开考前 -->
      <div
        class="border rounded-lg p-3 bg-white"
        :class="beforePhoto ? 'border-green-300' : 'border-amber-300 border-dashed'"
      >
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-800">① 开考前 · 空白页</span>
          <span v-if="beforePhoto" class="text-xs text-green-600 font-medium">已上传</span>
          <span v-else-if="examStarted" class="text-xs text-red-500 font-medium">未拍（需老师处理）</span>
          <span v-else class="text-xs text-amber-600 font-medium">待拍摄</span>
        </div>
        <img
          v-if="beforePhoto"
          :src="photoUrl(beforePhoto)"
          alt="空白草稿页"
          class="w-full h-28 object-cover rounded border border-gray-200 mb-2"
        />
        <div v-else class="w-full h-28 rounded border border-dashed border-gray-300 flex items-center justify-center text-gray-400 text-xs mb-2">
          未拍摄
        </div>
        <p v-if="beforePhoto" class="text-[11px] text-gray-500">
          拍摄于 {{ formatTime(beforePhoto.taken_at) }}
        </p>
        <button
          v-if="!beforePhoto && !examStarted"
          type="button"
          @click="$emit('start-capture', 'before_start')"
          class="w-full text-sm py-1.5 rounded border border-amber-500 text-amber-700 hover:bg-amber-100"
        >
          立即拍摄空白页
        </button>
      </div>

      <!-- 交卷前 -->
      <div
        class="border rounded-lg p-3 bg-white"
        :class="submitPhoto ? 'border-green-300' : 'border-amber-300 border-dashed'"
      >
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-800">② 交卷前 · 最终页</span>
          <span v-if="submitPhoto" class="text-xs text-green-600 font-medium">已上传</span>
          <span v-else class="text-xs text-amber-600 font-medium">交卷前拍摄</span>
        </div>
        <img
          v-if="submitPhoto"
          :src="photoUrl(submitPhoto)"
          alt="草稿最终页"
          class="w-full h-28 object-cover rounded border border-gray-200 mb-2"
        />
        <div v-else class="w-full h-28 rounded border border-dashed border-gray-300 flex items-center justify-center text-gray-400 text-xs mb-2">
          交卷前拍摄
        </div>
        <p v-if="submitPhoto" class="text-[11px] text-gray-500">
          拍摄于 {{ formatTime(submitPhoto.taken_at) }}
        </p>
        <button
          v-if="!submitPhoto"
          type="button"
          @click="$emit('start-capture', 'before_submit')"
          class="w-full text-sm py-1.5 rounded border border-amber-500 text-amber-700 hover:bg-amber-100"
        >
          {{ examStarted ? '拍摄最终页' : '可先拍摄（建议交卷前重拍）' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: Object, default: null },
  examStarted: { type: Boolean, default: false }
})

defineEmits(['start-capture'])

const beforePhoto = computed(() =>
  (props.status?.photos || []).find(p => p.phase === 'before_start') || null
)
const submitPhoto = computed(() =>
  (props.status?.photos || []).find(p => p.phase === 'before_submit') || null
)

const photoUrl = (photo) => photo?.data_uri || photo?.url || ''

const formatTime = (t) => t ? new Date(t).toLocaleString() : ''
</script>
