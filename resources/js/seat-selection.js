const form = document.getElementById('seatForm');
const seats = [...form.querySelectorAll('.seat-input:not(:disabled)')];
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
    if (Array.isArray(savedSeats)) seats.forEach(seat => { seat.checked = savedSeats.map(String).includes(seat.value); });
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
    const checked = seats.filter(seat => seat.checked);
    const selectedCombos = combos.filter(combo => quantity(combo) > 0);
    combos.forEach(combo => { combo.value = quantity(combo); });
    document.getElementById('selectedSeats').textContent = checked.length ? checked.map(seat => seat.dataset.label).join(', ') : 'Chưa chọn ghế';
    document.getElementById('selectedCombos').textContent = selectedCombos.length ? selectedCombos.map(combo => `${combo.dataset.label} × ${combo.value}`).join(', ') : 'Không chọn combo';
    const seatsTotal = checked.reduce((sum, seat) => sum + Number(seat.dataset.price), 0);
    const combosTotal = selectedCombos.reduce((sum, combo) => sum + quantity(combo) * Number(combo.dataset.price), 0);
    document.getElementById('totalPrice').textContent = money.format(seatsTotal + (step === 2 ? combosTotal : 0)) + '₫';
    document.getElementById('seatStepTotal').textContent = money.format(seatsTotal) + '₫';
    document.getElementById('nextConcessions').disabled = !checked.length;
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
    if (!seats.some(seat => seat.checked) || combos.filter(combo => quantity(combo) > 0).length > 5) {
        event.preventDefault();
        return;
    }
});
update();

function showStep(next) {
    if (next === 2 && !seats.some(seat => seat.checked)) return;
    step = next;
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
