import { computed, ref } from 'vue';

const activeRequestCount = ref(0);

export const isApiLoading = computed(() => activeRequestCount.value > 0);

export const trackApiRequest = async <T>(request: () => Promise<T>): Promise<T> => {
  activeRequestCount.value += 1;

  try {
    return await request();
  } finally {
    activeRequestCount.value = Math.max(0, activeRequestCount.value - 1);
  }
};
