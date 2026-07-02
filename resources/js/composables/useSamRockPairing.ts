import { ref, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../services/api';
import { getApiErrorMessage } from './useApiError';

export function useSamRockPairing(storeId: () => string) {
    const { t } = useI18n();

    const samrockOtp = ref('');
    const samrockExpiresAt = ref<string | null>(null);
    const samrockQrObjectUrl = ref<string | null>(null);
    const samrockBusy = ref(false);
    const samrockPollStatus = ref('');
    const samrockErrorMessage = ref('');
    let samrockPollInterval: number | null = null;
    let samrockPollInFlight = false;
    let pairingStoreId: string | null = null;

    function revokeSamRockQr() {
        if (samrockQrObjectUrl.value) {
            URL.revokeObjectURL(samrockQrObjectUrl.value);
            samrockQrObjectUrl.value = null;
        }
    }

    function stopSamRockPolling() {
        if (samrockPollInterval != null) {
            window.clearInterval(samrockPollInterval);
            samrockPollInterval = null;
        }
        samrockPollInFlight = false;
    }

    async function cancelSamRockPairing() {
        stopSamRockPolling();
        revokeSamRockQr();
        const sid = pairingStoreId;
        const otp = samrockOtp.value;
        pairingStoreId = null;
        if (sid && otp) {
            try {
                await api.delete(
                    `/stores/${sid}/samrock/otps/${encodeURIComponent(otp)}`,
                );
            } catch {
                // ignore cleanup errors
            }
        }
        samrockOtp.value = '';
        samrockExpiresAt.value = null;
        samrockErrorMessage.value = '';
        samrockPollStatus.value = '';
        samrockBusy.value = false;
    }

    async function pollSamRockStatus(onComplete: () => void | Promise<void>) {
        if (samrockPollInFlight || !samrockOtp.value || !pairingStoreId) {
            return;
        }
        samrockPollInFlight = true;
        const sid = pairingStoreId;
        const otp = samrockOtp.value;
        try {
            const statusRes = await api.get(
                `/stores/${sid}/samrock/otps/${encodeURIComponent(otp)}`,
            );
            const status = statusRes.data?.data?.status ?? '';
            if (status === 'completed') {
                stopSamRockPolling();
                await api.post(`/stores/${sid}/samrock/complete`, { otp });
                samrockOtp.value = '';
                pairingStoreId = null;
                revokeSamRockQr();
                samrockPollStatus.value = t('stores.samrock_pairing_complete');
                await onComplete();
            }
        } catch (err: unknown) {
            samrockErrorMessage.value = getApiErrorMessage(err, t('stores.samrock_error'));
            stopSamRockPolling();
        } finally {
            samrockPollInFlight = false;
        }
    }

    async function startSamRockPairing(onComplete: () => void | Promise<void>) {
        const sid = storeId();
        pairingStoreId = sid;
        samrockBusy.value = true;
        samrockErrorMessage.value = '';
        revokeSamRockQr();
        samrockOtp.value = '';
        stopSamRockPolling();

        try {
            const res = await api.post(`/stores/${sid}/samrock/otps`, {
                btc: true,
                btcln: true,
                lbtc: false,
                expires_in_seconds: 300,
            });
            const d = res.data?.data ?? {};
            const otp = d.otp ?? '';
            if (!otp) {
                samrockErrorMessage.value = t('stores.samrock_error');
                samrockBusy.value = false;
                pairingStoreId = null;
                return;
            }

            samrockOtp.value = otp;
            samrockExpiresAt.value = d.expiresAt ?? null;

            const qrRes = await api.get(
                `/stores/${sid}/samrock/otps/${encodeURIComponent(otp)}/qr`,
                { responseType: 'blob' },
            );
            revokeSamRockQr();
            samrockQrObjectUrl.value = URL.createObjectURL(qrRes.data);
            samrockBusy.value = false;
            samrockPollStatus.value = t('stores.samrock_waiting_scan');

            samrockPollInterval = window.setInterval(() => {
                void pollSamRockStatus(onComplete);
            }, 3000);
        } catch (err: unknown) {
            samrockErrorMessage.value = getApiErrorMessage(err, t('stores.samrock_error'));
            samrockBusy.value = false;
            pairingStoreId = null;
        }
    }

    onUnmounted(() => {
        stopSamRockPolling();
        revokeSamRockQr();
    });

    return {
        samrockOtp,
        samrockExpiresAt,
        samrockQrObjectUrl,
        samrockBusy,
        samrockPollStatus,
        samrockErrorMessage,
        cancelSamRockPairing,
        startSamRockPairing,
        stopSamRockPolling,
        revokeSamRockQr,
    };
}
