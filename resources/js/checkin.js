import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import { decodeTicketCanvas, decodeTicketImage, cameraErrorMessage, preferredCameraConfig } from './ticket-decoder';

const startButton = document.getElementById('start-scanner');
const stopButton = document.getElementById('stop-scanner');
const cameraSelect = document.getElementById('scanner-camera');
const torchButton = document.getElementById('scanner-torch');
const reader = document.getElementById('qr-reader');
const message = document.getElementById('scanner-message');
const form = document.getElementById('scan-form');
const tokenInput = document.getElementById('ticket-token');
const qrFile = document.getElementById('qr-file');
let scanner;
let submitted = false;
let busy = false;
let torch = false;
let generation = 0;
let scanTimer;

function status(text, error = false) {
    message.textContent = text;
    message.classList.toggle('text-danger', error);
}
function getScanner() {
    return scanner ??= new Html5Qrcode('qr-reader', {
        formatsToSupport: [Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.QR_CODE],
        useBarCodeDetectorIfSupported: true,
    });
}
async function stopCamera() {
    generation++;
    clearTimeout(scanTimer);
    if (scanner?.isScanning) {
        try { await scanner.stop(); } catch { /* A decoded ticket must still submit if camera cleanup fails. */ }
    }
    stopButton.hidden = true;
    torchButton.hidden = true;
    torch = false;
    torchButton.textContent = 'Bật đèn pin';
    torchButton.setAttribute('aria-pressed', 'false');
}
async function submitDecoded(decodedText) {
    if (submitted || !decodedText?.trim()) return;
    submitted = true;
    status('Đã đọc được mã. Đang xác thực vé...');
    await stopCamera();
    tokenInput.value = decodedText.trim();
    form.requestSubmit();
}
function scanHighResolution() {
    const run = ++generation;
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    const tick = () => {
        if (run !== generation || submitted || !scanner?.isScanning) return;
        const video = reader.querySelector('video');
        if (!document.hidden && video?.videoWidth && video.readyState >= 2) {
            // Decode at camera resolution, independently of the narrow phone preview.
            const scale = Math.min(1, 1600 / video.videoWidth);
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            try {
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                const value = decodeTicketCanvas(canvas);
                if (run === generation) { void submitDecoded(value); return; }
            } catch { /* No readable code in this frame; keep scanning. */ }
        }
        scanTimer = setTimeout(tick, 350);
    };
    tick();
}
async function listCameras() {
    try {
        const devices = (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === 'videoinput');
        const active = scanner.getRunningTrackSettings().deviceId;
        cameraSelect.replaceChildren();
        devices.forEach((device, index) => {
            const option = document.createElement('option');
            option.value = device.deviceId;
            option.textContent = device.label || `Camera ${index + 1}`;
            option.selected = device.deviceId === active;
            cameraSelect.append(option);
        });
        document.getElementById('scanner-camera-control').hidden = devices.length < 2;
    } catch { /* Camera switching is optional. */ }
}
async function startCamera(deviceId) {
    if (busy || submitted) return;
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        status('Camera trực tiếp cần HTTPS và Safari/Chrome. Hãy mở website HTTPS hoặc chụp/chọn ảnh mã vạch.', true);
        return;
    }
    busy = true;
    startButton.disabled = true;
    cameraSelect.disabled = true;
    qrFile.disabled = true;
    reader.classList.remove('d-none');
    status('Đang khởi động camera...');
    try {
        await stopCamera();
        const camera = preferredCameraConfig(deviceId);
        const config = { fps: 5, disableFlip: false }; // Full frame: do not crop long Code 128 tickets.
        try {
            await getScanner().start(camera, { ...config, videoConstraints: { ...camera, width: { ideal: 1920 }, height: { ideal: 1080 } } }, submitDecoded, () => {});
        } catch (error) {
            if (/NotAllowed|PermissionDenied|NotReadable/i.test(error?.name || String(error))) throw error;
            await getScanner().start(camera, config, submitDecoded, () => {});
        }
        if (submitted) return;
        const video = reader.querySelector('video');
        video?.setAttribute('playsinline', '');
        video?.setAttribute('webkit-playsinline', '');
        try {
            const caps = scanner.getRunningTrackCapabilities();
            if (caps.focusMode?.includes('continuous')) await scanner.applyVideoConstraints({ advanced: [{ focusMode: 'continuous' }] });
            torchButton.hidden = !caps.torch;
        } catch { /* Focus and torch are not available on every phone. */ }
        stopButton.hidden = false;
        status('Giữ toàn bộ mã vạch trong hình, ngang hoặc dọc đều được; không cắt hai đầu. Giữ máy ổn định; tăng sáng màn hình vé và tránh phản chiếu.');
        scanHighResolution();
        await listCameras();
    } catch (error) {
        await stopCamera();
        reader.classList.add('d-none');
        status(cameraErrorMessage(error), true);
    } finally {
        busy = false;
        startButton.disabled = submitted;
        cameraSelect.disabled = false;
        qrFile.disabled = submitted;
    }
}
startButton?.addEventListener('click', () => startCamera(cameraSelect.value || undefined));
stopButton?.addEventListener('click', async () => {
    if (busy) return;
    await stopCamera(); reader.classList.add('d-none');
    status('Đã tắt camera. Bạn có thể mở lại hoặc chọn ảnh mã vạch.');
});
cameraSelect?.addEventListener('change', () => startCamera(cameraSelect.value));
torchButton?.addEventListener('click', async () => {
    if (!scanner?.isScanning) return;
    try {
        await scanner.applyVideoConstraints({ advanced: [{ torch: !torch }] });
        torch = !torch;
        torchButton.setAttribute('aria-pressed', String(torch));
        torchButton.textContent = torch ? 'Tắt đèn pin' : 'Bật đèn pin';
    } catch { status('Camera này không hỗ trợ đèn pin. Hãy tăng ánh sáng hoặc chọn camera khác.'); }
});
qrFile?.addEventListener('change', async () => {
    const file = qrFile.files?.[0];
    if (!file || submitted || busy) return;
    busy = true;
    startButton.disabled = true;
    qrFile.disabled = true;
    reader.classList.remove('d-none');
    status('Đang đọc mã vạch trong ảnh…');
    try {
        await stopCamera();
        let value;
        try { value = await getScanner().scanFile(file, true); }
        catch { value = await decodeTicketImage(file); }
        await submitDecoded(value);
    } catch {
        status('Chưa đọc được mã. Chọn ảnh gốc rõ toàn bộ mã vạch, không cắt hai đầu; hoặc nhập mã BK bên dưới.', true);
    } finally {
        busy = false;
        qrFile.disabled = submitted;
        startButton.disabled = submitted;
        qrFile.value = '';
    }
});
window.addEventListener('pagehide', () => { void stopCamera(); });
if (startButton && !window.isSecureContext) {
    startButton.disabled = true;
    status('Camera trực tiếp cần HTTPS. Hãy chụp/chọn ảnh mã vạch hoặc nhập mã đơn bên dưới.', true);
}
