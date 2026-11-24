const form = document.getElementById('exchangeForm');
if (form) form.addEventListener('change', () => {
    const selected = [...form.querySelectorAll('[name="seats[]"]:checked')];
    const total = Math.max(0, selected.reduce((sum,s)=>sum+Number(s.dataset.price),0)+Number(form.dataset.combos)-Number(form.dataset.discount));
    const difference = total-Number(form.dataset.old);
    const money = new Intl.NumberFormat('vi-VN');
    document.getElementById('exchangeQuote').textContent = `${selected.length} vị trí · Tổng mới dự kiến ${money.format(total)}₫ · ${difference>=0?'Cần bù':'Cần hoàn'} ${money.format(Math.abs(difference))}₫. Máy chủ kiểm tra giá và ghế khi gửi, duyệt.`;
});
