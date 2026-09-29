<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">{{ examPaper?.title }}</h1>
      <div class="text-lg" v-if="!loading">
        剩余时间:
        <span class="font-mono font-bold" :class="{'text-red-600': timeRemaining < 60}">
          {{ formatTime(timeRemaining) }}
        </span>
      </div>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <!-- 开考前：草稿纸空白页拍照门禁 -->
    <div v-else-if="needsScratch && !beforeStartTaken && !gateHandled" class="max-w-xl mx-auto">
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="text-center mb-5">
          <div class="mx-auto w-14 h-14 rounded-full bg-amber-100 flex items-center justify-center mb-3">
            <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <h2 class="text-lg font-bold text-gray-900">开考前请先拍摄空白草稿纸</h2>
          <p class="text-sm text-gray-500 mt-2 leading-relaxed">
            本场考试（数学/计算类）允许使用纸质草稿纸。按监考要求，开考前需拍摄一张
            <span class="font-semibold text-amber-700">空白草稿页</span> 留存；
            交卷前还需拍摄<span class="font-semibold text-amber-700">草稿最终页</span>。
            未完成拍照将无法交卷。
          </p>
        </div>

        <ScratchPaperCapture :uploading="uploadingPhase === 'before_start'" @captured="(p) => uploadPhoto('before_start', p)" />

        <div class="mt-4 text-center">
          <button
            type="button"
            class="text-sm text-gray-500 underline hover:text-gray-700"
            @click="askTeacherAtGate"
          >
            无法拍照？申请交给监考老师处理
          </button>
        </div>

        <div class="mt-3">
          <router-link to="/exams" class="block text-center text-sm text-gray-400 hover:text-gray-600">
            返回考试列表（考试计时尚未开始）
          </router-link>
        </div>
      </div>
    </div>

    <!-- 门禁处理中（已交老师） -->
    <div v-else-if="needsScratch && gateHandled && !beforeStartTaken" class="max-w-md mx-auto text-center py-10">
      <div class="bg-white rounded-xl shadow p-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-amber-500 mx-auto mb-4"></div>
        <h2 class="text-lg font-bold text-gray-900">等待监考老师处理</h2>
        <p class="text-sm text-gray-500 mt-2">
          您的开考前空白草稿页拍照异常已提交，请等待老师审批。
          审批通过后可正常答题交卷；若被驳回，需补拍照片。
        </p>
        <button @click="refreshStatus" class="mt-4 text-sm text-indigo-600 hover:underline">刷新审批状态</button>
      </div>
    </div>

    <!-- 答题界面 -->
    <template v-else>
      <!-- 教师驳回提示 -->
      <div v-if="examRecord?.status === 'scratch_pending' && examRecord?.scratch_exception_status === 'rejected'"
           class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 text-sm">
        <p class="font-semibold">监考老师已驳回本次交卷，请按要求补拍草稿纸照片后重新提交。</p>
        <p v-if="examRecord?.scratch_exception_remark" class="mt-1 text-red-700">
          教师备注：{{ examRecord.scratch_exception_remark }}
        </p>
      </div>

      <!-- 草稿纸状态条 -->
      <ScratchPhotoPanel
        v-if="needsScratch"
        :status="scratchStatus"
        :exam-started="true"
        @start-capture="openCapture"
      />

      <div v-if="questions.length > 0" class="space-y-8">
        <div v-for="(question, index) in questions" :key="question.id" class="bg-white rounded-lg shadow p-6">
          <div class="flex items-start mb-4">
            <span class="bg-indigo-100 text-indigo-800 text-sm font-medium px-2.5 py-0.5 rounded mr-3">{{ index + 1 }}</span>
            <div class="flex-1">
              <h3 class="text-lg font-medium text-gray-900 mb-2">{{ question.title }}</h3>
              <p class="text-sm text-gray-500 mb-3">分值: {{ question.score }}分 | 题型: {{ questionTypeLabel(question.type) }}</p>
              <div class="space-y-2">
                <!-- 单选题 -->
                <template v-if="question.type === 'single_choice'">
                  <label v-for="(label, key) in question.options" :key="key" class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === key}">
                    <input type="radio" :name="'question_' + question.id" :value="key" v-model="answers[question.id]" @change="markAnswered(question.id)" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <span class="ml-3">{{ key }}. {{ label }}</span>
                  </label>
                </template>
                <!-- 判断题 -->
                <template v-else-if="question.type === 'true_false'">
                  <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === 'true'}">
                    <input type="radio" :name="'question_' + question.id" value="true" v-model="answers[question.id]" @change="markAnswered(question.id)" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <span class="ml-3">正确</span>
                  </label>
                  <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === 'false'}">
                    <input type="radio" :name="'question_' + question.id" value="false" v-model="answers[question.id]" @change="markAnswered(question.id)" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <span class="ml-3">错误</span>
                  </label>
                </template>
                <!-- 多选题 -->
                <template v-else-if="question.type === 'multiple_choice'">
                  <label v-for="(label, key) in question.options" :key="key" class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': (answers[question.id] || []).includes(key)}">
                    <input type="checkbox" :value="key" @change="toggleMultipleChoice(question.id, key)" :checked="(answers[question.id] || []).includes(key)" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <span class="ml-3">{{ key }}. {{ label }}</span>
                  </label>
                </template>
                <!-- 填空题/问答题 -->
                <template v-else>
                  <textarea v-model="answers[question.id]" @input="markAnswered(question.id)" rows="3" class="w-full border border-gray-300 rounded-md p-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="请输入答案"></textarea>
                </template>
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-between">
          <router-link to="/exams" class="bg-gray-300 text-gray-700 py-2 px-4 rounded hover:bg-gray-400">返回</router-link>
          <button @click="onSubmitClick" :disabled="submitting" class="bg-indigo-600 text-white py-2 px-6 rounded hover:bg-indigo-700 disabled:opacity-50">
            {{ submitting ? '提交中...' : '提交答卷' }}
          </button>
        </div>
      </div>
    </template>

    <!-- 拍照弹窗 -->
    <Teleport to="body">
      <div v-if="capturePhase" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/70" @click="closeCapture"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
          <h3 class="text-base font-bold text-gray-900 mb-1">
            {{ capturePhase === 'before_submit' ? '交卷前拍摄 · 草稿纸最终页' : '补拍 · 草稿纸空白页' }}
          </h3>
          <p class="text-xs text-gray-500 mb-4">
            {{ capturePhase === 'before_submit'
              ? '请把本场使用的所有草稿纸按顺序整理，拍摄最终页面（含全部关键演算）。'
              : '请拍摄一张空白草稿纸页面。' }}
          </p>
          <ScratchPaperCapture
            :key="captureKey"
            :uploading="uploadingPhase === capturePhase"
            @captured="onCapturedFromModal"
          />
          <div class="mt-3 flex flex-col gap-2">
            <button
              type="button"
              class="w-full text-sm text-amber-600 hover:text-amber-700 hover:underline"
              @click="openTeacherFromCapture"
            >
              无法拍照？交给监考老师处理
            </button>
            <button class="w-full text-sm text-gray-400 hover:text-gray-600" @click="closeCapture">取消</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 交老师处理弹窗 -->
    <Teleport to="body">
      <div v-if="showTeacherDialog" class="fixed inset-0 z-[85] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/70" @click="showTeacherDialog = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
          <h3 class="text-base font-bold text-gray-900 mb-2">交给监考老师处理</h3>
          <p class="text-sm text-gray-500 mb-3">
            提交后答卷将暂挂，监考老师核对情况后决定放行或要求补拍。请说明漏拍/无法拍照的原因：
          </p>
          <textarea
            v-model="exceptionReason"
            rows="3"
            class="w-full border border-gray-300 rounded-md p-3 text-sm focus:ring-indigo-500 focus:border-indigo-500"
            placeholder="例如：摄像头故障、草稿纸在开始铃响前已写姓名等"
          ></textarea>
          <div class="flex gap-3 mt-4">
            <button class="flex-1 border border-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-50" @click="showTeacherDialog = false">取消</button>
            <button
              class="flex-1 bg-amber-600 text-white py-2 rounded-lg hover:bg-amber-700 disabled:opacity-50"
              :disabled="submitting || exceptionReason.trim().length < 5"
              @click="confirmHandToTeacher"
            >
              {{ submitting ? '提交中...' : '确认交给老师' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../api'
import { useModal } from '../../composables/useModal'
import ScratchPaperCapture from '../../components/ScratchPaperCapture.vue'
import ScratchPhotoPanel from '../../components/ScratchPhotoPanel.vue'

const route = useRoute()
const router = useRouter()
const { alert } = useModal()
const examPaper = ref(null)
const examRecord = ref(null)
const questions = ref([])
const answers = ref({})
const answeredAtMap = ref({})
const loading = ref(true)
const submitting = ref(false)
const timeRemaining = ref(0)
let timer = null

// 草稿纸相关状态
const scratchStatus = ref(null)
const capturePhase = ref(null)
const captureKey = ref(0)
const uploadingPhase = ref(null)
const showTeacherDialog = ref(false)
const exceptionReason = ref('')
const gateHandled = ref(false) // 开考前异常已交老师
const pendingContext = ref(null) // 'gate' | 'submit'

const needsScratch = computed(() => !!examPaper.value?.allow_scratch_paper)
const beforeStartTaken = computed(() =>
  (scratchStatus.value?.photos || []).some(p => p.phase === 'before_start')
)
const beforeSubmitTaken = computed(() =>
  (scratchStatus.value?.photos || []).some(p => p.phase === 'before_submit')
)
const scratchComplete = computed(() => !!scratchStatus.value?.complete)

onMounted(async () => {
  try {
    const response = await api.get(`/exams/${route.params.id}/questions`)
    examPaper.value = response.data.exam_paper
    examRecord.value = response.data.exam_record
    questions.value = response.data.questions
    scratchStatus.value = examRecord.value.scratch_status || null

    const status = examRecord.value.status
    const exStatus = examRecord.value.scratch_exception_status

    // 异常挂起状态：等待页 / 驳回补拍页 / 放行直接交卷
    if (status === 'scratch_pending') {
      loading.value = false
      if (exStatus === 'approved') {
        // 教师已放行：直接交卷计分
        await doFinalize('submit-approved')
      } else if (exStatus === 'rejected') {
        // 被驳回：留在考试页补拍，不启动倒计时（考试时间已结束）
      } else {
        // pending：等待教师处理
        gateHandled.value = true
      }
      return
    }

    if (!needsScratch.value || beforeStartTaken.value) {
      startTimer()
    }
  } catch (e) {
    alert(e.response?.data?.message || '获取考试信息失败', '考试加载失败', 'error')
    router.push('/exams')
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

const startTimer = () => {
  if (timer) return
  timeRemaining.value = examPaper.value.total_time * 60
  timer = setInterval(() => {
    if (timeRemaining.value > 0) {
      timeRemaining.value--
    } else {
      clearInterval(timer)
      onTimeoutSubmit()
    }
  }, 1000)
}

// 时间到：照片齐全则自动交卷；缺照片则拦截并引导补拍/交老师
const onTimeoutSubmit = async () => {
  if (needsScratch.value && !scratchComplete.value) {
    alert(
      '考试时间已到，但草稿纸照片未拍齐，系统未自动交卷。请立即补拍后点"提交答卷"，或选择交给老师处理。',
      '草稿照片缺失，无法交卷',
      'warning'
    )
    return
  }
  await doFinalize('submit')
}

const formatTime = (seconds) => {
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

const questionTypeLabel = (type) => {
  const labels = {
    single_choice: '单选题',
    multiple_choice: '多选题',
    true_false: '判断题',
    fill_blank: '填空题',
    essay: '问答题'
  }
  return labels[type] || type
}

const markAnswered = (questionId) => {
  // 记录最近作答时间，交卷时随答案一起上传，用于监考回放与照片时间对应
  answeredAtMap.value[questionId] = new Date().toISOString()
}

const toggleMultipleChoice = (questionId, key) => {
  if (!answers.value[questionId]) {
    answers.value[questionId] = []
  }
  const index = answers.value[questionId].indexOf(key)
  if (index === -1) {
    answers.value[questionId].push(key)
  } else {
    answers.value[questionId].splice(index, 1)
  }
  markAnswered(questionId)
}

// ============ 草稿纸拍照 ============
const openCapture = (phase) => {
  capturePhase.value = phase
  captureKey.value++
}

const closeCapture = () => {
  capturePhase.value = null
}

const openTeacherFromCapture = () => {
  pendingContext.value = capturePhase.value === 'before_start' ? 'gate' : 'submit'
  capturePhase.value = null
  showTeacherDialog.value = true
}

const onCapturedFromModal = async (payload) => {
  await uploadPhoto(capturePhase.value, payload)
}

const uploadPhoto = async (phase, payload) => {
  uploadingPhase.value = phase
  try {
    const fd = new FormData()
    fd.append('exam_record_id', examRecord.value.id)
    fd.append('phase', phase)
    fd.append('taken_at', payload.takenAt)
    const ext = payload.file.type?.includes('png') ? 'png'
      : payload.file.type?.includes('webp') ? 'webp' : 'jpg'
    fd.append('photo', payload.file, `scratch_${phase}_${Date.now()}.${ext}`)

    const res = await api.post(`/exams/${route.params.id}/scratch-photos`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 60000
    })

    scratchStatus.value = res.data.scratch_status
    closeCapture()

    // 开考前空白页上传成功 → 开始答题与计时
    if (phase === 'before_start' && !timer) {
      startTimer()
    }
  } catch (e) {
    alert(e.response?.data?.message || '照片上传失败，请重试', '上传失败', 'error')
  } finally {
    uploadingPhase.value = null
  }
}

const askTeacherAtGate = () => {
  pendingContext.value = 'gate'
  showTeacherDialog.value = true
}

const confirmHandToTeacher = async () => {
  await doFinalize('submit', {
    hand_to_teacher: true,
    exception_reason: exceptionReason.value.trim()
  })
}

const refreshStatus = async () => {
  try {
    const res = await api.get(`/exams/${route.params.id}/scratch-status`)
    examRecord.value = {
      ...examRecord.value,
      status: res.data.record_status,
      scratch_exception_status: res.data.scratch_exception_status
    }
    scratchStatus.value = res.data.scratch_status

    if (res.data.scratch_exception_status === 'approved') {
      await doFinalize('submit-approved')
    } else if (res.data.scratch_exception_status === 'rejected') {
      gateHandled.value = false
    }
  } catch (e) {
    // ignore
  }
}

// ============ 交卷 ============
const onSubmitClick = async () => {
  // 已被驳回、补拍完成后重新交卷
  if (examRecord.value.status === 'scratch_pending'
      && examRecord.value.scratch_exception_status === 'rejected') {
    await doFinalize('resubmit')
    return
  }

  // 草稿纸考试：先引导拍最终页
  if (needsScratch.value && !beforeSubmitTaken.value) {
    openCapture('before_submit')
    return
  }
  await doFinalize('submit')
}

const buildAnswerData = () =>
  Object.entries(answers.value)
    .filter(([, v]) => v !== undefined && v !== null && v !== '' && !(Array.isArray(v) && v.length === 0))
    .map(([questionId, answer]) => ({
      question_id: parseInt(questionId),
      answer: Array.isArray(answer) ? answer.join(',') : String(answer),
      answered_at: answeredAtMap.value[questionId] || new Date().toISOString()
    }))

const endpointFor = (mode) => {
  if (mode === 'submit-approved') return 'submit-approved'
  if (mode === 'resubmit') return 'resubmit'
  return 'submit'
}

const doFinalize = async (mode, extra = {}) => {
  if (submitting.value) return
  submitting.value = true
  try {
    const payload = {
      exam_record_id: examRecord.value.id,
      answers: buildAnswerData(),
      ...extra
    }

    const response = await api.post(`/exams/${route.params.id}/${endpointFor(mode)}`, payload)
    showTeacherDialog.value = false
    alert(`考试完成！得分: ${response.data.score}`, '考试完成', 'success')
    router.push('/records')
  } catch (e) {
    const code = e.response?.data?.error_code
    if (code === 'SCRATCH_PHOTO_MISSING') {
      const missing = (e.response.data.missing_labels || []).join('、')
      alert(
        `还差：${missing}。请先补拍草稿照片后再交卷；如确实无法拍照，可选择交给老师处理。`,
        '草稿照片缺失，无法交卷',
        'warning'
      )
      const missingPhases = e.response.data.missing_phases || []
      setTimeout(() => promptRetakeOrTeacher(missingPhases), 350)
    } else if (code === 'SCRATCH_PENDING_TEACHER') {
      showTeacherDialog.value = false
      examRecord.value.status = 'scratch_pending'
      examRecord.value.scratch_exception_status = 'pending'
      gateHandled.value = true
      alert('已提交给监考老师，请等待处理结果。', '已交老师处理', 'warning')
      if (pendingContext.value === 'submit') {
        router.push('/records')
      }
    } else {
      alert(e.response?.data?.message || '提交失败', '提交失败', 'error')
    }
  } finally {
    submitting.value = false
  }
}

// 兼容计时器自动交卷入口
const submitExam = async (isAutoTimeout = false, handToTeacher = false) => {
  if (handToTeacher) {
    await doFinalize('submit', {
      hand_to_teacher: true,
      exception_reason: exceptionReason.value.trim()
    })
    return
  }
  await onSubmitClick()
}

const promptRetakeOrTeacher = (missingPhases) => {
  pendingContext.value = 'submit'
  if (missingPhases.includes('before_submit')) {
    openCapture('before_submit')
  } else if (missingPhases.includes('before_start')) {
    openCapture('before_start')
  }
}
</script>
