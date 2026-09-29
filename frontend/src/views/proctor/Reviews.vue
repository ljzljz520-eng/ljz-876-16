<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">草稿纸监考复核</h1>
        <p class="text-sm text-gray-500 mt-1">处理学生漏拍/无法拍照申请，查看草稿照片与答题时间轴回放</p>
      </div>
      <span v-if="pendingCount > 0" class="bg-red-100 text-red-700 text-sm font-semibold px-3 py-1.5 rounded-full">
        待处理 {{ pendingCount }} 条
      </span>
    </div>

    <div class="bg-white rounded-lg shadow p-3 flex items-center gap-2 flex-wrap">
      <button v-for="opt in filters" :key="opt.value"
        @click="filter = opt.value; page = 1; fetchList()"
        class="px-3 py-1.5 rounded-full text-sm font-medium"
        :class="filter === opt.value ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
        {{ opt.label }}
      </button>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <div v-else-if="records.length === 0" class="text-center py-8 text-gray-500 bg-white rounded-lg shadow">
      暂无需要复核的考试记录
    </div>

    <div v-else class="bg-white shadow overflow-hidden sm:rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">学生</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">试卷</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">空白纸</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">最终页</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">处理状态</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="record in records" :key="record.id" :class="record.scratch_waiver_status === 'pending' ? 'bg-amber-50/50' : ''">
            <td class="px-4 py-3 text-sm">
              <div class="font-medium text-gray-900">{{ record.user?.real_name || record.user?.username }}</div>
              <div class="text-gray-400 text-xs">{{ record.user?.username }}</div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-700">{{ record.exam_paper?.title }}</td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold" :class="record.scratch_pre_count > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'">
                {{ record.scratch_pre_count }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold" :class="record.scratch_final_count > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'">
                {{ record.scratch_final_count }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm">
              <span v-if="record.scratch_waiver_status === 'pending'" class="text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full text-xs font-semibold">待老师处理</span>
              <span v-else-if="record.scratch_waiver_status === 'approved'" class="text-green-700 bg-green-100 px-2 py-0.5 rounded-full text-xs font-semibold">已批准</span>
              <span v-else-if="record.scratch_waiver_status === 'rejected'" class="text-red-700 bg-red-100 px-2 py-0.5 rounded-full text-xs font-semibold">已驳回</span>
              <span v-else class="text-gray-500 text-xs">未申请</span>
            </td>
            <td class="px-4 py-3 text-sm space-x-2 whitespace-nowrap">
              <button @click="openTimeline(record)" class="text-indigo-600 hover:text-indigo-900 font-medium">监考回放</button>
              <button v-if="record.scratch_waiver_status === 'pending'" @click="openReview(record)" class="text-amber-700 hover:text-amber-900 font-medium">处理申请</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="px-4 py-3 flex items-center justify-between border-t">
        <span class="text-sm text-gray-500">共 {{ total }} 条</span>
        <div class="flex gap-2">
          <button @click="changePage(page - 1)" :disabled="page <= 1" class="px-3 py-1 border rounded text-sm disabled:opacity-40">上一页</button>
          <span class="px-3 py-1 text-sm">{{ page }}</span>
          <button @click="changePage(page + 1)" :disabled="page >= lastPage" class="px-3 py-1 border rounded text-sm disabled:opacity-40">下一页</button>
        </div>
      </div>
    </div>

    <!-- 处理申请弹窗 -->
    <Teleport to="body">
      <div v-if="reviewRecord" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-600/75" @click="reviewRecord = null"></div>
        <div class="relative z-10 bg-white rounded-xl shadow-xl w-full max-w-lg">
          <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold">处理免拍申请</h3>
          </div>
          <div class="px-6 py-4 space-y-4">
            <div class="bg-gray-50 rounded-lg p-3 text-sm">
              <p class="text-gray-500 mb-1">学生：{{ reviewRecord.user?.real_name || reviewRecord.user?.username }} ｜ 试卷：{{ reviewRecord.exam_paper?.title }}</p>
              <p class="text-gray-700"><span class="font-medium">申请原因：</span>{{ reviewRecord.scratch_waiver_reason }}</p>
            </div>
            <textarea v-model="reviewNote" rows="3" maxlength="500" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="处理备注（可选，学生可见）"></textarea>
            <p class="text-xs text-gray-500">提示：批准后学生可在缺少草稿照片的情况下交卷，相关情况会保留在监考回放记录中。</p>
          </div>
          <div class="px-6 py-4 border-t flex justify-end gap-3">
            <button @click="reviewRecord = null" class="px-4 py-2 border rounded-lg text-sm">取消</button>
            <button @click="decide('rejected')" :disabled="deciding" class="px-4 py-2 bg-white border border-red-300 text-red-600 rounded-lg text-sm font-semibold hover:bg-red-50 disabled:opacity-50">驳回（要求补拍）</button>
            <button @click="decide('approved')" :disabled="deciding" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 disabled:opacity-50">批准放行</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'

const router = useRouter()
const records = ref([])
const loading = ref(true)
const filter = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const pendingCount = ref(0)
const reviewRecord = ref(null)
const reviewNote = ref('')
const deciding = ref(false)

const filters = [
  { value: '', label: '全部' },
  { value: 'pending', label: '待处理' },
  { value: 'none', label: '未申请' },
  { value: 'approved', label: '已批准' },
  { value: 'rejected', label: '已驳回' }
]

onMounted(() => fetchList())

const fetchList = async () => {
  loading.value = true
  try {
    const params = { page: page.value, per_page: 15 }
    if (filter.value) params.waiver_status = filter.value
    const { data } = await api.get('/scratch-papers/reviews', { params })
    records.value = data.records.data
    lastPage.value = data.records.last_page
    total.value = data.records.total
    pendingCount.value = data.pending_count
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const changePage = (p) => {
  if (p < 1 || p > lastPage.value) return
  page.value = p
  fetchList()
}

const openTimeline = (record) => {
  router.push({ name: 'ProctorTimeline', params: { paperId: record.exam_paper_id, recordId: record.id } })
}

const openReview = (record) => {
  reviewRecord.value = record
  reviewNote.value = ''
}

const decide = async (decision) => {
  deciding.value = true
  try {
    await api.post(`/scratch-papers/records/${reviewRecord.value.id}/review`, {
      decision,
      note: reviewNote.value.trim() || null
    })
    reviewRecord.value = null
    await fetchList()
  } catch (e) {
    console.error(e)
  } finally {
    deciding.value = false
  }
}
</script>
