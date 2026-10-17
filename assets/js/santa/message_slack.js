// The flatpickr stylesheet is linked from the template: importing a CSS file from JS makes
// AssetMapper map it to a "data:" module, which our CSP (script-src) forbids.
import flatpickr from 'flatpickr';

const displayedDate = document.querySelector('[data-scheduling-display]');
const timestampInput = document.querySelector('[data-scheduling-timestamp]');
const dateClearer = document.getElementById('scheduling-clear');

const now = new Date();
const minDate = new Date(now.getTime() + 10 * 60000);
const defaultDate = new Date(now.getTime() + 30 * 60000);
const maxDate = new Date(now.getTime() + 120 * 24 * 60 * 60000);

const picker = flatpickr(displayedDate, {
    altInput: true,
    altFormat: 'F j, Y - H:i',
    enableTime: true,
    time_24hr: true,
    minDate: minDate,
    maxDate: maxDate,
    defaultHour: defaultDate.getHours(),
    defaultMinute: defaultDate.getMinutes(),
    minuteIncrement: 1,
});

displayedDate.addEventListener('change', () => {
    timestampInput.value = Math.ceil(new Date(displayedDate.value).getTime() / 1000);
});

dateClearer.addEventListener('click', () => {
    picker.clear();
    timestampInput.value = '';
});
