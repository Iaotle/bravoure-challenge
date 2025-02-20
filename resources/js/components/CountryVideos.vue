<template>
	<div class="container mx-auto px-4 py-6">
		<!-- Top Controls: Country selector, maxResults dropdown, and Seeder Buttons -->
		<section class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0 md:space-x-4">
			<div class="flex flex-col space-y-2">
				<label for="country-select" class="block text-gray-900 dark:text-white font-medium"> Select a country: </label>
				<select
					id="country-select"
					v-model="selectedCountry"
					@change="onCountryChange"
					class="mt-1 border rounded-md p-2 bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-blue-400"
				>
					<option v-for="(name, code) in countries" :key="code" :value="code">
						{{ name }}
					</option>
				</select>
			</div>

			<div class="flex flex-col space-y-2">
				<label for="max-results" class="block text-gray-900 dark:text-white font-medium"> Results per page: </label>
				<select
					id="max-results"
					v-model.number="maxResults"
					@change="onMaxResultsChange"
					class="mt-1 border rounded-md p-2 bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-blue-400"
				>
					<option v-for="option in maxResultsOptions" :key="option" :value="option">
						{{ option }}
					</option>
				</select>
			</div>

			<!-- Seeder Buttons -->
			<div class="flex space-x-2">
				<button @click="seedCountries" class="btn btn-success">
					<span>Seed Countries</span>
					<span v-if="seedStatuses.seedCountries === 'loading'" class="ml-2">
						<svg class="animate-spin w-4 h-4" viewBox="0 0 24 24">
							<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
							<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
						</svg>
					</span>
					<span v-else-if="seedStatuses.seedCountries === 'success'" class="ml-2">
						<svg
							class="w-4 h-4 text-green-500"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							viewBox="0 0 24 24"
						>
							<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
						</svg>
					</span>
					<span v-else-if="seedStatuses.seedCountries === 'error'" class="ml-2">
						<svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
						</svg>
					</span>
				</button>

				<button @click="seedFullCountries" class="btn btn-success">
					<span>Seed Full Countries</span>
					<span v-if="seedStatuses.seedFullCountries === 'loading'" class="ml-2">
						<svg class="animate-spin w-4 h-4" viewBox="0 0 24 24">
							<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
							<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
						</svg>
					</span>
					<span v-else-if="seedStatuses.seedFullCountries === 'success'" class="ml-2">
						<svg
							class="w-4 h-4 text-green-500"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							viewBox="0 0 24 24"
						>
							<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
						</svg>
					</span>
					<span v-else-if="seedStatuses.seedFullCountries === 'error'" class="ml-2">
						<svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
						</svg>
					</span>
				</button>
			</div>
		</section>

		<!-- Cache Action Buttons -->
		<section class="mt-6 flex flex-wrap gap-2">
			<button @click="clearCache" class="btn btn-danger">
				<span>Clear Cache</span>
				<StatusIcon :status="cacheStatuses.clearCache" />
			</button>
			<button @click="clearYouTubeCache" class="btn btn-danger">
				<span>Clear YouTube Cache</span>
				<StatusIcon :status="cacheStatuses.clearYouTubeCache" />
			</button>
			<button @click="clearCountryCache(selectedCountry)" class="btn btn-danger">
				<span>Clear Country Cache</span>
				<StatusIcon :status="cacheStatuses.clearCountryCache" />
			</button>
			<button @click="clearWikipediaCache" class="btn btn-danger">
				<span>Clear Wikipedia Cache</span>
				<StatusIcon :status="cacheStatuses.clearWikipediaCache" />
			</button>
			<button @click="clearCountryDescription" class="btn btn-danger">
				<span>Clear Country Descriptions</span>
				<StatusIcon :status="cacheStatuses.clearCountryDescription" />
			</button>
		</section>

		<!-- Navigation Hint for Keyboard Shortcuts -->
		<section class="mt-4 p-2 bg-gray-200 dark:bg-gray-700 rounded-md text-sm text-gray-800 dark:text-gray-200">
			Keyboard Shortcuts: ← Previous Page | → Next Page | ↑ Previous Country | ↓ Next Country
		</section>

		<!-- Main Content Area -->
		<main class="mt-8">
			<!-- Display time taken value here -->
			<section
				class="mt-4 p-2 rounded-md text-sm text-gray-800 dark:text-gray-200"
				:class="{
					'bg-green-800': timeTaken !== null && timeTaken < 100,
					'bg-orange-800': timeTaken >= 100 && timeTaken < 500,
					'bg-red-800': timeTaken >= 1000,
					'bg-black': timeTaken === null,
				}"
			>
				<span>Fetch time: {{ timeTaken ? timeTaken.toFixed(2) + ' ms' : 'Loading...' }}</span>
			</section>
			<!-- TODO: fix arrow keys scrolling the page -->

			<div v-if="errors != null" class="p-6 bg-red-500 text-white rounded-lg">
				<p class="text-xl font-semibold">Error fetching data</p>
				<!-- TODO: dompurify -->
				<p v-html="errors"></p>
			</div>
			<div v-if="videosData" class="p-6 bg-black shadow-lg rounded-lg">
				<h3 class="mt-6 text-xl font-semibold text-white">
					Popular Videos from {{ videosData.country }}
					<span class="block text-sm text-gray-500 mt-1">
						(showing {{ videosData.offset }} - {{ videosData.offset + videosData.numResults }} of
						{{ videosData.totalResults }}) • Last updated: {{ timeAgoOverall }}
					</span>
				</h3>
				<div class="mt-4 flex space-x-2">
					<button
						@click="fetchData(currentCountry, prevToken)"
						:disabled="!isNumeric(prevToken)"
						:class="isNumeric(prevToken) ? 'btn btn-primary' : 'btn btn-disabled'"
					>
						Previous Page
					</button>
					<button
						@click="fetchData(currentCountry, nextToken)"
						:disabled="!isNumeric(nextToken)"
						:class="isNumeric(nextToken) ? 'btn btn-primary' : 'btn btn-disabled'"
					>
						Next Page
					</button>
				</div>

				<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-6 mt-6">
					<VideoCard v-for="(video, index) in videosData.videos" :key="`${video.id}-${index}`" :video="video" />
				</div>
				<h2 class="text-3xl font-bold text-white uppercase border-b pb-3 mt-6">
					{{ videosData.country }}
				</h2>
				<p class="mt-3 text-gray-300 text-xs">
					{{ videosData.wikipedia_extract || 'No Wikipedia info available.' }}
				</p>
			</div>
			<div v-else v-if="!errors" class="flex justify-center items-center min-h-[200px]">
				<Loader />
			</div>
		</main>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, reactive, onBeforeUnmount, watch } from 'vue';
