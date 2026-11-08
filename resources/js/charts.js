// Chart.js with only what the dashboard and balances charts use, loaded on demand (its own chunk)
import {
    Chart, BarController, BarElement, LineController, LineElement, PointElement,
    CategoryScale, LinearScale, Filler, Tooltip, Legend,
} from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Filler, Tooltip, Legend);

export default Chart;
