<template>
  <svg class="app-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path v-for="path in iconPaths" :key="path" :d="path" />
    <circle v-for="circle in iconCircles" :key="`${circle.cx}-${circle.cy}-${circle.r}`" :cx="circle.cx" :cy="circle.cy" :r="circle.r" />
    <polyline v-for="polyline in iconPolylines" :key="polyline" :points="polyline" />
    <line v-for="line in iconLines" :key="`${line.x1}-${line.y1}-${line.x2}-${line.y2}`" :x1="line.x1" :y1="line.y1" :x2="line.x2" :y2="line.y2" />
  </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue';

type IconLine = {
  x1: number;
  y1: number;
  x2: number;
  y2: number;
};

type IconCircle = {
  cx: number;
  cy: number;
  r: number;
};

type IconShape = {
  paths?: string[];
  circles?: IconCircle[];
  polylines?: string[];
  lines?: IconLine[];
};

const props = defineProps<{
  name: string;
}>();

const icons: Record<string, IconShape> = {
  menu: {
    paths: ['M4 6h16', 'M4 12h16', 'M4 18h16'],
  },
  close: {
    paths: ['M6 6l12 12', 'M18 6 6 18'],
  },
  leaf: {
    paths: ['M11 20A7 7 0 0 1 4 13C4 5 12 3 20 3c0 8-2 16-9 16Z', 'M4 13c4 0 8-2 12-6'],
  },
  dashboard: {
    paths: ['M4 13h6V4H4v9Z', 'M14 20h6V4h-6v16Z', 'M4 20h6v-3H4v3Z'],
  },
  forms: {
    paths: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z', 'M14 2v6h6', 'M8 13h8', 'M8 17h5'],
  },
  submissions: {
    paths: ['M9 11 12 14 22 4', 'M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
  },
  facilities: {
    paths: ['M3 21h18', 'M5 21V7l7-4 7 4v14', 'M9 21v-6h6v6', 'M9 10h.01', 'M15 10h.01'],
  },
  reports: {
    paths: ['M4 19V5', 'M4 19h16', 'M8 15v-4', 'M12 15V8', 'M16 15v-6'],
  },
  integrations: {
    paths: ['M17 7h1a3 3 0 0 1 0 6h-1', 'M7 17H6a3 3 0 0 1 0-6h1', 'M8 12h8'],
  },
  team: {
    paths: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
  },
  settings: {
    paths: ['M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z', 'M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1.82 2 2 0 1 1-3.34 0A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1.82-.33 2 2 0 1 1 0-3.34A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .33-1.82 2 2 0 1 1 3.34 0A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.2.36.2.78 0 1.14.25.18.53.3.84.36a2 2 0 1 1 0 3.34 1.65 1.65 0 0 0-.84.36Z'],
  },
  search: {
    paths: ['m21 21-4.35-4.35'],
    circles: [{ cx: 11, cy: 11, r: 7 }],
  },
  bell: {
    paths: ['M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9', 'M13.73 21a2 2 0 0 1-3.46 0'],
  },
  plus: {
    lines: [{ x1: 12, y1: 5, x2: 12, y2: 19 }, { x1: 5, y1: 12, x2: 19, y2: 12 }],
  },
  calendar: {
    paths: ['M8 2v4', 'M16 2v4', 'M3 10h18', 'M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z'],
  },
  clipboard: {
    paths: ['M9 5h6', 'M9 12h6', 'M9 16h4', 'M8 3h8l1 3H7l1-3Z', 'M6 5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1'],
  },
  clock: {
    paths: ['M12 7v5l3 2'],
    circles: [{ cx: 12, cy: 12, r: 9 }],
  },
  upload: {
    paths: ['M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4', 'M17 8l-5-5-5 5', 'M12 3v12'],
  },
  refresh: {
    paths: ['M20 11a8 8 0 0 0-14.9-3', 'M5 4v4h4', 'M4 13a8 8 0 0 0 14.9 3', 'M19 20v-4h-4'],
  },
  check: {
    paths: ['m5 12 4 4L19 6'],
  },
  location: {
    paths: ['M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z'],
    circles: [{ cx: 12, cy: 10, r: 2.5 }],
  },
  building: {
    paths: ['M3 21h18', 'M4 21V5a2 2 0 0 1 2-2h8v18', 'M14 9h4a2 2 0 0 1 2 2v10', 'M8 7h2', 'M8 11h2', 'M8 15h2'],
  },
  more: {
    circles: [{ cx: 12, cy: 12, r: 1 }, { cx: 19, cy: 12, r: 1 }, { cx: 5, cy: 12, r: 1 }],
  },
  arrowLeft: {
    paths: ['M19 12H5'],
    polylines: ['12 19 5 12 12 5'],
  },
  arrowUp: {
    paths: ['M12 19V5'],
    polylines: ['5 12 12 5 19 12'],
  },
  arrowDown: {
    paths: ['M12 5v14'],
    polylines: ['5 12 12 19 19 12'],
  },
  drag: {
    circles: [
      { cx: 8, cy: 6, r: 1 }, { cx: 16, cy: 6, r: 1 },
      { cx: 8, cy: 12, r: 1 }, { cx: 16, cy: 12, r: 1 },
      { cx: 8, cy: 18, r: 1 }, { cx: 16, cy: 18, r: 1 },
    ],
  },
  save: {
    paths: ['M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z', 'M17 21v-8H7v8', 'M7 3v5h8'],
  },
  pencil: {
    paths: ['M4 20h4L19 9l-4-4L4 16v4Z', 'm13.5 6.5 4 4'],
  },
  lock: {
    paths: ['M6 10h12v10H6z', 'M8 10V7a4 4 0 0 1 8 0v3'],
  },
  switch: {
    paths: ['M7 7h11', 'm14 4 4 3-4 3', 'M17 17H6', 'm10 14-4 3 4 3'],
  },
  wifi: {
    paths: ['M5 13a10 10 0 0 1 14 0', 'M8.5 16.5a5 5 0 0 1 7 0', 'M12 20h.01'],
  },
  signature: {
    paths: ['M3 17c3-5 5-7 6-6 1 1-2 6 0 7 2 1 5-4 8-4 2 0 3 1 4 2'],
  },
  clone: {
    paths: ['M8 8h11a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2Z', 'M4 16H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v1'],
  },
};

const fallback: IconShape = {
  paths: ['M12 2v20', 'M2 12h20'],
};

const iconShape = computed(() => icons[props.name] ?? fallback);
const iconPaths = computed(() => iconShape.value.paths ?? []);
const iconCircles = computed(() => iconShape.value.circles ?? []);
const iconPolylines = computed(() => iconShape.value.polylines ?? []);
const iconLines = computed(() => iconShape.value.lines ?? []);
</script>
