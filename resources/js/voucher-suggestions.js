// Suggested discounts are estimates; the server validates again when reserving seats.
export function initVoucherSuggestions(form, voucher, store, money) {
    const panel = document.getElementById('voucherSuggestions');
    if (!panel) return () => {};
    let timer, sequence = 0, lastSubtotal = -1;
    let choices = [];
    const estimate = document.createElement('p');
    estimate.className = 'small fw-semibold mt-2';
    panel.after(estimate);
    const subtotalForForm = () => [...form.querySelectorAll('.seat-input:checked:not(:disabled)')].reduce((n, seat) => n + Number(seat.dataset.price), 0)
        + [...form.querySelectorAll('.combo-quantity')].reduce((n, combo) => n + Math.max(0, Math.min(10, Math.trunc(Number(combo.value) || 0))) * Number(combo.dataset.price), 0);
    const showEstimate = () => {
        const choice = choices.find(v => v.code === voucher.value.trim().toUpperCase() && v.usable);
        estimate.textContent = choice ? `Giảm dự kiến ${money.format(choice.discount)}₫ · Còn ${money.format(Math.max(0, lastSubtotal - choice.discount))}₫ trước khi dùng điểm` : '';
    };
    voucher.addEventListener('input', showEstimate);
    return () => {
        const subtotal = subtotalForForm();
        if (subtotal === lastSubtotal) return;
        lastSubtotal = subtotal;
        const current = ++sequence;
        clearTimeout(timer);
        choices = [];
        estimate.textContent = '';
        if (!subtotal) { panel.textContent = 'Chọn ghế để xem voucher phù hợp.'; return; }
        panel.textContent = 'Đang tìm ưu đãi…';
        timer = setTimeout(async () => {
            try {
                const response = await fetch(`${panel.dataset.url}?subtotal=${subtotal}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
                if (!response.ok) throw new Error('Unavailable');
                const data = await response.json();
                if (current !== sequence) return;
                choices = data.vouchers;
                panel.replaceChildren();
                if (!choices.length) panel.textContent = 'Chưa có voucher phù hợp lúc này.';
                choices.forEach((choice, index) => {
                    const row = document.createElement('div');
                    row.className = 'border rounded p-2 mb-2 d-flex justify-content-between align-items-center gap-2';
                    const text = document.createElement('span');
                    text.textContent = `${choice.code} · ${choice.usable ? `Giảm ${money.format(choice.discount)}₫${index === 0 ? ' · Tiết kiệm nhất' : ''}` : choice.reason}`;
                    row.append(text);
                    if (choice.usable) {
                        const button = document.createElement('button');
                        button.type = 'button'; button.className = 'btn btn-sm btn-outline-primary text-nowrap'; button.textContent = 'Áp dụng';
                        button.addEventListener('click', () => { voucher.value = choice.code; store('voucher', choice.code); showEstimate(); });
                        row.append(button);
                    }
                    panel.append(row);
                });
                showEstimate();
            } catch {
                if (current === sequence) panel.textContent = 'Chưa tải được ưu đãi. Bạn vẫn có thể nhập mã trực tiếp.';
            }
        }, 500);
    };
}
