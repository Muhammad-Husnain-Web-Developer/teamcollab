/**
 * Call/notification sounds generated with the Web Audio API — no audio
 * assets needed. The AudioContext is resumed on the first user gesture
 * (browser autoplay policy).
 */

let ctx = null;
let ringInterval = null;

function audioCtx() {
  if (!ctx) {
    const Ctor = window.AudioContext || window.webkitAudioContext;
    if (!Ctor) return null;
    ctx = new Ctor();
  }
  if (ctx.state === 'suspended') {
    ctx.resume().catch(() => {});
  }
  return ctx;
}

/** Schedule a single tone `start` seconds from now. */
function tone(freq, start, duration, volume = 0.15, type = 'sine') {
  const c = audioCtx();
  if (!c) return;

  const osc  = c.createOscillator();
  const gain = c.createGain();
  const t0   = c.currentTime + start;

  osc.type = type;
  osc.frequency.value = freq;

  // Short fade in/out to avoid clicks
  gain.gain.setValueAtTime(0, t0);
  gain.gain.linearRampToValueAtTime(volume, t0 + 0.015);
  gain.gain.setValueAtTime(volume, t0 + duration - 0.03);
  gain.gain.linearRampToValueAtTime(0, t0 + duration);

  osc.connect(gain);
  gain.connect(c.destination);
  osc.start(t0);
  osc.stop(t0 + duration);
}

/** Classic dual-tone incoming ring: two bursts, repeating every 3s. */
export function startIncomingRing() {
  stopRing();
  const burst = () => {
    tone(440, 0,   0.4, 0.12);
    tone(480, 0,   0.4, 0.12);
    tone(440, 0.6, 0.4, 0.12);
    tone(480, 0.6, 0.4, 0.12);
  };
  burst();
  ringInterval = setInterval(burst, 3000);
}

/** Outgoing ringback: single soft tone every 4s while waiting for answer. */
export function startOutgoingRingback() {
  stopRing();
  const burst = () => tone(425, 0, 1.0, 0.07);
  burst();
  ringInterval = setInterval(burst, 4000);
}

export function stopRing() {
  if (ringInterval) {
    clearInterval(ringInterval);
    ringInterval = null;
  }
}

/** Short two-note ding for new notifications. */
export function playNotificationDing() {
  tone(880,  0,    0.12, 0.10);
  tone(1318, 0.10, 0.18, 0.08);
}

// Unlock the AudioContext on the first user interaction
if (typeof window !== 'undefined') {
  const unlock = () => audioCtx();
  window.addEventListener('pointerdown', unlock, { once: true });
  window.addEventListener('keydown', unlock, { once: true });
}
