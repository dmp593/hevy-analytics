import { Chart } from 'chart.js';

/**
 * Chart.js wrapper for a <canvas> that survives Alpine AJAX partial swaps.
 *
 * Lifecycle is the whole problem this solves. When a filter form swaps a
 * results partial, the canvas may be replaced (x-init runs again on a fresh
 * component) or reused in place (x-init does NOT run, so the chart has to be
 * told to redraw). Getting that wrong leaks a Chart instance per swap onto the
 * same canvas, and once two live instances share a canvas Chart.js corrupts its
 * own internal state and the canvas goes permanently blank.
 *
 * Rules that keep it correct:
 *  - The listener is bound per component and removed on destroy(), so it dies
 *    with the component instead of accumulating on window.
 *  - The Chart instance is held outside Alpine's reactive proxy. A proxied
 *    instance breaks Chart.js's animator cleanup, because the animator compares
 *    object identity and the proxy is not the object it registered.
 *  - Any pre-existing chart on this canvas is destroyed before drawing, so a
 *    reused canvas can never end up with two owners.
 *
 * Usage (via resources/views/components/chart.blade.php):
 *   <canvas x-data="chart" data-chart-config="{...}" x-init="mount()"></canvas>
 */

// Chart instances keyed by canvas element. A WeakMap so a removed canvas and
// its chart are both collectable, and so nothing here is ever made reactive.
const instances = new WeakMap();

function destroyFor(canvas) {
    const existing = instances.get(canvas) ?? Chart.getChart(canvas);
    if (existing) {
        existing.destroy();
        instances.delete(canvas);
    }
}

/**
 * Destroy any chart whose canvas has been detached from the document.
 *
 * Alpine does not reliably call destroy() on a component whose element was
 * swapped out by Alpine AJAX, which strands the chart that was drawn on the
 * replaced canvas. Rather than depend on that lifecycle firing, sweep on every
 * draw: a chart whose canvas is no longer in the document can never be useful
 * again, so this is always safe and needs no bookkeeping.
 */
function sweepDetached() {
    Object.values(Chart.instances).forEach((instance) => {
        if (instance.canvas && !instance.canvas.isConnected) {
            instance.destroy();
        }
    });
}

export default function chart() {
    return {
        onMerged: null,

        mount() {
            this.draw();

            // ajax:merged bubbles from the swapped target, so listening on the
            // document also catches a canvas that was reused in place.
            this.onMerged = (event) => {
                if (event.target === this.$el || event.target.contains?.(this.$el)) {
                    // Let the DOM settle before re-reading the config.
                    requestAnimationFrame(() => this.draw());
                }
            };

            document.addEventListener('ajax:merged', this.onMerged);
        },

        destroy() {
            if (this.onMerged) {
                document.removeEventListener('ajax:merged', this.onMerged);
                this.onMerged = null;
            }
            destroyFor(this.$el);
        },

        draw() {
            sweepDetached();

            const canvas = this.$el;
            if (!canvas.isConnected) {
                return;
            }

            const raw = canvas.getAttribute('data-chart-config');
            if (!raw) {
                return;
            }

            let config;
            try {
                config = JSON.parse(raw);
            } catch (error) {
                console.error('[chart] could not parse data-chart-config', error);
                return;
            }

            destroyFor(canvas);

            const options = config.options ?? {};

            instances.set(
                canvas,
                new Chart(canvas, {
                    type: config.type || 'line',
                    data: config.data || { labels: [], datasets: [] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        ...options,
                        // Merged after the spread so a caller-supplied `plugins`
                        // key cannot silently drop the legend setting.
                        plugins: {
                            legend: { display: config.legend !== false },
                            ...(options.plugins ?? {}),
                        },
                    },
                }),
            );
        },
    };
}
