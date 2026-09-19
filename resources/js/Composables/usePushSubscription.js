import { ref } from 'vue';
import axios from 'axios';

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

export function usePushSubscription() {
  const isSupported = typeof window !== 'undefined'
    && 'serviceWorker' in navigator
    && 'PushManager' in window;

  const isSubscribed = ref(false);
  const loading = ref(false);

  async function checkSubscribed() {
    if (!isSupported) return false;
    try {
      const reg = await navigator.serviceWorker.getRegistration();
      const sub = await reg?.pushManager.getSubscription();
      isSubscribed.value = Boolean(sub);
    } catch {
      isSubscribed.value = false;
    }
    return isSubscribed.value;
  }

  /**
   * @param {string} vapidPublicKey - from the `vapid_public_key` Inertia shared prop
   * @returns {Promise<{ok: boolean, reason?: string}>}
   */
  async function subscribe(vapidPublicKey) {
    if (!isSupported) return { ok: false, reason: 'unsupported' };
    if (!vapidPublicKey) return { ok: false, reason: 'not_configured' };

    loading.value = true;
    try {
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        return { ok: false, reason: 'denied' };
      }

      const reg = await navigator.serviceWorker.register('/service-worker.js');
      await navigator.serviceWorker.ready;

      const subscription = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
      });

      const json = subscription.toJSON();
      await axios.post('/push-subscriptions', {
        endpoint: json.endpoint,
        keys: json.keys,
      });

      isSubscribed.value = true;
      return { ok: true };
    } catch {
      return { ok: false, reason: 'error' };
    } finally {
      loading.value = false;
    }
  }

  async function unsubscribe() {
    if (!isSupported) return;
    loading.value = true;
    try {
      const reg = await navigator.serviceWorker.getRegistration();
      const sub = await reg?.pushManager.getSubscription();
      if (sub) {
        await axios.delete('/push-subscriptions', { data: { endpoint: sub.endpoint } });
        await sub.unsubscribe();
      }
      isSubscribed.value = false;
    } finally {
      loading.value = false;
    }
  }

  return { isSupported, isSubscribed, loading, checkSubscribed, subscribe, unsubscribe };
}
