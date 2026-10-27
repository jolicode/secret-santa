import { postJson } from '../utils/http.js';

const root = document.querySelector('[data-load-users-url]');
const countValues = document.querySelectorAll('.js-count-value');
let refreshCount = null;

function updateProgress(count, animate) {
    clearInterval(refreshCount);

    if (!animate) {
        countValues.forEach((countValue) => {
            countValue.innerHTML = count;
        });
    }

    // Increase the count with animation
    const currentCount = parseInt(countValues[0].innerHTML, 10);
    const step = Math.max(1, Math.floor((count - currentCount) / 10));
    let displayedCount = currentCount;

    refreshCount = setInterval(() => {
        displayedCount += step;
        if (displayedCount >= count) {
            displayedCount = count;
            clearInterval(refreshCount);
        }

        countValues.forEach((countValue) => {
            countValue.innerHTML = displayedCount;
        });
    }, 50);
}

function loadRemainingUsers() {
    postJson(root.dataset.loadUsersUrl)
        .then((response) => {
            if (response.error) {
                window.location.href = root.dataset.loadUsersErrorUrl;

                return;
            }

            if (response.finished) {
                updateProgress(response.count, false);
                window.location.href = root.dataset.loadUsersNextUrl;

                return;
            }

            updateProgress(response.count, true);

            // Use setTimeout with 0ms to defer execution to the next event loop cycle
            setTimeout(loadRemainingUsers, 0);
        })
        .catch(() => {
            window.location.href = root.dataset.loadUsersErrorUrl;
        });
}

if (root) {
    loadRemainingUsers();
}
