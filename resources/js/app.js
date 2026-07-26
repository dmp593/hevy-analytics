import Alpine from 'alpinejs';
import morph from '@alpinejs/morph';
import ajax from '@imacrayon/alpine-ajax';
import chart from './components/chart';

Alpine.plugin(morph);
Alpine.plugin(ajax);
Alpine.data('chart', chart);

window.Alpine = Alpine;
Alpine.start();
