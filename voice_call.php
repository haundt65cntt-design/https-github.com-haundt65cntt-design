<?php
session_start();
require_once __DIR__ . '/config/database.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$room_id = trim($_GET['room_id'] ?? '');
$role = trim($_GET['role'] ?? '');
$other_id = (int)($_GET['other_id'] ?? 0);
if (!$room_id || !in_array($role, ['caller', 'callee']) || !$other_id) {
    die('Invalid parameters');
}
$stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
$stmt->execute([$other_id]);
$other = $stmt->fetch();
$other_name = $other ? ($other['full_name'] ?: $other['username']) : ('User' . $other_id);
$base = dirname($_SERVER['SCRIPT_NAME']);
$base = ($base === '/' || $base === '\\') ? '' : rtrim($base, '/') . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Voice Call - <?php echo htmlspecialchars($other_name); ?></title>
    <link href="assets/css/theme.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", system-ui, sans-serif; margin: 0; padding: 20px; background: var(--gradient-bg); background-attachment: fixed; color: var(--text-primary); min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .card { background: var(--surface); backdrop-filter: blur(14px); border: 1px solid var(--border); border-radius: var(--radius); padding: 32px; text-align: center; max-width: 360px; width: 100%; box-shadow: var(--shadow-lg); }
        h2 { margin: 0 0 8px; font-size: 1.25rem; color: var(--text-primary); }
        .status { color: var(--text-secondary); margin-bottom: 24px; font-size: 0.9rem; }
        .avatar { width: 80px; height: 80px; border-radius: 50%; background: var(--gradient-primary); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 16px; color: #fff; box-shadow: 0 8px 24px rgba(59, 130, 246, 0.35); }
        .btn { border: none; border-radius: 50%; width: 56px; height: 56px; cursor: pointer; font-size: 1.25rem; margin: 0 12px; transition: transform 0.2s; }
        .btn:hover { transform: scale(1.05); }
        .btn-hangup { background: #ef4444; color: #fff; }
        .btn-hangup:hover { background: #dc2626; }
        .btn-mute { background: #64748b; color: #fff; }
        .btn-mute:hover { background: #475569; }
        .btn-mute.active { background: #ef4444; }
        .btn-speaker { background: var(--accent); color: #fff; }
        .btn-speaker:hover { background: var(--accent-dark); }
        .btn-speaker.active { background: #22c55e; }
        .controls { margin-top: 24px; display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; }
        audio { width: 100%; margin-top: 16px; display: none; }
        .unmute-hint { margin-top: 12px; padding: 10px 16px; background: rgba(251, 191, 36, 0.2); border-radius: 8px; color: #d97706; font-size: 0.85rem; cursor: pointer; }
        .unmute-hint:hover { background: rgba(251, 191, 36, 0.35); }
    </style>
</head>
<body class="voice-call-page">
    <div class="card voice-call-card">
        <div class="avatar" id="avatarLetter"><?php echo strtoupper(mb_substr($other_name, 0, 1)); ?></div>
        <h2><?php echo htmlspecialchars($other_name); ?></h2>
        <p class="status" id="status"><?php echo $role === 'caller' ? 'Calling...' : 'Connecting...'; ?></p>
        <div class="controls">
            <button class="btn btn-mute" id="muteBtn" title="Mute"><i class="fas fa-microphone"></i></button>
            <button class="btn btn-speaker" id="speakerBtn" title="Speaker"><i class="fas fa-volume-up"></i></button>
            <button class="btn btn-hangup" id="hangupBtn" title="Hang Up"><i class="fas fa-phone-slash"></i></button>
        </div>
        <div class="unmute-hint" id="unmuteHint" style="display:none;">🔊 Click here to enable remote audio</div>
        <audio id="remoteAudio" playsinline></audio>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js"></script>
    <script>
        const ROOM_ID = <?php echo json_encode($room_id); ?>;
        const ROLE = <?php echo json_encode($role); ?>;
        const API = <?php echo json_encode($base); ?> + 'ajax/voice_call_handler.php';
        const config = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };
        let pc = null;
        let localStream = null;
        const statusEl = document.getElementById('status');
        const remoteAudio = document.getElementById('remoteAudio');
        const unmuteHint = document.getElementById('unmuteHint');
        let isMuted = false;
        let isSpeakerOn = true;
        let weEndedCall = false;

        function setStatus(s) { statusEl.textContent = s; }

        function doHangup(closeWindow) {
            weEndedCall = true;
            setStatus('Call ended');
            if (localStream) localStream.getTracks().forEach(t => t.stop());
            if (pc) pc.close();
            fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'end', room_id: ROOM_ID }), credentials: 'include' }).catch(() => {});
            if (closeWindow !== false) setTimeout(() => window.close(), 1000);
        }

        async function startCall() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                setStatus('Cannot access microphone: ' + e.message);
                return;
            }
            pc = new RTCPeerConnection(config);
            localStream.getTracks().forEach(t => pc.addTrack(t, localStream));
            pc.ontrack = e => {
                if (e.streams && e.streams[0]) {
                    remoteAudio.srcObject = e.streams[0];
                    remoteAudio.muted = false;
                    remoteAudio.volume = 1;
                    const p = remoteAudio.play();
                    if (p && typeof p.then === 'function') {
                        p.then(() => { unmuteHint.style.display = 'none'; }).catch(() => { unmuteHint.style.display = 'block'; });
                    }
                }
            };
            pc.onicecandidate = e => { if (e.candidate) sendIce(e.candidate); };
            pc.onconnectionstatechange = () => {
                if (pc.connectionState === 'connected') {
                    setStatus('Connected - In call');
                    if (remoteAudio.srcObject) {
                        remoteAudio.play().then(() => { unmuteHint.style.display = 'none'; }).catch(() => { unmuteHint.style.display = 'block'; });
                    }
                } else if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected') setStatus('Connection lost');
            };

            if (ROLE === 'caller') {
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'set_offer', room_id: ROOM_ID, sdp: JSON.stringify(pc.localDescription) }), credentials: 'include' });
                setStatus('Waiting for answer...');
                waitForAnswer();
            } else {
                waitForOffer();
            }
            pollIce();
        }

        async function waitForOffer() {
            const res = await fetch(API + '?action=get_offer&room_id=' + encodeURIComponent(ROOM_ID), { credentials: 'include' });
            const data = await res.json();
            if (data.offer) {
                await pc.setRemoteDescription(new RTCSessionDescription(JSON.parse(data.offer)));
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'set_answer', room_id: ROOM_ID, sdp: JSON.stringify(pc.localDescription) }), credentials: 'include' });
                setStatus('Connected - In call');
                return;
            }
            setTimeout(waitForOffer, 500);
        }

        async function waitForAnswer() {
            const res = await fetch(API + '?action=get_answer&room_id=' + encodeURIComponent(ROOM_ID), { credentials: 'include' });
            const data = await res.json();
            if (data.answer) {
                await pc.setRemoteDescription(new RTCSessionDescription(JSON.parse(data.answer)));
                setStatus('Connected - In call');
                return;
            }
            setTimeout(waitForAnswer, 500);
        }
        pollCallStatus();

        async function sendIce(candidate) {
            await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'add_ice', room_id: ROOM_ID, candidate: JSON.stringify(candidate) }), credentials: 'include' });
        }

        let lastIceId = 0;
        async function pollIce() {
            const res = await fetch(API + '?action=get_ice&room_id=' + encodeURIComponent(ROOM_ID) + '&after_id=' + lastIceId, { credentials: 'include' });
            const data = await res.json();
            if (data.candidates && pc) {
                for (const c of data.candidates) {
                    try { await pc.addIceCandidate(new RTCIceCandidate(JSON.parse(c.candidate))); } catch (e) {}
                    if (c.id > lastIceId) lastIceId = c.id;
                }
            }
            setTimeout(pollIce, 800);
        }

        async function pollCallStatus() {
            if (weEndedCall) return;
            const res = await fetch(API + '?action=get_status&room_id=' + encodeURIComponent(ROOM_ID), { credentials: 'include' });
            const data = await res.json();
            if (data.success && data.status === 'ended') {
                weEndedCall = true;
                setStatus('Other party hung up');
                if (localStream) localStream.getTracks().forEach(t => t.stop());
                if (pc) pc.close();
                setTimeout(() => window.close(), 1500);
                return;
            }
            setTimeout(pollCallStatus, 1200);
        }

        document.getElementById('muteBtn').onclick = function() {
            if (!localStream) return;
            isMuted = !isMuted;
            localStream.getAudioTracks().forEach(t => { t.enabled = !isMuted; });
            this.classList.toggle('active', isMuted);
            this.title = isMuted ? 'CancelMute' : 'Mute';
            this.querySelector('i').className = isMuted ? 'fas fa-microphone-slash' : 'fas fa-microphone';
        };

        document.getElementById('speakerBtn').onclick = async function() {
            isSpeakerOn = !isSpeakerOn;
            this.classList.toggle('active', isSpeakerOn);
            this.title = isSpeakerOn ? 'Speaker On' : 'Speaker Off';
            this.querySelector('i').className = isSpeakerOn ? 'fas fa-volume-up' : 'fas fa-volume-mute';
            if (remoteAudio.setSinkId) {
                try {
                    const devs = await navigator.mediaDevices.enumerateDevices();
                    const out = devs.find(d => d.kind === 'audiooutput');
                    if (out && out.deviceId) remoteAudio.setSinkId(isSpeakerOn ? out.deviceId : '');
                } catch (e) {}
            }
            remoteAudio.volume = isSpeakerOn ? 1 : 0.5;
        };

        document.getElementById('hangupBtn').onclick = () => doHangup(true);

        unmuteHint.onclick = function() {
            remoteAudio.play().then(() => { unmuteHint.style.display = 'none'; }).catch(() => {});
        };

        startCall();
    </script>
    <?php include_once 'includes/copyright_footer.php'; ?>
</body>
</html>
