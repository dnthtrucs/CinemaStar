import { suggestAdjacentSeats } from './seat-suggestions.js';
const form = document.getElementById('seatForm');
const seats = [...form.querySelectorAll('.seat-input')];
const combos = [...form.querySelectorAll('.combo-quantity')];
const voucher = form.querySelector('[name="voucher_code"]');
const submit = document.getElementById('submitSeats');
const storageKey = `cinemastar:showtime:${form.dataset.showtimeId}:`;
const money = new Intl.NumberFormat('vi-VN');

function store(key, value) {
    try { localStorage.setItem(storageKey + key, value); } catch { /* Storage may be disabled. */ }
}

try {
    const savedSeats = JSON.parse(localStorage.getItem(storageKey + 'seats') || '[]');
    if (Array.isArray(savedSeats)) seats.forEach(seat => { seat.checked = !seat.disabled && savedSeats.map(String).includes(seat.value); });
    const savedCombos = JSON.parse(localStorage.getItem(storageKey + 'concessions') || '{}');
    if (savedCombos && typeof savedCombos === 'object') combos.forEach(combo => {
        if (Object.hasOwn(savedCombos, combo.dataset.id)) combo.value = savedCombos[combo.dataset.id];
    });
    if (!voucher.value) voucher.value = localStorage.getItem(storageKey + 'voucher') || '';
} catch { /* Keep form defaults when saved data is invalid. */ }

function quantity(input) {
    return Math.max(0, Math.min(10, Math.trunc(Number(input.value) || 0)));
}

let step = 1;
function update() {
    const checked = seats.filter(seat => seat.checked && !seat.disabled);
    const selectedCombos = combos.filter(combo => quantity(combo) > 0);
    combos.forEach(combo => { combo.value = quantity(combo); });
    document.getElementById('selectedSeats').textContent = checked.length ? checked.map(seat => seat.dataset.label).join(', ') : 'Chưa chọn ghế';
    document.getElementById('selectedCombos').textContent = selectedCombos.length ? selectedCombos.map(combo => `${combo.dataset.label} × ${combo.value}`).join(', ') : 'Không chọn combo';
    const seatsTotal = checked.reduce((sum, seat) => sum + Number(seat.dataset.price), 0);
    const combosTotal = selectedCombos.reduce((sum, combo) => sum + quantity(combo) * Number(combo.dataset.price), 0);
    document.getElementById('totalPrice').textContent = money.format(seatsTotal + (step === 2 ? combosTotal : 0)) + '₫';
    document.getElementById('seatStepTotal').textContent = money.format(seatsTotal) + '₫';
    document.getElementById('nextConcessions').disabled = !checked.length;
    document.getElementById('mobileNext').disabled = !checked.length;
    const tooMany = selectedCombos.length > 5;
    const error = document.getElementById('comboError');
    if (error) error.hidden = !tooMany;
    if (submit) submit.disabled = !checked.length || tooMany;
    store('seats', JSON.stringify(checked.map(seat => seat.value)));
    store('concessions', JSON.stringify(Object.fromEntries(selectedCombos.map(combo => [combo.dataset.id, quantity(combo)]))));
}

seats.forEach(seat => seat.addEventListener('change', update));
combos.forEach(combo => combo.addEventListener('input', update));
form.querySelectorAll('[data-combo-step]').forEach(button => button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.comboTarget);
    input.value = quantity(input) + Number(button.dataset.comboStep);
    update();
}));
voucher.addEventListener('input', () => store('voucher', voucher.value));
form.addEventListener('submit', event => {
    if (step !== 2) { event.preventDefault(); return; }
    if (!seats.some(seat => seat.checked && !seat.disabled) || combos.filter(combo => quantity(combo) > 0).length > 5) {
        event.preventDefault();
        return;
    }
});
update();

