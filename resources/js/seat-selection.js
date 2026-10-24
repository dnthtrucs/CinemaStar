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

function update() {
    const checked = seats.filter(seat => seat.checked);
    const selectedCombos = combos.filter(combo => quantity(combo) > 0);
    combos.forEach(combo => { combo.value = quantity(combo); });
    document.getElementById('selectedSeats').textContent = checked.length ? checked.map(seat => seat.dataset.label).join(', ') : 'Chưa chọn ghế';
    document.getElementById('selectedCombos').textContent = selectedCombos.length ? selectedCombos.map(combo => `${combo.dataset.label} × ${combo.value}`).join(', ') : 'Không chọn combo';
    const seatsTotal = checked.reduce((sum, seat) => sum + Number(seat.dataset.price), 0);
    const combosTotal = selectedCombos.reduce((sum, combo) => sum + quantity(combo) * Number(combo.dataset.price), 0);
    document.getElementById('totalPrice').textContent = money.format(seatsTotal + combosTotal) + '₫';
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
    if (!seats.some(seat => seat.checked) || combos.filter(combo => quantity(combo) > 0).length > 5) {
        event.preventDefault();
        return;
    }
    combos.forEach(combo => { combo.disabled = quantity(combo) === 0; });
});
update();
