import Chart from 'chart.js/auto';
import { Html5Qrcode } from 'html5-qrcode';

window.Chart = Chart;
window.Html5Qrcode = Html5Qrcode;

// Catatan: Alpine.js sudah dibundel dan dijalankan oleh Livewire 4.
// Mengimpor & menjalankan Alpine kedua kali membuat directive wire:model
// tidak terpasang, sehingga pencarian/pagination tidak mengirim request.