function showStep(next) {
    if (next === 2 && !seats.some(seat => seat.checked && !seat.disabled)) return;
    step = next;
    document.getElementById('mobileNext').hidden = step !== 1;
    document.getElementById('seatStep').hidden = step !== 1;
    document.getElementById('concessionStep').hidden = step !== 2;
    document.querySelector('[data-seat-step]').hidden = step !== 1;
    document.getElementById('bookingStep').textContent = step === 1 ? 'Bước 1/3 · Chọn ghế' : 'Bước 2/3 · Chọn bắp nước';
    document.getElementById('totalLabel').textContent = step === 1 ? 'Tiền ghế' : 'Tổng tiền ghế và bắp nước';
    if (submit) submit.hidden = step !== 2;
    const login = document.getElementById('loginToBook');
    if (login) login.hidden = step !== 2;
    update();
    document.getElementById('bookingStep').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
document.getElementById('nextConcessions').addEventListener('click', () => showStep(2));
document.getElementById('backSeats').addEventListener('click', () => showStep(1));

document.getElementById('mobileNext').addEventListener('click', () => showStep(2));
let seatZoom = 1;
function zoomSeats(delta) {
    seatZoom = Math.max(0.75, Math.min(1.75, seatZoom + delta));
    document.getElementById('seatStep').style.setProperty('--seat-zoom', seatZoom);
}
document.getElementById('seatZoomOut').addEventListener('click', () => zoomSeats(-0.25));
document.getElementById('seatZoomIn').addEventListener('click', () => zoomSeats(0.25));
const summary = document.getElementById('bookingSummary');
if (window.ResizeObserver) new ResizeObserver(() => {
    document.querySelector('.booking-page').style.setProperty('--booking-bottom-height', `${summary.getBoundingClientRect().height}px`);
}).observe(summary);

const liveStatus = document.getElementById('seatLiveStatus');
let refreshing = false;
let bookingOpen = true;
async function refreshSeats() {
    if (refreshing || document.hidden) return;
    refreshing = true;
    try {
        const response = await fetch(form.dataset.availabilityUrl, { cache: 'no-store', headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('availability');
        const state = await response.json();
        bookingOpen = state.open;
        const unavailable = new Set([...state.unavailable, ...state.inactive].map(String));
        const removed = [];
        seats.forEach(seat => {
            const blocked = !state.open || seat.dataset.inactive === '1' || seat.dataset.seatIds.split(',').some(id => unavailable.has(id));
            if (blocked && seat.checked) { removed.push(seat.dataset.label); seat.checked = false; }
            seat.disabled = blocked;
            seat.nextElementSibling.classList.toggle('booked', blocked);
        });
        update();
        liveStatus.textContent = !state.open ? 'Suất chiếu đã ngừng nhận đặt vé. Vui lòng đổi suất.'
            : removed.length ? `Ghế ${removed.join(', ')} vừa hết chỗ và đã bỏ khỏi lựa chọn. Vui lòng chọn lại.`
            : 'Đã cập nhật ghế · ' + new Date().toLocaleTimeString('vi-VN');
        return true;
    } catch {
        liveStatus.textContent = 'Chưa cập nhật được ghế. Kiểm tra kết nối; máy chủ sẽ kiểm tra lại trước khi tạo đơn.';
        return false;
    } finally { refreshing = false; }
}
document.getElementById('suggestSeats').addEventListener('click', async () => {
    if (!await refreshSeats() || !bookingOpen) return;
    const chosen = suggestAdjacentSeats(seats.map(seat => ({
        id: seat.value, row: seat.dataset.row, number: Number(seat.dataset.number),
        capacity: Number(seat.dataset.capacity), unavailable: seat.disabled,
    })), Number(document.getElementById('partySize').value));
    if (!chosen.length) { liveStatus.textContent = 'Không còn đủ ghế liền nhau cho nhóm này. Hãy giảm số người hoặc đổi suất.'; return; }
    seats.forEach(seat => { seat.checked = chosen.includes(seat.value); });
    update();
    liveStatus.textContent = 'Đã gợi ý nhóm ghế liền nhau gần giữa phòng. Bạn có thể đổi ghế trước khi tiếp tục.';
});
setInterval(refreshSeats, 5000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshSeats(); });
window.addEventListener('pageshow', refreshSeats);
refreshSeats();
