import { defineStore } from 'pinia';
import { ref, shallowRef, computed } from 'vue';
import axios from 'axios';
import { startIncomingRing, startOutgoingRingback, stopRing } from '../Utils/sounds';
import { useUIStore } from './useUIStore';
import { useAuthStore } from './useAuthStore';

// STUN only — fine for most pairs, but at group scale "some pairs can't
// connect behind symmetric NAT" becomes a common partial-mesh failure. A
// TURN server (coturn / Twilio NTS) is the follow-up that fixes that.
const ICE_SERVERS = [
  { urls: 'stun:stun.l.google.com:19302' },
  { urls: 'stun:stun1.l.google.com:19302' },
];

/**
 * Mesh group calls: one RTCPeerConnection per remote participant, all
 * signaled pairwise through the server. 1-on-1 is simply a mesh of two.
 *
 * Glare avoidance without extra negotiation: whoever JOINS sends the offer
 * to everyone already in the call; existing participants only ever answer.
 */
export const useCallStore = defineStore('call', () => {
  // ── State ────────────────────────────────────────────────────────────
  // idle | calling | incoming | connecting | active
  const callState = ref('idle');
  const callId    = ref(null);
  const callType  = ref(null);   // 'audio' | 'video'
  const isCaller  = ref(false);
  const callError = ref(null);
  const caller    = ref(null);   // who rang us (incoming only)

  // userId → { id, name, display_name, avatar_url, status, stream }
  // status: 'invited' | 'connecting' | 'connected'
  // Replaced wholesale on every change so shallowRef consumers re-render.
  const participants = shallowRef(new Map());

  const localStream  = shallowRef(null);
  const screenStream = shallowRef(null);

  const isMuted         = ref(false);
  const isCamOff        = ref(false);
  const isScreenSharing = ref(false);
  const duration        = ref(0);

  // Recording is purely local (see useCallRecorder.js); the store only
  // tracks who has announced one so everyone else can be shown a notice.
  const isRecordingLocally = ref(false);
  const recordingBy        = ref(new Set()); // remote userIds currently recording

  const uiStore = useUIStore();

  // userId → { pc, pendingIce: [] } — not reactive, never rendered directly.
  const peers = new Map();

  let cameraTrack   = null;
  let durationTimer = null;
  let ringTimer     = null;
  let connectTimer  = null;

  const RING_TIMEOUT_MS    = 45_000;
  const CONNECT_TIMEOUT_MS = 20_000;

  // ── Derived ──────────────────────────────────────────────────────────
  const remoteParticipants = computed(() => Array.from(participants.value.values()));
  const remoteCount        = computed(() => participants.value.size);
  // First remote participant — what the 1-on-1 layout shows big.
  const remoteUser         = computed(() => remoteParticipants.value[0] ?? null);
  const remoteStream       = computed(() => remoteUser.value?.stream ?? null);
  const invitedCount       = computed(() => remoteParticipants.value.filter(p => p.status === 'invited').length);
  const recordingParticipants = computed(() => remoteParticipants.value.filter(p => recordingBy.value.has(p.id)));

  function setParticipant(user, patch = {}) {
    const next = new Map(participants.value);
    const existing = next.get(user.id) ?? { stream: null, status: 'invited' };
    next.set(user.id, { ...existing, ...user, ...patch });
    participants.value = next;
  }

  function dropParticipant(userId) {
    if (recordingBy.value.has(userId)) {
      const nextRec = new Set(recordingBy.value);
      nextRec.delete(userId);
      recordingBy.value = nextRec;
    }
    if (!participants.value.has(userId)) return;
    const next = new Map(participants.value);
    next.delete(userId);
    participants.value = next;
  }

  // ── Outgoing call ────────────────────────────────────────────────────
  /**
   * @param {object|object[]} callees  user object(s) {id, name, display_name, avatar_url}
   * @param {'audio'|'video'} type
   * @param {{context_type?: string, context_id?: number}} context
   */
  async function initiateCall(callees, type = 'video', context = {}) {
    if (callState.value !== 'idle') return;
    const list = Array.isArray(callees) ? callees : [callees];
    if (list.length === 0) return;

    callState.value = 'calling';
    callType.value  = type;
    isCaller.value  = true;
    callError.value = null;
    list.forEach(u => setParticipant(u, { status: 'invited' }));

    // The caller must already have media when the first joiner's offer
    // arrives, so ask for it now rather than on accept.
    try {
      await getLocalMedia();
    } catch (e) {
      uiStore.toastError(mediaErrorMessage(e));
      cleanup();
      reset();
      return;
    }

    try {
      const { data } = await axios.post('/calls', {
        callee_ids: list.map(u => u.id),
        call_type: type,
        ...context,
      });
      callId.value = data.callId;
      startOutgoingRingback();

      ringTimer = setTimeout(() => {
        if (callState.value === 'calling') cancelCall();
      }, RING_TIMEOUT_MS);
    } catch (e) {
      const msg = e?.response?.data?.message ?? 'Could not reach server.';
      uiStore.toastError(msg);
      cleanup();
      reset();
      callError.value = msg;
    }
  }

  async function cancelCall() {
    if (callState.value !== 'calling') return;
    try {
      await axios.post(`/calls/${callId.value}/leave`);
    } catch {}
    cleanup();
    reset();
  }

  // ── Incoming call ────────────────────────────────────────────────────
  function handleIncoming(data) {
    if (callState.value !== 'idle') {
      // Busy — decline silently.
      axios.post(`/calls/${data.callId}/reject`).catch(() => {});
      return;
    }
    callId.value    = data.callId;
    callType.value  = data.callType;
    caller.value    = data.caller;
    isCaller.value  = false;
    callState.value = 'incoming';

    // Everyone on the roster except me; the caller is already 'joined'.
    (data.participants ?? [data.caller]).forEach(p => {
      if (p.id === myId()) return;
      setParticipant(p, { status: p.status === 'joined' ? 'connecting' : 'invited' });
    });

    startIncomingRing();
    ringTimer = setTimeout(() => {
      if (callState.value === 'incoming') { cleanup(); reset(); }
    }, RING_TIMEOUT_MS);
  }

  async function acceptCall() {
    if (callState.value !== 'incoming') return;
    if (ringTimer) { clearTimeout(ringTimer); ringTimer = null; }
    stopRing();
    callState.value = 'connecting';
    startConnectTimeout();

    try {
      await getLocalMedia();
    } catch (e) {
      callError.value = 'Camera/microphone access denied.';
      uiStore.toastError(mediaErrorMessage(e));
      await rejectCall();
      return;
    }

    let joined = [];
    try {
      const { data } = await axios.post(`/calls/${callId.value}/accept`);
      joined = data?.participants ?? [];
    } catch (e) {
      uiStore.toastError(e?.response?.data?.message ?? 'Could not join the call.');
      cleanup();
      reset();
      return;
    }

    // Prune anyone who dropped while we were ringing, then offer to each
    // person already in the call — we are the joiner, so we offer.
    const joinedIds = new Set(joined.map(p => p.id));
    remoteParticipants.value.forEach(p => {
      if (p.status !== 'invited' && !joinedIds.has(p.id)) dropParticipant(p.id);
    });
    for (const p of joined) {
      setParticipant(p, { status: 'connecting' });
      await offerTo(p.id).catch(() => dropParticipant(p.id));
    }
  }

  async function rejectCall() {
    if (!['incoming', 'connecting'].includes(callState.value)) return;
    try {
      await axios.post(`/calls/${callId.value}/reject`);
    } catch {}
    cleanup();
    reset();
  }

  // ── Roster events ────────────────────────────────────────────────────
  function handleParticipantJoined(data) {
    if (data.callId !== callId.value) return;
    if (data.participant.id === myId()) return;

    // They will send us an offer; just make room for them.
    setParticipant(data.participant, { status: 'connecting' });

    // The "started recording" notice went out before they joined — repeat
    // it so the newcomer is told too. Others already know and stay quiet.
    if (isRecordingLocally.value) {
      axios.post(`/calls/${callId.value}/recording`, { recording: true }).catch(() => {});
    }

    if (callState.value === 'calling') {
      if (ringTimer) { clearTimeout(ringTimer); ringTimer = null; }
      stopRing();
      callState.value = 'connecting';
      startConnectTimeout();
    }
  }

  function handleParticipantLeft(data) {
    if (data.callId !== callId.value) return;

    removePeer(data.userId);

    if (data.callEnded) {
      cleanup();
      reset();
      return;
    }

    // Everyone else is gone — nothing to stay connected to.
    if (['connecting', 'active'].includes(callState.value) && remoteCount.value === 0) {
      leaveCall();
    }
  }

  function handleRejected(data) {
    if (data.callId !== callId.value) return;

    const who = participants.value.get(data.userId);
    dropParticipant(data.userId);

    if (data.callEnded) {
      uiStore.toastInfo(`${who?.display_name || who?.name || 'They'} declined the call.`);
      cleanup();
      reset();
    }
  }

  // ── Recording (consent notices only — media never touches the server) ─
  function handleRecordingToggled(data) {
    if (data.callId !== callId.value) return;
    if (data.userId === myId()) return;

    const already = recordingBy.value.has(data.userId);
    if (already === !!data.recording) return; // duplicate notice (re-announce for a late joiner)

    const next = new Set(recordingBy.value);
    if (data.recording) next.add(data.userId); else next.delete(data.userId);
    recordingBy.value = next;

    const who  = participants.value.get(data.userId);
    const name = who?.display_name || who?.name || 'Someone';
    if (data.recording) {
      uiStore.toastWarning(`${name} started recording this call.`, 6000);
    } else {
      uiStore.toastInfo(`${name} stopped recording.`);
    }
  }

  /**
   * Announce a local recording start/stop to everyone else. Resolves false
   * when the START notice could not be delivered — the caller must not
   * record in that case. A failed STOP notice is not worth blocking on.
   */
  async function setRecording(recording) {
    if (!recording) {
      isRecordingLocally.value = false;
      if (callState.value !== 'active' || !callId.value) return true;
      axios.post(`/calls/${callId.value}/recording`, { recording: false }).catch(() => {});
      return true;
    }

    if (callState.value !== 'active' || !callId.value) {
      uiStore.toastError('You can only record an active call.');
      return false;
    }

    try {
      await axios.post(`/calls/${callId.value}/recording`, { recording: true });
    } catch (e) {
      uiStore.toastError(e?.response?.data?.message ?? 'Could not notify the others about the recording.');
      return false;
    }

    isRecordingLocally.value = true;
    return true;
  }

  // ── WebRTC signaling (pairwise) ──────────────────────────────────────
  async function handleSignal(data) {
    if (data.callId !== callId.value) return;
    const from = data.fromUserId;
    const { type, data: payload } = data.signal;

    if (type === 'offer') {
      // A joiner is offering to us — answer on a fresh peer for them.
      if (!participants.value.has(from)) {
        setParticipant({ id: from, name: 'Participant' }, { status: 'connecting' });
      }
      const peer = ensurePeer(from);
      await peer.pc.setRemoteDescription(new RTCSessionDescription(payload));
      await drainPendingIce(from);
      const answer = await peer.pc.createAnswer();
      await peer.pc.setLocalDescription(answer);
      await sendSignal(from, { type: 'answer', data: { type: answer.type, sdp: answer.sdp } });

    } else if (type === 'answer') {
      const peer = peers.get(from);
      if (!peer) return;
      await peer.pc.setRemoteDescription(new RTCSessionDescription(payload));
      await drainPendingIce(from);

    } else if (type === 'ice') {
      const peer = peers.get(from);
      if (!peer) return;
      if (peer.pc.remoteDescription?.type) {
        await peer.pc.addIceCandidate(new RTCIceCandidate(payload)).catch(() => {});
      } else {
        peer.pendingIce.push(payload);
      }
    }
  }

  async function offerTo(userId) {
    const peer = ensurePeer(userId);
    const offer = await peer.pc.createOffer({
      offerToReceiveAudio: true,
      offerToReceiveVideo: callType.value === 'video',
    });
    await peer.pc.setLocalDescription(offer);
    await sendSignal(userId, { type: 'offer', data: { type: offer.type, sdp: offer.sdp } });
  }

  function ensurePeer(userId) {
    const existing = peers.get(userId);
    if (existing) return existing;

    const pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });
    const peer = { pc, pendingIce: [] };
    peers.set(userId, peer);

    localStream.value?.getTracks().forEach(track => pc.addTrack(track, localStream.value));

    // If a screen share is already running, new peers should get the screen
    // track too, not the (possibly disabled) camera.
    if (isScreenSharing.value && screenStream.value) {
      const screenTrack = screenStream.value.getVideoTracks()[0];
      const sender = pc.getSenders().find(s => s.track?.kind === 'video');
      if (screenTrack && sender) sender.replaceTrack(screenTrack).catch(() => {});
    }

    pc.ontrack = ({ streams }) => {
      const p = participants.value.get(userId);
      if (p) setParticipant(p, { stream: streams[0] });
    };

    pc.onicecandidate = ({ candidate }) => {
      if (candidate) sendSignal(userId, { type: 'ice', data: candidate.toJSON() }).catch(() => {});
    };

    const onConnected = () => {
      const p = participants.value.get(userId);
      if (p) setParticipant(p, { status: 'connected' });
      markActive();
    };

    pc.onconnectionstatechange = () => {
      if (pc.connectionState === 'connected') {
        onConnected();
      } else if (['failed', 'closed'].includes(pc.connectionState)) {
        removePeer(userId);
        if (['connecting', 'active'].includes(callState.value) && remoteCount.value === 0) {
          leaveCall();
        }
      }
    };

    // Fallback via ICE connection state (more reliable cross-browser)
    pc.oniceconnectionstatechange = () => {
      if (['connected', 'completed'].includes(pc.iceConnectionState)) onConnected();
    };

    return peer;
  }

  function removePeer(userId) {
    const peer = peers.get(userId);
    if (peer) {
      try { peer.pc.close(); } catch {}
      peers.delete(userId);
    }
    dropParticipant(userId);
  }

  async function drainPendingIce(userId) {
    const peer = peers.get(userId);
    if (!peer) return;
    const candidates = peer.pendingIce.splice(0);
    for (const c of candidates) {
      await peer.pc.addIceCandidate(new RTCIceCandidate(c)).catch(() => {});
    }
  }

  async function sendSignal(targetUserId, signal) {
    await axios.post(`/calls/${callId.value}/signal`, {
      target_user_id: targetUserId,
      signal,
    });
  }

  // ── Leave ────────────────────────────────────────────────────────────
  async function leaveCall() {
    if (callState.value === 'idle') return;
    if (callState.value === 'calling') return cancelCall();

    try {
      await axios.post(`/calls/${callId.value}/leave`);
    } catch {}
    cleanup();
    reset();
  }

  // Kept as the button/handler name the UI already uses.
  const endCall = leaveCall;

  // ── Local media controls ─────────────────────────────────────────────
  function toggleMute() {
    localStream.value?.getAudioTracks().forEach(t => { t.enabled = !t.enabled; });
    isMuted.value = !isMuted.value;
  }

  function toggleCamera() {
    localStream.value?.getVideoTracks().forEach(t => { t.enabled = !t.enabled; });
    isCamOff.value = !isCamOff.value;
  }

  // ── Screen sharing ───────────────────────────────────────────────────
  // A track swap on every peer's video sender — no renegotiation, no
  // signaling change. Only possible on calls that STARTED as video calls:
  // an audio-only call never negotiated a video m-line to replace.
  function videoSenders() {
    return Array.from(peers.values())
      .map(peer => peer.pc.getSenders().find(s => s.track?.kind === 'video'))
      .filter(Boolean);
  }

  async function startScreenShare() {
    if (isScreenSharing.value) return;
    if (callType.value !== 'video' || callState.value !== 'active') {
      uiStore.toastError('Screen sharing is only available during an active video call.');
      return;
    }

    const senders = videoSenders();
    if (senders.length === 0) {
      uiStore.toastError('Could not start screen sharing — no video track to replace.');
      return;
    }

    let stream;
    try {
      stream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
    } catch (e) {
      if (e?.name !== 'NotAllowedError') uiStore.toastError('Could not start screen sharing.');
      return;
    }

    const screenTrack = stream.getVideoTracks()[0];
    if (!screenTrack) {
      stream.getTracks().forEach(t => t.stop());
      return;
    }

    try {
      cameraTrack = localStream.value?.getVideoTracks()[0] ?? senders[0].track;
      await Promise.all(senders.map(s => s.replaceTrack(screenTrack)));
    } catch {
      stream.getTracks().forEach(t => t.stop());
      cameraTrack = null;
      uiStore.toastError('Could not start screen sharing.');
      return;
    }

    screenStream.value    = stream;
    isScreenSharing.value = true;
    screenTrack.onended   = () => stopScreenShare();
  }

  async function stopScreenShare() {
    if (!isScreenSharing.value) return;

    if (cameraTrack) {
      await Promise.all(videoSenders().map(s => s.replaceTrack(cameraTrack).catch(() => {})));
    }

    screenStream.value?.getTracks().forEach(t => t.stop());
    screenStream.value    = null;
    cameraTrack           = null;
    isScreenSharing.value = false;
  }

  function toggleScreenShare() {
    return isScreenSharing.value ? stopScreenShare() : startScreenShare();
  }

  // ── Internals ────────────────────────────────────────────────────────
  function myId() {
    return useAuthStore().user?.id ?? null;
  }

  async function getLocalMedia() {
    localStream.value = await navigator.mediaDevices.getUserMedia({
      audio: true,
      video: callType.value === 'video',
    });
  }

  function mediaErrorMessage(e) {
    switch (e?.name) {
      case 'NotAllowedError':
      case 'PermissionDeniedError':
        return 'Microphone/camera permission denied — allow it in the browser and try again.';
      case 'NotFoundError':
        return 'No microphone/camera found on this device.';
      case 'NotReadableError':
        return 'Microphone/camera is in use by another app.';
      default:
        return 'Could not start the call — media or connection error.';
    }
  }

  function startConnectTimeout() {
    if (connectTimer) clearTimeout(connectTimer);
    connectTimer = setTimeout(() => {
      if (callState.value === 'connecting') {
        uiStore.toastError('Call could not connect — check mic/camera permissions and try again.');
        leaveCall();
      }
    }, CONNECT_TIMEOUT_MS);
  }

  function markActive() {
    if (connectTimer) { clearTimeout(connectTimer); connectTimer = null; }
    if (callState.value !== 'active') callState.value = 'active';
    if (durationTimer) return;
    duration.value = 0;
    durationTimer  = setInterval(() => { duration.value++; }, 1000);
  }

  function cleanup() {
    peers.forEach(peer => { try { peer.pc.close(); } catch {} });
    peers.clear();
    screenStream.value?.getTracks().forEach(t => t.stop());
    screenStream.value = null;
    cameraTrack        = null;
    localStream.value?.getTracks().forEach(t => t.stop());
    localStream.value  = null;
    participants.value = new Map();
    if (durationTimer) { clearInterval(durationTimer); durationTimer = null; }
    if (ringTimer)     { clearTimeout(ringTimer); ringTimer = null; }
    if (connectTimer)  { clearTimeout(connectTimer); connectTimer = null; }
    stopRing();
  }

  function reset() {
    callState.value       = 'idle';
    callId.value          = null;
    callType.value        = null;
    caller.value          = null;
    isCaller.value        = false;
    isMuted.value         = false;
    isCamOff.value        = false;
    isScreenSharing.value = false;
    duration.value        = 0;
    isRecordingLocally.value = false;
    recordingBy.value        = new Set();
  }

  return {
    callState, callId, callType, isCaller, callError, caller,
    participants, remoteParticipants, remoteCount, remoteUser, remoteStream, invitedCount,
    localStream, screenStream,
    isMuted, isCamOff, isScreenSharing, duration,
    isRecordingLocally, recordingBy, recordingParticipants,
    initiateCall, cancelCall,
    acceptCall, rejectCall, leaveCall, endCall,
    toggleMute, toggleCamera,
    startScreenShare, stopScreenShare, toggleScreenShare,
    setRecording, handleRecordingToggled,
    handleIncoming, handleParticipantJoined, handleParticipantLeft, handleRejected, handleSignal,
  };
});
