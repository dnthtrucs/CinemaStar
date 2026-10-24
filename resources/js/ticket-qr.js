const renderTicketQrs = async () => {
    const canvases = document.querySelectorAll('canvas[data-ticket-qr]');
    if (!canvases.length) return;

    let qrCode;
    try {
        ({ default: qrCode } = await import('qrcode'));
    } catch (error) {
        document.querySelectorAll('.ticket-qr-loading').forEach((loading) => {
            loading.textContent = 'Không thể tạo mã QR. Hãy dùng mã vé bên dưới.';
            loading.classList.add('text-danger');
        });
        console.error('Không thể nạp trình tạo QR:', error);
        return;
    }

    canvases.forEach(async (canvas) => {
        const wrapper = canvas.closest('.ticket-qr-wrap');
        const loading = wrapper?.querySelector('.ticket-qr-loading');

        try {
            await qrCode.toCanvas(canvas, canvas.dataset.ticketQr, {
                width: 220,
                margin: 1,
                errorCorrectionLevel: 'M',
                color: {
                    dark: '#171717',
                    light: '#ffffff',
                },
            });

            canvas.classList.add('is-ready');
            loading?.remove();
        } catch (error) {
            if (loading) {
                loading.textContent = 'Không thể tạo mã QR. Hãy dùng mã vé bên dưới.';
                loading.classList.add('text-danger');
            }
            console.error('Không thể tạo QR vé:', error);
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderTicketQrs);
} else {
    renderTicketQrs();
}
