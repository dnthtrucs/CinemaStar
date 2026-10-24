const startButton = document.getElementById('start-scanner');
const reader = document.getElementById('qr-reader');
const message = document.getElementById('scanner-message');
const form = document.getElementById('scan-form');
const tokenInput = document.getElementById('ticket-token');
let scanner;

startButton?.addEventListener('click', async () => {
    startButton.disabled = true;
    reader.classList.remove('d-none');
    message.classList.remove('text-danger');
    message.textContent = 'Đang khởi động camera...';
    try {
        if (!scanner) {
            const { Html5Qrcode } = await import('html5-qrcode');
            scanner = new Html5Qrcode('qr-reader');
        }
        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 240, height: 240 } },
            async (decodedText) => {
                await scanner.stop();
                tokenInput.value = decodedText;
                form.submit();
            },
            () => {},
        );
        message.textContent = 'Đưa mã QR vé vào khung hình để check-in.';
    } catch {
        reader.classList.add('d-none');
        startButton.disabled = false;
        message.textContent = 'Không thể mở camera. Hãy cấp quyền camera; trên điện thoại cần truy cập bằng HTTPS hoặc localhost.';
        message.classList.add('text-danger');
    }
});
