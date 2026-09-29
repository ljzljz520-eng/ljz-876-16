<template>
  <div class="bg-white rounded-xl shadow-lg overflow-hidden max-w-2xl w-full">
    <!-- 头部 -->
    <div class="px-6 py-4 border-b flex items-start justify-between">
      <div>
        <h3 class="text-lg font-bold text-gray-900">{{ title }}</h3>
        <p class="mt-1 text-sm text-gray-500">{{ description }}</p>
      </div>
      <span class="ml-4 flex-shrink-0 px-2.5 py-1 text-xs font-semibold rounded-full" :class="phase === 'pre' ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800'">
        {{ phase === 'pre' ? '开考前 · 空白纸' : '交卷前 · 最终页' }}
      </span>
    </div>

    <div class="p-6 space-y-4">
      <!-- 相机取景 -->
      <div v-if="mode === 'camera'" class="space-y-4">
        <div class="relative bg-gray-900 rounded-lg overflow-hidden aspect-[4/3]">
          <video ref="videoEl" autoplay playsinline class="w-full h-full object-cover"></video>
          <div v-if="!cameraReady" class="absolute inset-0 flex flex-col items-center justify-center text-gray-300">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-white mb-3"></div>
            <span class="text-sm">正在启动摄像头...</span>
          </div>
          <div class="absolute top-3 left-3 flex items-center bg-black/50 text-white text-xs px-2 py-1 rounded">
            <span class="w-2 h-2 rounded-full bg-red-500 mr-2 animate-pulse"></span>实时取景
          </div>
        </div>
        <p v-if="cameraError" class="text-sm text-red-600 bg-red-50 rounded p-3">
          {{ cameraError }}
        </p>
        <div class="flex flex-wrap gap-3 justify-center">
          <button
            type="button"
            @click="capture"
            :disabled="!cameraReady || uploading"
            class="inline-flex items-center px-5 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            拍照
          </button>
          <button
            type="button"
            @click="mode = 'upload'"
            class="inline-flex items-center px-4 py-2.5 rounded-lg bg-white border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50"
          >
            无法使用摄像头，选择图片
          </button>
        </div>
      </div>

      <!-- 文件选择（降级方案） -->
      <div v-else class="space-y-3">
        <label class="flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-lg p-8 cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/40 transition">
          <svg class="w-10 h-10 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
          <span class="text-sm text-gray-600">点击选择草稿纸照片（JPG/PNG，≤10MB）</span>
          <input ref="fileInputEl" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onFileSelected">
        </label>
        <div class="text-center">
          <button type="button" @click="startCamera" class="text-sm text-indigo-600 hover:underline">返回摄像头拍照</button>
        </div>
      </div>

      <!-- 预览确认 -->
      <div v-if="previewUrl" class="space-y-3">
        <div class="border rounded-lg overflow-hidden">
          <img :src="previewUrl" alt="草稿纸照片预览" class="w-full max-h-80 object-contain bg-gray-100">
        </div>
        <p class="text-xs text-gray-500">请确认照片中草稿纸内容完整、画面清晰。确认后将上传留存并记录拍摄时间。</p>
        <div class="flex flex-wrap gap-3 justify-end">
          <button
            type="button"
            @click="retake"
            :disabled="uploading"
            class="px-4 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50 disabled:opacity-50"
          >
            重新拍摄
          </button>
          <button
            type="button"
            @click="confirmUpload"
            :disabled="uploading"
            class="inline-flex items-center px-5 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700 disabled:opacity-50"
          >
            <span v-if="uploading" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
            {{ uploading ? '上传留存中...' : '确认上传留存' }}
          </button>
        </div>
      </div>

      <!-- 已留存照片列表 -->
      <div v-if="history.length" class="border-t pt-4">
        <p class="text-sm font-medium text-gray-700 mb-2">本场已留存（{{ history.length }}）</p>
        <div class="flex gap-3 flex-wrap">
          <div v-for="item in history" :key="item.id" class="relative w-24 h-24 rounded border overflow-hidden bg-gray-100">
            <img :src="tokenizeUrl(item.url)" class="w-full h-full object-cover" alt="">
          </div>
        </div>
      </div>

      <slot name="extra"></slot>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import api from '../api'

