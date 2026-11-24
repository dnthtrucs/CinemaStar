// Prefer contiguous blocks nearest the centre; never bridge unavailable positions.
export function suggestAdjacentSeats(seats, count) {
    if (!Number.isInteger(count) || count < 1 || count > 10) return [];
    const rows = [...new Set(seats.map(seat => seat.row))].sort();
    const candidates = [];
    rows.forEach((row, rowIndex) => {
        const positions = seats.filter(seat => seat.row === row).sort((a, b) => a.number - b.number);
        const min = Math.min(...positions.map(s => s.number));
        const max = Math.max(...positions.map(s => s.number + s.capacity - 1));
        for (let start = 0; start < positions.length; start++) {
            const group = []; let capacity = 0; let next = positions[start].number;
            for (const seat of positions.slice(start)) {
                if (seat.unavailable || seat.number !== next || capacity + seat.capacity > count) break;
                group.push(seat); capacity += seat.capacity; next += seat.capacity;
                if (capacity === count) {
                    const centre = (group[0].number + next - 1) / 2;
                    candidates.push({ ids: group.map(s => s.id), score: Math.abs(rowIndex - (rows.length - 1) / 2) * 2 + Math.abs(centre - (min + max) / 2) });
                    break;
                }
            }
        }
    });
    return candidates.sort((a, b) => a.score - b.score)[0]?.ids ?? [];
}
