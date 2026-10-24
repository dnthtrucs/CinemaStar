@php
    $lifetime = $member->lifetimePoints();
    $tier = $member->membershipTier();
    $progress = $tier['next_threshold'] === null ? 100 : min(100, max(0, ($lifetime - $tier['minimum_points']) / ($tier['next_threshold'] - $tier['minimum_points']) * 100));
@endphp
<section class="card shadow-soft p-4 mb-4" aria-label="Hạng thành viên">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Hạng {{ $tier['label'] }}</h2>
        <span class="badge text-bg-dark">Tích điểm x{{ $tier['multiplier'] }}</span>
    </div>
    <div class="row mb-3">
        <div class="col"><div class="text-muted small">Điểm tích lũy</div><strong>{{ number_format($lifetime) }}</strong></div>
        <div class="col"><div class="text-muted small">Điểm đang có</div><strong>{{ number_format($member->loyalty_points) }}</strong></div>
    </div>
    <div class="progress" role="progressbar" aria-label="Tiến độ hạng thành viên" aria-valuenow="{{ round($progress) }}" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar bg-warning" style="width: {{ $progress }}%"></div>
    </div>
    <p class="small text-muted mt-2 mb-0">{{ $tier['next_threshold'] === null ? 'Bạn đã đạt hạng cao nhất.' : 'Còn '.number_format(max(0, $tier['next_threshold'] - $lifetime)).' điểm tích lũy để lên hạng tiếp theo.' }}</p>
    <p class="small text-muted mt-1 mb-0">Đổi điểm không làm giảm hạng. Điểm từ đơn đã hoàn tiền không tính vào hạng.</p>
</section>