import axios from 'axios';
import { useTimeAgo } from '@vueuse/core';
import VideoCard from './VideoCard.vue';
import Loader from './Loader.vue';

// Props
const props = defineProps({
	countries: {
		type: Object,
		required: true,
	},
});

// State
const selectedCountry = ref(Object.keys(props.countries)[0] || null);
const currentCountry = ref(null);
const nextToken = ref(null);
const prevToken = ref(null);
const videosData = ref(null);
const oldestCachedPage = ref(null);
const maxResults = ref(6);
const maxResultsOptions = [6, 12, 18, 24, 30, 60];
const errors = ref(null);
const timeTaken = ref(null);

// Helper
function isNumeric(n) {
	return !isNaN(parseFloat(n)) && isFinite(n);
}
// TODO: debounce and cancel previous requests

// Fetch data function (now includes maxResults)
async function fetchData(country = selectedCountry.value, pageToken = '') {
	// Record the start time
	const startTime = performance.now();
	try {
		const response = await axios.get(`/countries?country=${country}&pageToken=${pageToken}&maxResults=${maxResults.value}`);

		// Record the end time after the response is received
		const endTime = performance.now();
		const data = response.data.data[0];
		videosData.value = data;
		currentCountry.value = country;
		nextToken.value = data.nextToken;
		prevToken.value = data.prevToken;
		oldestCachedPage.value = data.oldest_cached_page;
		errors.value = data.errors?.message;
		timeTaken.value = endTime - startTime;
	} catch (error) {
		const errorDetails = error?.response?.data ?? {};

		// Ensure trace exists and format it properly
		let formattedTrace = 'No trace available';
		if (Array.isArray(errorDetails.trace)) {
			formattedTrace = errorDetails.trace
				.map(
					(t, i) =>
						`#${i} ${t.file || 'Unknown file'}:${t.line || 'Unknown line'} - ${t.function || 'Unknown function'}`,
				)
				.join('\n');
		}

		const exceptionInfo = `
        <strong>Exception:</strong> ${errorDetails.exception || 'Unknown'} <br>
        <strong>Message:</strong> ${errorDetails.message || 'No message'} <br>
        <strong>File:</strong> ${errorDetails.file || 'Unknown'} <br>
        <strong>Line:</strong> ${errorDetails.line || 'Unknown'} <br>
        <strong>Trace:</strong> <pre class="overflow-scroll">${formattedTrace}</pre>
    `;

		errors.value = errorDetails.exception ? exceptionInfo : (error?.response?.data ?? error?.message);
	}
}

// Handle country change
function onCountryChange() {
	fetchData(selectedCountry.value);
}

// Handle maxResults change
function onMaxResultsChange() {
	fetchData(selectedCountry.value);
}

