import { guardFormSubmit } from '../utils/form-submit-guard.js';
import { postJson } from '../utils/http.js';

const runForm = document.getElementById('run_form');
const submitButton = document.getElementById('submit-button');
const sampleMessageButton = document.getElementById('sample-message-button');
const fieldLengths = document.querySelectorAll('[data-length-field]');

guardFormSubmit(runForm, submitButton);

/**
 * Handle sample message send
 */
if (sampleMessageButton) {
    sampleMessageButton.addEventListener('click', () => {
        postJson(sampleMessageButton.dataset.url, new FormData(runForm))
            .then((response) => {
                if (response.success) {
                    window.alert('We have sent you a fake message. Check it now before finishing the Secret Santa.');

                    return;
                }

                let message = 'Error when sending the fake message:\n';
                Object.values(response.errors).forEach((error) => {
                    message += error + '\n';
                });

                window.alert(message);
            });
    });
}

function updateFieldLength(field, fieldLength) {
    fieldLength.innerHTML = field.value.trim().replace('\r\n', '\n').length;
}

fieldLengths.forEach((fieldLength) => {
    const field = document.querySelector('[data-field="' + fieldLength.getAttribute('data-length-field') + '"]');
    field.addEventListener('keyup', () => updateFieldLength(field, fieldLength));
    updateFieldLength(field, fieldLength);
});