const props = defineProps({
  examPaperId: { type: [Number, String], required: true },
  phase: { type: String, required: true }, // pre | final
  title: { type: String, default: '草稿纸拍照留存' },
  description: { type: String, default: '' },
  history: { type: Array, default: () => [] }
})

const emit = defineEmits(['uploaded'])

const videoEl = ref(null)
const fileInputEl = ref(null)
const mode = ref('camera')
const cameraReady = ref(false)
const cameraError = ref('')
const previewUrl = ref('')
const pendingBlob = ref(null)
const uploading = ref(false)
let mediaStream = null

const tokenizeUrl = (url) => {
  const token = localStorage.getItem('token')
  if (!token) return url
  return url + (url.includes('?') ? '&' : '?') + 'token=' + encodeURIComponent(token)
}

const stopCamera = () => {
  if (mediaStream) {
    mediaStream.getTracks().forEach((t) => t.stop())
    mediaStream = null
  }
  cameraReady.value = false
}

const startCamera = async () => {
  mode.value = 'camera'
  cameraError.value = ''
  stopCamera()
  try {
    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error('当前浏览器不支持摄像头调用，请改用“选择图片”上传')
    }
    mediaStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment' },
      audio: false
    })
    await nextTick()
    if (videoEl.value) {
      videoEl.value.srcObject = mediaStream
      try {
        await videoEl.value.play()
      } catch (e) {
        // autoplay 被阻止时，video 上仍可手动点击；不阻断拍照
      }
    }
    cameraReady.value = true
  } catch (e) {
    cameraError.value = e?.name === 'NotAllowedError'
      ? '摄像头权限被拒绝，请允许浏览器使用摄像头，或选择本地图片上传'
      : (e?.message || '摄像头启动失败，可选择本地图片上传')
    cameraReady.value = false
  }
}

const capture = () => {
  if (!videoEl.value || !cameraReady.value) return
  const video = videoEl.value
  const canvas = document.createElement('canvas')
  canvas.width = video.videoWidth || 1280
  canvas.height = video.videoHeight || 960
  const ctx = canvas.getContext('2d')
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height)
  canvas.toBlob((blob) => {
    if (!blob) return
    setPreview(blob, 'captured-scratch.jpg')
  }, 'image/jpeg', 0.85)
}

const onFileSelected = (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  if (!file.type.startsWith('image/')) {
    cameraError.value = '请选择图片文件'
    return
  }
  if (file.size > 10 * 1024 * 1024) {
    cameraError.value = '照片不能超过 10MB'
    return
  }
  setPreview(file, file.name)
}

const setPreview = (blobOrFile, name) => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  pendingBlob.value = { blob: blobOrFile, name }
  previewUrl.value = URL.createObjectURL(blobOrFile)
  stopCamera()
}

const retake = () => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = ''
  pendingBlob.value = null
  if (fileInputEl.value) fileInputEl.value.value = ''
  startCamera()
}

const confirmUpload = async () => {
  if (!pendingBlob.value || uploading.value) return
  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('phase', props.phase)
    formData.append('photo', pendingBlob.value.blob, pendingBlob.value.name)
    formData.append('client_captured_at', new Date().toISOString())

    const { data } = await api.post(`/exams/${props.examPaperId}/scratch/upload`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 60000
    })
    previewUrl.value = ''
    pendingBlob.value = null
    emit('uploaded', data)
  } catch (e) {
    // 全局拦截器会弹出错误提示
    console.error('scratch upload failed', e)
  } finally {
    uploading.value = false
    startCamera()
  }
}

onMounted(() => {
  startCamera()
})

onBeforeUnmount(() => {
  stopCamera()
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
})
</script>
