/**
 * Prevent the multi-submit of a form: block any further submit and show an
 * hourglass on the submit button.
 */
export function guardFormSubmit(form, submitButton) {
    let submitted = false;

    form.addEventListener('submit', (e) => {
        if (submitted) {
            e.preventDefault();

            return;
        }

        submitted = true;
        submitButton.disabled = true;
        submitButton.children[0].className = 'fas fa-hourglass-start';
    });
}
