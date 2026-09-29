<template>
  <div class="space-y-4">
    <!-- 实时取景 -->
    <div v-if="!capturedUrl" class="relative">
      <video
        ref="videoEl"
        autoplay
        playsinline
        muted
        class="w-full rounded-lg bg-black aspect-[4/3] object-cover"
      ></video>
      <div v-if="cameraError" class="absolute inset-0 flex flex-col items-center justify-center text-gray-300 text-sm p-4 text-center bg-gray-900 rounded-lg">
        <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
        <p>{{ cameraError }}</p>
      </div>
    </div>

    <!-- 拍照结果预览 -->
    <div v-else class="relative">
      <img :src="capturedUrl" alt="草稿纸照片预览" class="w-full rounded-lg border border-gray-200" />
    </div>

    <!-- 操作按钮 -->
    <div class="flex flex-wrap gap-3">
      <button
        v-if="!capturedUrl && !cameraError"
        type="button"
        @click="capture"
        class="flex-1 bg-indigo-600 text-white py-2.5 px-4 rounded-lg hover:bg-indigo-700 font-medium"
      >
        📷 拍照
      </button>

      <label
        v-if="!capturedUrl"
        class="flex-1 text-center bg-white border border-gray-300 text-gray-700 py-2.5 px-4 rounded-lg hover:bg-gray-50 font-medium cursor-pointer"
      >
        从相册/文件选择
        <input type="file" accept="image/*" capture="environment" class="hidden" @change="onFileSelect" />
      </label>

      <template v-else>
        <button
          type="button"
          @click="retake"
          class="flex-1 bg-white border border-gray-300 text-gray-700 py-2.5 px-4 rounded-lg hover:bg-gray-50 font-medium"
        >
          重拍
        </button>
        <button
          type="button"
          @click="confirmPhoto"
          :disabled="uploading"
          class="flex-1 bg-green-600 text-white py-2.5 px-4 rounded-lg hover:bg-green-700 font-medium disabled:opacity-50"
        >
          {{ uploading ? '上传中...' : '确认使用这张' }}
        </button>
      </template>
    </div>

    <p class="text-xs text-gray-400">
      照片仅用于监考核对，请确保页面完整、光线清晰。拍照时间：{{ capturedAtLabel || '尚未拍摄' }}
    </p>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  uploading: { type: Boolean, default: false }
})
const emit = defineEmits(['captured'])

const videoEl = ref(null)
const capturedUrl = ref(null)
const capturedBlob = ref(null)
const capturedAtLabel = ref('')
const capturedAtISO = ref(null)
const cameraError = ref('')
let stream = null

onMounted(async () => {
  await openCamera()
})

onBeforeUnmount(() => {
  stopCamera()
  if (capturedUrl.value) URL.revokeObjectURL(capturedUrl.value)
})

const openCamera = async () => {
  try {
    if (!navigator.mediaDevices?.getUserMedia) {
      cameraError.value = '当前浏览器不支持摄像头，请使用"从相册/文件选择"上传照片。'
      return
    }
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } },
      audio: false
    })
    if (videoEl.value) {
      videoEl.value.srcObject = stream
    } else {
      // 视频元素尚未渲染时等一帧
      requestAnimationFrame(() => {
        if (videoEl.value) videoEl.value.srcObject = stream
      })
    }
  } catch (e) {
    cameraError.value = '无法打开摄像头（' + (e.name || '错误') + '）。可检查权限，或直接从相册选择照片。'
  }
}

const stopCamera = () => {
  if (stream) {
    stream.getTracks().forEach(t => t.stop())
    stream = null
  }
}

const capture = () => {
  const video = videoEl.value
  if (!video || !video.videoWidth) return

  const canvas = document.createElement('canvas')
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  const ctx = canvas.getContext('2d')
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height)

  canvas.toBlob((blob) => {
    if (!blob) return
    setCaptured(blob, 'image/jpeg')
  }, 'image/jpeg', 0.85)
}

const onFileSelect = (e) => {
  const file = e.target.files?.[0]
  if (!file) return
  setCaptured(file, file.type || 'image/jpeg')
}

const setCaptured = (blobOrFile, mime) => {
  if (capturedUrl.value) URL.revokeObjectURL(capturedUrl.value)
  capturedBlob.value = blobOrFile
  capturedUrl.value = URL.createObjectURL(blobOrFile)
  capturedAtISO.value = new Date().toISOString()
  capturedAtLabel.value = new Date().toLocaleString()
  stopCamera()
}

const retake = async () => {
  if (capturedUrl.value) URL.revokeObjectURL(capturedUrl.value)
  capturedUrl.value = null
  capturedBlob.value = null
  capturedAtISO.value = null
  capturedAtLabel.value = ''
  cameraError.value = ''
  await openCamera()
}

const confirmPhoto = () => {
  if (!capturedBlob.value) return
  emit('captured', {
    file: capturedBlob.value,
    takenAt: capturedAtISO.value
  })
}

defineExpose({
  reset: retake
})
</script>
