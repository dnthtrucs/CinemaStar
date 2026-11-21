import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

const startButton = document.getElementById('start-scanner');
const reader = document.getElementById('qr-reader');
const message = document.getElementById('scanner-message');
const form = document.getElementById('scan-form');
const tokenInput = document.getElementById('ticket-token');
let scanner;
let submitted = false;
async function submitDecoded(decodedText) {
    if (submitted) return;
    submitted = true;
    if (scanner?.isScanning) await scanner.stop();
    tokenInput.value = decodedText;
    form.submit();
}

startButton?.addEventListener('click', async () => {
    startButton.disabled = true;
    reader.classList.remove('d-none');
    message.classList.remove('text-danger');
    message.textContent = 'Đang khởi động camera...';
    scanner ??= new Html5Qrcode('qr-reader', { formatsToSupport: [Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.QR_CODE] });

    try {
        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 280, height: 140 } },
            submitDecoded,
            () => {},
        );
        message.textContent = 'Đưa mã vạch vé vào khung hình để check-in.';
    } catch {
        reader.classList.add('d-none');
        startButton.disabled = false;
        message.textContent = 'Không thể mở camera. Hãy cấp quyền camera; trên điện thoại cần truy cập bằng HTTPS hoặc localhost.';
        message.classList.add('text-danger');
    }
});

const qrFile = document.getElementById('qr-file');
qrFile?.addEventListener('change', async () => {
    const file = qrFile.files?.[0];
    if (!file || submitted) return;
    startButton.disabled = true;
    qrFile.disabled = true;
    reader.classList.remove('d-none');
    message.classList.remove('text-danger');
    message.textContent = 'Đang đọc mã vạch trong ảnh…';
    try {
        scanner ??= new Html5Qrcode('qr-reader', { formatsToSupport: [Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.QR_CODE] });
        if (scanner.isScanning) await scanner.stop();
        await submitDecoded(await scanner.scanFile(file, true));
    } catch (_) {
        reader.classList.add('d-none');
        submitted = false;
        message.textContent = 'Chưa đọc được mã. Chọn ảnh rõ toàn bộ mã vạch hoặc nhập mã BK ở bên dưới.';
        message.classList.add('text-danger');
    } finally {
        qrFile.disabled = false;
        startButton.disabled = !window.isSecureContext;
        qrFile.value = '';
    }
});
if (startButton && !window.isSecureContext) {
    startButton.disabled = true;
    message.textContent = 'Camera trực tiếp cần HTTPS. Hãy chụp/chọn ảnh mã vạch hoặc nhập mã đơn bên dưới.';
}
