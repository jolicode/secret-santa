import { postJson } from '../utils/http.js';

const countProgress = document.getElementById('count-progress');
const countValues = document.querySelectorAll('.js-count-value');

function updateProgress(count) {
    countProgress.value = count;

    countValues.forEach((countValue) => {
        countValue.innerHTML = count;
    });
}

function sendRemainingMessages() {
    postJson(countProgress.dataset.url)
        .then((response) => {
            updateProgress(response.count);

            if (response.finished) {
                window.location.href = countProgress.dataset.finishUrl;

                return;
            }

            // Use setTimeout to not stack all the call
            setTimeout(sendRemainingMessages, 0);
        });
}

sendRemainingMessages();
