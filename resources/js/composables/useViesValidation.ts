import { ref } from 'vue';
import api from '../services/api';

export type ViesValidationData = {
  valid: boolean;
  country_code: string;
  vat_number: string;
  name?: string | null;
  address?: string | null;
  request_date?: string | null;
  error_code?: string | null;
  error_message?: string | null;
};

export function useViesValidation() {
  const loading = ref(false);
  const message = ref('');
  const lastResult = ref<ViesValidationData | null>(null);

  async function validate(vatNumber: string, country?: string): Promise<ViesValidationData | null> {
    const trimmed = vatNumber.trim();
    if (!trimmed) {
      message.value = '';
      lastResult.value = null;
      return null;
    }

    loading.value = true;
    message.value = '';
    try {
      const res = await api.post<{ data: ViesValidationData }>('/invoicing/vies/validate', {
        vat_number: trimmed,
        ...(country ? { country } : {}),
      });
      lastResult.value = res.data.data;
      return res.data.data;
    } catch {
      message.value = 'unavailable';
      lastResult.value = null;
      return null;
    } finally {
      loading.value = false;
    }
  }

  return {
    loading,
    message,
    lastResult,
    validate,
  };
}
