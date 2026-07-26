/**
 * Chart.js wrapper that survives Alpine AJAX morph swaps and initial renders.
 *
 * Purpose:
 *  - Render once on `x-init` using the config baked into the canvas attribute.
 *  - After an Alpine AJAX morph (where the canvas is kept but its attributes
 *    change), re-read the config from the element and re-draw the chart.
 *
 * The Chart.js instance is stored as a plain JS property (not Alpine‑reactive)
 * so `x-effect` and attribute‑watching never loop.
 *
 * Usage (via resources/views/components/chart.blade.php):
 *   <canvas x-data="chart" data-chart-config="@js($config)"
 *           x-init="load()"></canvas>
 */
export default function chart() {
    return {
        instance: null,

        mount() {
            this.draw();
            window.addEventListener('ajax:merged', () => setTimeout(() => this.draw(), 0));
        },

        draw() {
            if (typeof Chart === 'undefined') return;

            const raw = this.$el.getAttribute('data-chart-config');
            if (!raw) return;
            let config;
            try { config = JSON.parse(raw); } catch { return; }

            this.destroyInstance();
            // eslint-disable-next-line no-undef
            this.instance = new Chart(this.$el, {
                type: config.type || 'line',
                data: config.data || { labels: [], datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: config.legend !== false } },
                    ...(config.options || {}),
                },
            });
        },

        destroyInstance() {
            if (this.instance) {
                this.instance.destroy();
                this.instance = null;
            }
        },
    };
}