// Cache Clear Functions with content reload
const cacheStatuses = reactive({
	clearCache: 'idle',
	clearYouTubeCache: 'idle',
	clearCountryCache: 'idle',
	clearWikipediaCache: 'idle',
	clearCountryDescription: 'idle',
});

async function clearCacheAction(endpoint, statusKey) {
	cacheStatuses[statusKey] = 'loading';
	try {
		await axios.get(endpoint);
		cacheStatuses[statusKey] = 'success';
		// Reload content after clearing cache
		fetchData(selectedCountry.value);
	} catch (error) {
		cacheStatuses[statusKey] = 'error';
	}
	setTimeout(() => (cacheStatuses[statusKey] = 'idle'), 2500);
}

const clearCache = () => clearCacheAction('/clear-cache', 'clearCache');
const clearYouTubeCache = () => clearCacheAction('/clear-youtube-cache', 'clearYouTubeCache');
const clearCountryCache = (country) => clearCacheAction(`/clear-country-cache/${country}`, 'clearCountryCache');
const clearWikipediaCache = () => clearCacheAction('/clear-wikipedia-cache', 'clearWikipediaCache');
const clearCountryDescription = () => clearCacheAction('/clear-country-description', 'clearCountryDescription');

// Seeder Functions
const seedStatuses = reactive({
	seedCountries: 'idle',
	seedFullCountries: 'idle',
});

async function seedAction(endpoint, statusKey) {
	seedStatuses[statusKey] = 'loading';
	try {
		await axios.get(endpoint);
		seedStatuses[statusKey] = 'success';
		// For seeding, we reload the whole page
		window.location.reload();
	} catch (error) {
		seedStatuses[statusKey] = 'error';
		setTimeout(() => (seedStatuses[statusKey] = 'idle'), 2500);
	}
}

const seedCountries = () => seedAction('/seed-countries', 'seedCountries');
const seedFullCountries = () => seedAction('/seed-full-countries', 'seedFullCountries');

// Overall formatted date helper
const timeAgoOverall = computed(() => (oldestCachedPage.value ? useTimeAgo(new Date(oldestCachedPage.value)).value : ''));

// Keyboard navigation helpers
function changeCountry(direction) {
	const countryCodes = Object.keys(props.countries);
	const currentIndex = countryCodes.indexOf(selectedCountry.value);
	if (direction === 'previous' && currentIndex > 0) {
		selectedCountry.value = countryCodes[currentIndex - 1];
	} else if (direction === 'next' && currentIndex < countryCodes.length - 1) {
		selectedCountry.value = countryCodes[currentIndex + 1];
	}
	fetchData(selectedCountry.value);
}

function handleKeydown(e) {
	// Avoid triggering shortcuts when focused in inputs or editable elements
	const tag = e.target.tagName.toLowerCase();
	if (['select', 'input', 'textarea'].includes(tag) || e.target.isContentEditable) return;

	switch (e.key) {
		case 'ArrowLeft':
			if (isNumeric(prevToken.value)) fetchData(currentCountry.value, prevToken.value);
			break;
		case 'ArrowRight':
			if (isNumeric(nextToken.value)) fetchData(currentCountry.value, nextToken.value);
			break;
		case 'ArrowUp':
			changeCountry('previous');
			break;
		case 'ArrowDown':
			changeCountry('next');
			break;
		default:
			break;
	}
}

onMounted(() => {
	fetchData(selectedCountry.value);
	window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
	window.removeEventListener('keydown', handleKeydown);
});
</script>

<!-- StatusIcon is a helper component that renders the correct SVG based on status -->
<script>
export default {
	components: {
		StatusIcon: {
			props: ['status'],
			template: `
				<span v-if="status==='loading'" class="ml-2">
					<svg class="animate-spin w-4 h-4" viewBox="0 0 24 24">
						<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
						<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
					</svg>
				</span>
				<span v-else-if="status==='success'" class="ml-2">
					<svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
					</svg>
				</span>
				<span v-else-if="status==='error'" class="ml-2">
					<svg class="w-4 h-4 text-white-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
					</svg>
				</span>
			`,
		},
	},
};
</script>

<style scoped>
.btn {
	@apply inline-flex items-center px-4 py-2 rounded-md font-semibold transition-colors duration-150 focus:outline-none focus:ring-2;
}

.btn-success {
	@apply bg-green-500 text-white hover:bg-green-600 focus:ring-green-400;
}

.btn-danger {
	@apply bg-red-500 text-white hover:bg-red-600 focus:ring-red-400;
}

.btn-primary {
	@apply bg-blue-500 text-white hover:bg-blue-600 focus:ring-blue-400;
}

.btn-disabled {
	@apply bg-gray-500 text-white cursor-not-allowed opacity-50;
}
</style>
