<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">{{ examPaper?.title }}</h1>
      <div class="text-lg" v-if="canSeeQuestions">
        剩余时间: <span class="font-mono font-bold" :class="{'text-red-600': timeRemaining < 60}">{{ formatTime(timeRemaining) }}</span>
      </div>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <!-- ============ 开考前：空白草稿纸拍照闸门 ============ -->
    <div v-else-if="scratch?.required && !scratch.can_answer" class="max-w-2xl mx-auto">
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6">
        <div class="flex">
          <svg class="w-6 h-6 text-amber-500 flex-shrink-0 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
          <div class="text-sm text-amber-800">
            <template v-if="scratch.waiver_status === 'pending'">
              <p class="font-semibold mb-1">您的“交给老师处理”申请正在等待监考老师批准</p>
              <p>批准后本页面会自动放行；您也可以现在补拍空白草稿纸直接进入答题。</p>
            </template>
            <template v-else-if="scratch.waiver_status === 'rejected'">
              <p class="font-semibold mb-1">免拍申请未获批准，请立即补拍空白草稿纸</p>
              <p v-if="scratch.waiver_note">老师备注：{{ scratch.waiver_note }}</p>
            </template>
            <template v-else>
              <p class="font-semibold mb-1">本场考试允许使用纸质草稿，开考前需先拍照留存</p>
              <p>请将全部空白草稿纸摊平拍摄（每张纸的正反面/每一页都要拍到）。完成拍照前不能查看试题。</p>
            </template>
          </div>
        </div>
      </div>

      <ScratchPaperCapture
        v-if="scratch.waiver_status !== 'pending' || showRetakeWhilePending"
        :exam-paper-id="route.params.id"
        phase="pre"
        title="拍摄空白草稿纸"
        description="开考前留存，作为考试起始时间的监考依据"
        :history="phasePhotos('pre')"
        @uploaded="onPhotoUploaded"
      >
        <template #extra>
          <div class="border-t pt-4 flex items-center justify-between flex-wrap gap-3">
            <p class="text-xs text-gray-500">没有草稿纸 / 无法拍照？</p>
            <button v-if="scratch.waiver_status !== 'pending'" type="button" @click="openWaiver" class="text-sm text-indigo-600 font-semibold hover:underline">
              申请交给监考老师处理
            </button>
          </div>
        </template>
      </ScratchPaperCapture>

      <!-- 等待老师处理（同时仍允许现场补拍） -->
      <div v-if="scratch.waiver_status === 'pending'" class="bg-white rounded-xl shadow p-8 text-center mt-6">
        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-amber-500 mx-auto mb-4"></div>
        <p class="text-gray-700 font-medium">等待监考老师处理中…</p>
        <p class="text-sm text-gray-400 mt-2">申请原因：{{ scratch.waiver_reason }}</p>
        <button v-if="!showRetakeWhilePending" type="button" @click="showRetakeWhilePending = true" class="mt-4 text-sm text-indigo-600 hover:underline">我已拿到草稿纸，改为现场补拍</button>
      </div>
    </div>

    <!-- ============ 答题中 / 待最终拍照 ============ -->
    <template v-else-if="!loading && questions.length > 0">
      <!-- 草稿纸留存状态条 -->
      <div v-if="scratch?.required" class="bg-white rounded-lg shadow px-4 py-3 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-4 text-sm">
          <span class="inline-flex items-center" :class="scratch.pre_photo_taken ? 'text-green-700' : 'text-gray-400'">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
            空白纸已拍 ({{ scratch.pre_photo_count }})
          </span>
          <span class="inline-flex items-center" :class="scratch.final_photo_taken ? 'text-green-700' : 'text-gray-400'">
            <svg class="w-4 h-4 mr-1" :fill="scratch.final_photo_taken ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            最终页已拍 ({{ scratch.final_photo_count }})
          </span>
          <span v-if="scratch.waiver_status === 'pending'" class="text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full text-xs font-semibold">免拍申请待老师处理</span>
          <span v-else-if="scratch.waiver_status === 'approved'" class="text-green-700 bg-green-100 px-2 py-0.5 rounded-full text-xs font-semibold">老师已批准特殊处理</span>
          <span v-else-if="scratch.waiver_status === 'rejected'" class="text-red-700 bg-red-100 px-2 py-0.5 rounded-full text-xs font-semibold">申请被驳回，请补拍</span>
        </div>
        <button v-if="!scratch.final_photo_taken && scratch.waiver_status !== 'approved'" type="button" @click="showFinalCapture = true" class="text-sm text-indigo-600 font-semibold hover:underline">
          立即拍摄最终页面
        </button>
      </div>

      <div v-for="(question, index) in questions" :key="question.id" class="bg-white rounded-lg shadow p-6">
        <div class="flex items-start mb-4">
          <span class="bg-indigo-100 text-indigo-800 text-sm font-medium px-2.5 py-0.5 rounded mr-3">{{ index + 1 }}</span>
          <div class="flex-1">
            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ question.title }}</h3>
            <p class="text-sm text-gray-500 mb-3">分值: {{ question.score }}分 | 题型: {{ questionTypeLabel(question.type) }}</p>
            <div class="space-y-2">
              <template v-if="question.type === 'single_choice'">
                <label v-for="(label, key) in question.options" :key="key" class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === key}">
                  <input type="radio" :name="'question_' + question.id" :value="key" v-model="answers[question.id]" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">{{ key }}. {{ label }}</span>
                </label>
              </template>
              <template v-else-if="question.type === 'true_false'">
                <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === 'true'}">
                  <input type="radio" :name="'question_' + question.id" value="true" v-model="answers[question.id]" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">正确</span>
                </label>
                <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === 'false'}">
                  <input type="radio" :name="'question_' + question.id" value="false" v-model="answers[question.id]" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">错误</span>
                </label>
              </template>
              <template v-else-if="question.type === 'multiple_choice'">
                <label v-for="(label, key) in question.options" :key="key" class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': (answers[question.id] || []).includes(key)}">
                  <input type="checkbox" :value="key" @change="toggleMultipleChoice(question.id, key)" :checked="(answers[question.id] || []).includes(key)" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">{{ key }}. {{ label }}</span>
                </label>
              </template>
              <template v-else>
                <textarea v-model="answers[question.id]" rows="3" class="w-full border border-gray-300 rounded-md p-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="请输入答案"></textarea>
              </template>
            </div>
          </div>
        </div>
      </div>
      <div class="flex justify-between">
        <router-link to="/exams" class="bg-gray-300 text-gray-700 py-2 px-4 rounded hover:bg-gray-400">返回</router-link>
        <button @click="attemptSubmit" :disabled="submitting" class="bg-indigo-600 text-white py-2 px-6 rounded hover:bg-indigo-700 disabled:opacity-50">
          {{ submitting ? '提交中...' : '提交答卷' }}
        </button>
      </div>
    </template>
  </div>

  <!-- ============ 交卷前最终页拍照弹窗 ============ -->
  <Teleport to="body">
    <div v-if="showFinalCapture" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-gray-600/75 backdrop-blur-sm" @click="cancelFinalCapture"></div>
      <div class="relative z-10 w-full flex justify-center max-h-[92vh] overflow-y-auto">
        <ScratchPaperCapture
          :exam-paper-id="route.params.id"
          phase="final"
          title="拍摄草稿纸最终页面"
          description="交卷前留存，与答题时间共同构成监考回放时间轴"
          :history="phasePhotos('final')"
          @uploaded="onFinalUploaded"
        >
          <template #extra>
            <div class="border-t pt-4 flex items-center justify-between flex-wrap gap-3">
              <div class="flex items-center gap-3">
                <button type="button" @click="cancelFinalCapture" class="text-sm text-gray-500 hover:text-gray-700">稍后再拍</button>
                <button
                  v-if="!scratch?.can_submit && scratch?.waiver_status !== 'pending'"
                  type="button"
                  @click="showFinalCapture = false; openWaiver()"
                  class="text-sm text-amber-700 font-semibold hover:underline"
                >无法拍照，交给老师处理</button>
                <span v-else-if="scratch?.waiver_status === 'pending'" class="text-xs text-amber-700 font-semibold">免拍申请待老师处理…</span>
              </div>
              <button v-if="scratch?.can_submit" type="button" @click="doSubmit" :disabled="submitting" class="px-5 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700 disabled:opacity-50">
                {{ submitting ? '提交中...' : '照片齐全，立即交卷' }}
              </button>
            </div>
          </template>
        </ScratchPaperCapture>
      </div>
    </div>
  </Teleport>

  <!-- ============ 交给老师处理（免拍申请）弹窗 ============ -->
  <Teleport to="body">
    <div v-if="showWaiver" class="fixed inset-0 z-[85] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-gray-600/75" @click="showWaiver = false"></div>
      <div class="relative z-10 bg-white rounded-xl shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b">
          <h3 class="text-lg font-semibold text-gray-900">申请交给监考老师处理</h3>
        </div>
        <div class="px-6 py-4 space-y-4">
          <p class="text-sm text-gray-600">如设备无法拍照、漏拍或无草稿纸，请说明情况。提交后本场考试将暂时无法交卷，需监考老师批准；老师可能要求现场补交纸质草稿。</p>
          <textarea v-model="waiverReason" rows="4" maxlength="500" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="例如：电脑没有摄像头 / 草稿纸已在老师处 / 开考前忘记拍摄……"></textarea>
          <p v-if="waiverError" class="text-sm text-red-600">{{ waiverError }}</p>
        </div>
        <div class="px-6 py-4 border-t flex justify-end gap-3">
          <button @click="showWaiver = false" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">取消</button>
          <button @click="submitWaiver" :disabled="waiverSubmitting" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
            {{ waiverSubmitting ? '提交中...' : '提交申请' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../api'
import { useModal } from '../../composables/useModal'
import ScratchPaperCapture from '../../components/ScratchPaperCapture.vue'

const route = useRoute()
const router = useRouter()
const { alert, confirm } = useModal()
const examPaper = ref(null)
const examRecord = ref(null)
const questions = ref([])
const answers = ref({})
const loading = ref(true)
const submitting = ref(false)
const timeRemaining = ref(0)
const scratch = ref(null)
const photoHistory = ref([])
const showFinalCapture = ref(false)
const showWaiver = ref(false)
const waiverReason = ref('')
const waiverError = ref('')
const waiverSubmitting = ref(false)
const showRetakeWhilePending = ref(false)
let timer = null
let waiverPollTimer = null

const canSeeQuestions = computed(() => !scratch.value?.required || scratch.value?.can_answer)

onMounted(async () => {
  await loadExam()
  if (canSeeQuestions.value) {
    startTimer()
  }
  // 免拍申请提交后轮询老师处理结果
  waiverPollTimer = setInterval(async () => {
    if (scratch.value?.required && scratch.value.waiver_status === 'pending') {
      await refreshScratch()
    }
  }, 10000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
  if (waiverPollTimer) clearInterval(waiverPollTimer)
})

const loadExam = async () => {
  loading.value = true
  try {
    const response = await api.get(`/exams/${route.params.id}/questions`)
    examPaper.value = response.data.exam_paper
    examRecord.value = response.data.exam_record
    questions.value = response.data.questions || []
    scratch.value = response.data.scratch || null
    photoHistory.value = scratch.value?.photos || []
    // 以服务器开考时间计算剩余时长，拍照等待时间不额外延长考试
    timeRemaining.value = computeRemaining(examRecord.value.start_time, examPaper.value.total_time)
  } catch (e) {
    alert('获取考试信息失败', '考试加载失败', 'error')
    router.push('/exams')
  } finally {
    loading.value = false
  }
}

const computeRemaining = (startTimeIso, totalMinutes) => {
  const startMs = startTimeIso ? new Date(startTimeIso).getTime() : Date.now()
  const elapsedSec = Math.max(0, Math.floor((Date.now() - startMs) / 1000))
  return Math.max(0, totalMinutes * 60 - elapsedSec)
}

const refreshScratch = async () => {
  try {
    const { data } = await api.get(`/exams/${route.params.id}/scratch/status`)
    const prevStatus = scratch.value?.waiver_status
    scratch.value = data.scratch
    photoHistory.value = data.scratch.photos || []
    // 老师刚刚批准：放行并自动开始计时/补拉题目
    if (prevStatus === 'pending' && data.scratch.waiver_status === 'approved') {
      showRetakeWhilePending.value = false
      alert('监考老师已批准特殊处理，您现在可以继续考试/交卷', '处理结果', 'success')
      if (!questions.value.length) {
        await loadExam()
        if (!timer) startTimer()
      }
      // 时间已到且最终拍照弹窗处于打开状态时，批准后自动交卷
      if (timeRemaining.value <= 0 && data.scratch.can_submit) {
        showFinalCapture.value = false
        await doSubmit(true)
      }
    } else if (prevStatus === 'pending' && data.scratch.waiver_status === 'rejected') {
      alert(data.scratch.waiver_note ? `申请未获批准：${data.scratch.waiver_note}` : '申请未获批准，请补拍草稿纸照片后再交卷', '处理结果', 'warning')
    }
  } catch (e) {
    // 静默处理轮询错误
  }
}

const phasePhotos = (phase) => photoHistory.value.filter((p) => p.phase === phase)

const onPhotoUploaded = async (payload) => {
  scratch.value = payload.scratch
  photoHistory.value = payload.scratch.photos || []
  if (payload.photo.phase === 'pre' && scratch.value.can_answer) {
    showRetakeWhilePending.value = false
    alert('空白草稿纸照片已留存，现在开始答题', '拍照成功', 'success')
    const response = await api.get(`/exams/${route.params.id}/questions`)
    questions.value = response.data.questions || []
    if (!timer) startTimer()
  }
}

const onFinalUploaded = async (payload) => {
  scratch.value = payload.scratch
  photoHistory.value = payload.scratch.photos || []
  // 时间已到时拍完最终页自动交卷，避免学生超时滞留
  if (payload.scratch.can_submit && timeRemaining.value <= 0) {
    await doSubmit(true)
  }
}

const startTimer = () => {
  if (timer) return
  timer = setInterval(() => {
    if (timeRemaining.value > 0) {
      timeRemaining.value--
    } else {
      clearInterval(timer)
      timer = null
      handleTimeUp()
    }
  }, 1000)
}

const formatTime = (seconds) => {
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

const questionTypeLabel = (type) => ({
  single_choice: '单选题',
  multiple_choice: '多选题',
  true_false: '判断题',
  fill_blank: '填空题',
  essay: '问答题'
}[type] || type)

const toggleMultipleChoice = (questionId, key) => {
  if (!answers.value[questionId]) answers.value[questionId] = []
  const index = answers.value[questionId].indexOf(key)
  if (index === -1) answers.value[questionId].push(key)
  else answers.value[questionId].splice(index, 1)
}

const attemptSubmit = async () => {
  // 交卷闸门：最终页未拍时不能直接交卷
  if (scratch.value?.required && !scratch.value.can_submit) {
    const waiver = scratch.value.waiver_status
    if (waiver === 'pending') {
      alert('草稿纸最终页面尚未拍摄，您的免拍申请正在等待监考老师处理，批准后即可交卷。', '暂不能交卷', 'warning')
      return
    }
    if (waiver === 'rejected') {
      alert('免拍申请未获批准，请先拍摄草稿纸最终页面后再交卷。', '暂不能交卷', 'warning')
      showFinalCapture.value = true
      return
    }
    const ok = await confirm(
      '交卷前必须拍摄草稿纸最终页面并留存。现在去拍照吗？\n\n若无法拍照，可在拍照窗口中申请交给监考老师处理。',
      '请补拍最终页面',
      'warning'
    )
    if (ok) showFinalCapture.value = true
    return
  }
  await doSubmit()
}

const handleTimeUp = async () => {
  if (scratch.value?.required && !scratch.value.can_submit) {
    // 时间到但最终页未拍：弹出补拍，学生补拍或申请老师处理后才能交卷
    showFinalCapture.value = true
    alert('考试时间已到，请立即拍摄草稿纸最终页面；无法拍照请申请交给监考老师处理，完成后系统将自动交卷。', '考试时间到', 'warning')
    return
  }
  await doSubmit(true)
}

const cancelFinalCapture = () => {
  showFinalCapture.value = false
}

const openWaiver = () => {
  waiverError.value = ''
  showWaiver.value = true
}

const submitWaiver = async () => {
  waiverError.value = ''
  if (!waiverReason.value.trim() || waiverReason.value.trim().length < 2) {
    waiverError.value = '请填写至少 2 个字的原因说明'
    return
  }
  waiverSubmitting.value = true
  try {
    const { data } = await api.post(`/exams/${route.params.id}/scratch/waiver`, {
      reason: waiverReason.value.trim()
    })
    showWaiver.value = false
    waiverReason.value = ''
    scratch.value = data.scratch
    alert('已提交给监考老师处理，请耐心等待；批准后此页面会自动放行。', '申请已提交', 'success')
  } catch (e) {
    waiverError.value = e.response?.data?.errors?.reason?.[0] || e.response?.data?.message || '提交失败，请重试'
  } finally {
    waiverSubmitting.value = false
  }
}

const doSubmit = async (isAuto = false) => {
  if (submitting.value) return
  if (showFinalCapture.value && !isAuto && !scratch.value?.can_submit) {
    showFinalCapture.value = false
  }
  submitting.value = true
  try {
    const answerData = Object.entries(answers.value).map(([questionId, answer]) => ({
      question_id: parseInt(questionId),
      answer: Array.isArray(answer) ? answer.join(',') : (answer ?? '')
    }))
    const response = await api.post(`/exams/${route.params.id}/submit`, {
      exam_record_id: examRecord.value.id,
      answers: answerData
    })
    showFinalCapture.value = false
    alert(`考试完成！得分: ${response.data.score}`, '考试完成', 'success')
    router.push('/records')
  } catch (e) {
    const data = e.response?.data
    if (data?.error_code === 'SCRATCH_PAPER_INCOMPLETE' && data.scratch) {
      scratch.value = data.scratch
      showFinalCapture.value = true
      alert(data.message || '请先完成草稿纸拍照后再交卷', '暂不能交卷', 'warning')
    } else {
      alert(data?.message || '提交失败', '提交失败', 'error')
    }
  } finally {
    submitting.value = false
  }
}
</script>
