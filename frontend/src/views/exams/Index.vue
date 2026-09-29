<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">在线考试</h1>
    </div>
    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="examPapers.length === 0" class="text-center py-8 text-gray-500">
      暂无可用试卷
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="paper in examPapers" :key="paper.id" class="bg-white rounded-lg shadow p-6">
        <div class="flex items-start justify-between mb-2">
          <h3 class="text-lg font-semibold text-gray-900">{{ paper.title }}</h3>
          <span v-if="paper.scratch_paper_required" class="flex-shrink-0 ml-2 inline-flex items-center text-xs font-semibold text-amber-800 bg-amber-100 px-2 py-1 rounded-full" title="本场考试允许使用纸质草稿，开考前与交卷前需拍照留存">
            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
            需草稿纸拍照
          </span>
        </div>
        <p class="text-gray-600 text-sm mb-4">{{ paper.description || '暂无描述' }}</p>
        <div class="space-y-2 text-sm text-gray-500">
          <div class="flex justify-between">
            <span>题目数量</span>
            <span>{{ paper.question_count }} 题</span>
          </div>
          <div class="flex justify-between">
            <span>总分</span>
            <span>{{ paper.total_score }} 分</span>
          </div>
          <div class="flex justify-between">
            <span>考试时长</span>
            <span>{{ paper.total_time }} 分钟</span>
          </div>
        </div>
        <button @click="startExam(paper)" class="mt-4 w-full bg-indigo-600 text-white py-2 px-4 rounded hover:bg-indigo-700 transition-colors">
          开始考试
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'
import { useModal } from '../../composables/useModal'

const router = useRouter()
const { alert } = useModal()
const examPapers = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const response = await api.get('/exams')
    examPapers.value = response.data.exam_papers.data
  } catch (e) {
    console.error('Failed to fetch exam papers:', e)
  } finally {
    loading.value = false
  }
})

const startExam = async (paper) => {
  try {
    const response = await api.post(`/exams/${paper.id}/start`)
    router.push(`/exams/${paper.id}`)
  } catch (e) {
    alert(e.response?.data?.message || '开始考试失败', '开始考试', 'error')
  }
}
</script>
