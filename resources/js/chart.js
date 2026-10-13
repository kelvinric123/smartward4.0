// Chart.js for the pages that draw charts, bundled so they work on a hospital
// network with no internet. They use the global Chart, as the CDN script gave
// them. List it before resources/js/app.js in @vite, so it is there when
// Alpine starts the components that draw.
import Chart from 'chart.js/auto';

window.Chart = Chart;
