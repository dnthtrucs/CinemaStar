@extends('layouts.app')
@section('title', 'Hoàn tiền thủ công')
@section('content')
<div class="container py-4">
    @include('admin.partials.nav')
    <h1 class="h3">Hoàn tiền thủ công</h1>
    <p class="text-muted">Chỉ đánh dấu sau khi đã chuyển tiền lại cho khách. Ứng dụng ghi nhận đối soát, không tự chuyển tiền.</p>
    <form method="GET" class="d-flex gap-2 my-3">
        <label for="refund-status" class="visually-hidden">Trạng thái xử lý</label>
        <select id="refund-status" name="status" class="form-select w-auto">
            @foreach(['pending' => 'Chưa xử lý', 'resolved' => 'Đã xử lý', 'all' => 'Tất cả'] as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary">Lọc</button>
    </form>
    <div class="card p-3 table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Giao dịch / Mã ngân hàng</th><th>Đơn / Khách</th><th>Số tiền</th><th>Lý do</th><th>Xử lý</th></tr></thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td>{{ $payment->request_id }}<div class="small text-muted">{{ $payment->transaction_id }}</div></td>
                    <td><a href="{{ route('admin.bookings.show', $payment->booking) }}">{{ $payment->booking->code }}</a><div>{{ $payment->booking->user->name }}</div></td>
                    <td class="text-nowrap">{{ number_format($payment->amount, 0, ',', '.') }}₫</td>
                    <td>{{ \App\Models\Payment::MANUAL_REFUND_REASONS[$payment->response_code] }}</td>
                    <td>
                        @if($payment->refund_resolved_at)
                            <span class="badge text-bg-success">Đã xử lý</span>
                            <div class="small">{{ $payment->refund_resolved_at->format('H:i d/m/Y') }} · {{ $payment->refundResolver?->name ?? 'Tài khoản đã xóa' }}</div>
                            <div>{{ $payment->refund_resolution_note }}</div>
                        @else
                            @include('admin.manual-refunds.resolve-form')
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">Không có khoản hoàn tiền phù hợp.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $payments->links() }}
    </div>
</div>
@endsection
