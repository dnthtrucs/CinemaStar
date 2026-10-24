import Chart from 'chart.js/auto';

const source = document.getElementById('admin-dashboard-data');
if (source) {
    const reports = JSON.parse(source.textContent);
    const money = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 });
    const number = new Intl.NumberFormat('vi-VN');
    const colors = ['#e52d47', '#235789', '#e9a23b', '#2c8f75'];

    const draw = (id, config) => {
        const canvas = document.getElementById(id);
        if (canvas) new Chart(canvas, config);
    };
    const cashOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (item) => money.format(item.parsed.y ?? item.parsed) } } },
        scales: { y: { beginAtZero: true, ticks: { callback: (value) => money.format(value) } } },
    };

    draw('daily-revenue-chart', {
        type: 'line',
        data: { labels: reports.daily.map((row) => row.date.slice(8, 10) + '/' + row.date.slice(5, 7)), datasets: [{ label: 'Doanh thu', data: reports.daily.map((row) => row.revenue), borderColor: colors[0], backgroundColor: '#e52d4718', fill: true, tension: 0.2 }] },
        options: cashOptions,
    });
    draw('top-movies-chart', {
        type: 'bar',
        data: { labels: reports.top_movies.map((row) => row.title), datasets: [{ label: 'Doanh thu vé', data: reports.top_movies.map((row) => row.revenue), backgroundColor: colors[1] }] },
        options: cashOptions,
    });
    draw('occupancy-chart', {
        type: 'bar',
        data: { labels: reports.occupancy.map((row) => row.title), datasets: [{ label: 'Tỷ lệ lấp đầy', data: reports.occupancy.map((row) => row.percentage), backgroundColor: colors[2] }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (item) => {
                const row = reports.occupancy[item.dataIndex];
                return `${number.format(row.sold)} / ${number.format(row.capacity)} ghế (${number.format(row.percentage)}%)`;
            } } } },
            scales: { y: { beginAtZero: true, suggestedMax: 100, ticks: { callback: (value) => `${number.format(value)}%` } } },
        },
    });
    draw('revenue-split-chart', {
        type: 'doughnut',
        data: { labels: ['Vé', 'Combo bắp nước'], datasets: [{ data: [reports.revenue_split.tickets, reports.revenue_split.concessions], backgroundColor: [colors[1], colors[3]] }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (item) => `${item.label}: ${money.format(item.parsed)}` } } } },
    });
}
