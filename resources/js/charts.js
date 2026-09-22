import {
    Chart,
    LineController,
    BarController,
    DoughnutController,
    LineElement,
    PointElement,
    BarElement,
    ArcElement,
    CategoryScale,
    LinearScale,
    Filler,
    Tooltip,
} from 'chart.js';

Chart.register(
    LineController, BarController, DoughnutController,
    LineElement, PointElement, BarElement, ArcElement,
    CategoryScale, LinearScale, Filler, Tooltip,
);

/*
 * Chart tokens.
 *
 * Series slots come from a validated categorical order (blue -> orange -> aqua);
 * they are assigned by entity, in fixed order, and never cycled or reassigned by
 * rank. Chrome colours are deliberately recessive so the data carries the page.
 */
const TOKEN = {
    series: ['#2a78d6', '#eb6834', '#1baf7a'],
    other: '#c3c2b7',
    grid: '#e1e0d9',
    baseline: '#c3c2b7',
    muted: '#898781',
    ink: '#0b0b0b',
    surface: '#ffffff',
};

Chart.defaults.font.family =
    'Figtree, system-ui, -apple-system, "Segoe UI", sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = TOKEN.muted;
Chart.defaults.animation.duration = 400;

const tooltip = (extra = {}) => ({
    enabled: true,
    backgroundColor: '#0b0b0b',
    titleColor: '#ffffff',
    bodyColor: '#e5e5e3',
    padding: 10,
    cornerRadius: 8,
    displayColors: true,
    boxWidth: 8,
    boxHeight: 8,
    boxPadding: 4,
    ...extra,
});

const nf = new Intl.NumberFormat();

/** Line + area: scans over time. Crosshair tooltip across both series. */
function scansOverTime(canvas, config) {
    const { labels, datasets } = config;

    const ctx = canvas.getContext('2d');

    return new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: datasets.map((set, index) => {
                const color = TOKEN.series[index] ?? TOKEN.other;
                const fill = ctx.createLinearGradient(0, 0, 0, canvas.clientHeight || 260);
                fill.addColorStop(0, hexToRgba(color, index === 0 ? 0.20 : 0.10));
                fill.addColorStop(1, hexToRgba(color, 0));

                return {
                    label: set.label,
                    data: set.data,
                    borderColor: color,
                    backgroundColor: fill,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBorderWidth: 2,
                    // 2px surface ring keeps the hovered point legible where series overlap.
                    pointHoverBorderColor: TOKEN.surface,
                    pointHoverBackgroundColor: color,
                };
            }),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                tooltip: tooltip({
                    callbacks: {
                        label: (item) => ` ${item.dataset.label}: ${nf.format(item.parsed.y)}`,
                    },
                }),
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: TOKEN.baseline },
                    ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: TOKEN.grid, drawTicks: false },
                    border: { display: false, dash: [2, 3] },
                    ticks: { precision: 0, maxTicksLimit: 5, padding: 8 },
                },
            },
        },
    });
}

/** Doughnut: device mix. Capped at 3 slots + "Other" so every pair stays distinct. */
function donut(canvas, config) {
    const { labels, values } = config;

    return new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: labels.map((_, i) => TOKEN.series[i] ?? TOKEN.other),
                // 2px surface gap between segments.
                borderColor: TOKEN.surface,
                borderWidth: 2,
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                tooltip: tooltip({
                    callbacks: {
                        label: (item) => {
                            const total = item.dataset.data.reduce((a, b) => a + b, 0) || 1;
                            const share = ((item.parsed / total) * 100).toFixed(1);
                            return ` ${item.label}: ${nf.format(item.parsed)} (${share}%)`;
                        },
                    },
                }),
            },
        },
    });
}

/** Single-series bar: scans by hour of day. No legend — the title names it. */
function hourlyBars(canvas, config) {
    return new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: config.labels,
            datasets: [{
                label: 'Scans',
                data: config.values,
                backgroundColor: TOKEN.series[0],
                hoverBackgroundColor: TOKEN.series[0],
                borderRadius: 4,
                borderSkipped: 'bottom',
                maxBarThickness: 18,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: tooltip({
                    callbacks: {
                        title: (items) => `${items[0].label} – ${items[0].label.replace(':00', ':59')}`,
                        label: (item) => ` ${nf.format(item.parsed.y)} scans`,
                    },
                }),
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: TOKEN.baseline },
                    ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: TOKEN.grid, drawTicks: false },
                    border: { display: false },
                    ticks: { precision: 0, maxTicksLimit: 4, padding: 8 },
                },
            },
        },
    });
}

function hexToRgba(hex, alpha) {
    const value = hex.replace('#', '');
    const r = parseInt(value.slice(0, 2), 16);
    const g = parseInt(value.slice(2, 4), 16);
    const b = parseInt(value.slice(4, 6), 16);

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

const BUILDERS = { line: scansOverTime, donut, bars: hourlyBars };

/**
 * Blade emits `<canvas data-chart="line" data-chart-config="{...}">` and this
 * wires it up, so no chart configuration ever lives in a template.
 */
export function mountCharts(root = document) {
    root.querySelectorAll('[data-chart]').forEach((canvas) => {
        if (canvas.dataset.chartMounted) {
            return;
        }

        const builder = BUILDERS[canvas.dataset.chart];
        if (!builder) {
            return;
        }

        try {
            builder(canvas, JSON.parse(canvas.dataset.chartConfig || '{}'));
            canvas.dataset.chartMounted = '1';
        } catch (error) {
            console.error('Chart failed to render', error);
        }
    });
}

export { TOKEN };
