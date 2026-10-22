<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Bản in (HTML) - CinemaStar</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #aaa; padding: 8px; text-align: left; }
        @media print {
            body { padding: 0; }
            .print-help { display: none; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body onload="window.print()">
    <h1>Báo cáo doanh thu CinemaStar</h1>
    <p class="print-help">Bản in (HTML). Chọn Lưu dưới dạng PDF trong hộp thoại in nếu cần.</p>
    @include('admin.reports.excel')
</body>
</html>
