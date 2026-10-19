import { initUserSearch } from '../utils/user-search.js';

const channelList = document.getElementById('channel-list');
const checkboxes = channelList.querySelectorAll('input[type="checkbox"]');
const summaryCount = document.getElementById('checked-channels-count');
const summary = document.getElementById('checked-channels-summary');

initUserSearch({
    input: document.getElementById('channel-search-input'),
    cleaner: document.getElementById('search-cleaner'),
    list: channelList,
    items: channelList.querySelectorAll('.user-item'),
    noMatch: document.getElementById('no-channel-matching'),
});

/**
 * Render the selected channels below the list, so they can be reviewed and unselected without scrolling it
 */
function updateSummary() {
    const checked = Array.from(checkboxes).filter((checkbox) => checkbox.checked);

    summaryCount.textContent = `${checked.length} channel${checked.length > 1 ? 's' : ''}`;
    if (checked.length === 0) {
        summary.textContent = 'No channel selected';

        return;
    }

    summary.replaceChildren(
        ...checked.map((checkbox) => {
            const chip = document.createElement('span');
            chip.className = 'user-summary';

            const name = document.createElement('span');
            name.textContent = checkbox.parentElement.querySelector('span').textContent;

            const uncheck = document.createElement('button');
            uncheck.type = 'button';
            uncheck.className = 'fas fa-times';
            uncheck.title = 'Unselect this channel';
            uncheck.addEventListener('click', () => {
                checkbox.checked = false;
                updateSummary();
            });

            chip.append(name, ' ', uncheck);

            return chip;
        }),
    );
}

checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateSummary));
updateSummary();
