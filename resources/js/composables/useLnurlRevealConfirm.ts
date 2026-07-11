import { onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../services/api';

/**
 * LNURL "confirm with Lightning wallet" challenge flow for sensitive actions
 * (wallet secret reveal, Cashu settings edit).
 *
 * Owns the challenge fetch, the QR modal state and the status polling. What happens
 * after the user confirms in their wallet is the caller's business: `onConfirmed`
 * runs once per confirmed challenge; when it throws, the modal reopens with the
 * extracted error so the user can retry.
 */
export function useLnurlRevealConfirm(options: {
    onConfirmed: () => Promise<void>;
    /** Maps an onConfirmed error to the message shown in the reopened modal. */
    confirmErrorMessage: (err: unknown) => string;
}) {
    const { t } = useI18n();

    const showModal = ref(false);
    const lnurl = ref('');
    const k1 = ref('');
    const loading = ref(false);
    const error = ref('');
    const polling = ref(false);
    let pollingInterval: number | null = null;

    function stopPolling() {
        if (pollingInterval != null) {
            window.clearInterval(pollingInterval);
            pollingInterval = null;
        }
        polling.value = false;
    }

    function close() {
        stopPolling();
        showModal.value = false;
        k1.value = '';
        lnurl.value = '';
        error.value = '';
    }

    async function fetchChallengeAndOpen(): Promise<boolean> {
        try {
            const res = await api.post('/lnurl-auth/reveal-confirm-challenge');
            const raw = res.data ?? {};
            const data =
                typeof raw === 'object' && raw !== null && 'data' in raw
                    ? (raw as { data: { k1?: string; lnurl?: string } }).data
                    : raw;
            const challengeK1 = data?.k1 ?? (data as { K1?: string })?.K1;
            const challengeLnurl =
                data?.lnurl ?? (data as { lnurlAuthUrl?: string })?.lnurlAuthUrl;
            if (!challengeK1 || !challengeLnurl) {
                error.value = t('auth.error_occurred');
                return false;
            }
            k1.value = challengeK1;
            lnurl.value = challengeLnurl;
            showModal.value = true;
            polling.value = true;
            const startTime = Date.now();
            const doPoll = async () => {
                if (Date.now() - startTime > 300000) {
                    error.value = t('account.challenge_expired');
                    close();
                    return;
                }
                try {
                    const statusRes = await api.get(
                        `/lnurl-auth/challenge-status/${challengeK1}?_=${Date.now()}`,
                    );
                    const sRaw = statusRes.data ?? {};
                    const sData =
                        typeof sRaw === 'object' && sRaw !== null && 'data' in sRaw
                            ? (sRaw as { data: { status?: string } }).data
                            : sRaw;
                    const status = (sData as { status?: string })?.status;
                    if (status === 'reveal_confirmed') {
                        stopPolling();
                        showModal.value = false;
                        try {
                            await options.onConfirmed();
                        } catch (err: unknown) {
                            error.value = options.confirmErrorMessage(err);
                            showModal.value = true;
                        }
                    } else if (status === 'expired' || status === 'error') {
                        error.value =
                            status === 'expired'
                                ? t('account.challenge_expired')
                                : (sData as { message?: string })?.message ||
                                  t('auth.error_occurred');
                    }
                } catch {
                    // keep polling
                }
            };
            doPoll();
            pollingInterval = window.setInterval(doPoll, 1000);
            return true;
        } catch (err: any) {
            error.value = err.response?.data?.error || t('auth.error_occurred');
            return false;
        }
    }

    async function open() {
        loading.value = true;
        error.value = '';
        try {
            await fetchChallengeAndOpen();
        } finally {
            loading.value = false;
        }
    }

    async function requestNew() {
        stopPolling();
        error.value = '';
        await fetchChallengeAndOpen();
    }

    onUnmounted(stopPolling);

    return {
        showModal,
        lnurl,
        loading,
        error,
        polling,
        open,
        close,
        requestNew,
    };
}
