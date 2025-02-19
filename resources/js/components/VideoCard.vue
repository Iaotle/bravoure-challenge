<template>
	<div class="bg-black border shadow-md rounded-lg overflow-hidden hover:shadow-xl transition">
	  <a :href="`https://www.youtube.com/watch?v=${video.id}`" target="_blank">
		<img
		  :src="video.thumbnails.high"
		  :alt="video.title"
		  class="w-full h-56 object-cover"
		/>
	  </a>
	  <div class="p-4">
		<a
		  :href="`https://www.youtube.com/watch?v=${video.id}`"
		  target="_blank"
		  class="text-lg font-bold text-blue-600 hover:underline block"
		>
		  {{ video.title }}
		</a>
		<p class="text-sm text-gray-500 mt-1">
		  {{ formatDate(video.publishedAt) }} ({{ timeAgo }})
		</p>
		<p class="mt-2 text-gray-700 line-clamp-3">
		  {{ video.description }}
		</p>
	  </div>
	</div>
  </template>
  
  <script setup>
  import { computed } from 'vue'
  import { useTimeAgo } from '@vueuse/core'
  
  const props = defineProps({
	video: {
	  type: Object,
	  required: true,
	},
  })
  
  // Helper: Format the date to locale string
  function formatDate(dateStr) {
	return new Date(dateStr).toLocaleDateString()
  }
  
  // Compute the relative time (e.g. "2 minutes ago") for the video's published date
  const timeAgo = computed(() => {
	return useTimeAgo(new Date(props.video.publishedAt)).value
  })
  </script>
  