import Alpine from 'alpinejs';
import morph from '@alpinejs/morph';
import ajax from '@imacrayon/alpine-ajax';
import chart from './components/chart';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
window.Chart = Chart;

Alpine.plugin(morph);
Alpine.plugin(ajax);
Alpine.data('chart', chart);

window.Alpine = Alpine;
Alpine.start();
