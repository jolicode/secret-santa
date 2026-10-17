const revealButton = document.getElementById('reveal');
const retryButton = document.getElementById('retry-button');

if (revealButton) {
    revealButton.addEventListener('click', () => {
        document.querySelectorAll('[data-receiver]').forEach((receiver) => {
            receiver.innerHTML = receiver.getAttribute('data-receiver');
        });
    });
}

if (retryButton) {
    let retried = false;

    // Prevent the multi-click of the button
    retryButton.addEventListener('click', (e) => {
        if (retried) {
            e.preventDefault();

            return;
        }

        retried = true;
        retryButton.children[0].className = 'fas fa-hourglass-start';
    });
}
