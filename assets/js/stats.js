// Open the scrollable charts on their most recent columns, once fonts are loaded and widths are final
window.addEventListener('load', () => {
    for (const chart of document.querySelectorAll('.chart')) {
        chart.scrollLeft = chart.scrollWidth;
    }
});
