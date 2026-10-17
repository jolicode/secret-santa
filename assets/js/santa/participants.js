import { guardFormSubmit } from '../utils/form-submit-guard.js';
import { initUserSearch } from '../utils/user-search.js';

// Only rendered when the team has restricted users
const restrictUserCheckbox = document.getElementById('restrict-user-checkbox');
const allChecker = document.getElementById('all-checker');
const allUnchecker = document.getElementById('all-unchecker');
const groupList = document.getElementById('group-list');
const userList = document.getElementById('user-list');
const summaryCount = document.getElementById('summary-count');
const checkedUsersSummary = document.getElementById('checked-users-summary');
const submitButton = document.getElementById('submit-button');

guardFormSubmit(document.getElementById('run_form'), submitButton);

/**
 * Render the summary of already checked users
 */
function updateSummary() {
    const users = userList.querySelectorAll('.user-item input:checked');

    let html = '';

    if (users.length > 0) {
        users.forEach((user) => {
            html += user.parentElement.dataset.summary;
        });
    } else {
        html = 'No user selected';
    }

    summaryCount.innerHTML = users.length + (users.length <= 1 ? ' person' : ' people');
    checkedUsersSummary.innerHTML = html;
}

/**
 * Handle uncheck user from summary
 */
function uncheckUser(e) {
    if (!e.target.dataset.uncheckUser) {
        return;
    }

    const input = document.getElementById(e.target.dataset.uncheckUser);

    if (!input) {
        return;
    }

    input.checked = false;
    updateSummary();
}

/**
 * Enable restricted user display behavior
 */
function onRestrictUserChange() {
    userList.classList.toggle('hide-restricted', !restrictUserCheckbox.checked);
}

/**
 * Return all the users currently visible in the list
 */
function getVisibleUsersCheckboxes() {
    let selector = '.user-item';

    if (userList.classList.contains('hide-not-matching')) {
        selector += ':not(.not-matching)';
    }
    if (userList.classList.contains('hide-restricted')) {
        selector += ':not(.restricted)';
    }

    return userList.querySelectorAll(selector + ' input[type="checkbox"]');
}

function checkAllVisibleUsers() {
    getVisibleUsersCheckboxes().forEach((checkbox) => {
        checkbox.checked = true;
    });

    updateSummary();
}

function uncheckAllVisibleUsers() {
    getVisibleUsersCheckboxes().forEach((checkbox) => {
        checkbox.checked = false;
    });

    updateSummary();
}

/**
 * Handle the click on a group to check all users in it
 */
function checkAllUserInAGroup(e) {
    e.target.getAttribute('data-user-ids').split('|').forEach((userId) => {
        const userInput = document.querySelector('[value="' + userId + '"]');

        if (userInput) {
            userInput.checked = true;
        }
    });

    updateSummary();
}

initUserSearch({
    input: document.getElementById('user-search-input'),
    cleaner: document.getElementById('search-cleaner'),
    list: userList,
    items: userList.querySelectorAll('.user-item'),
    noMatch: document.getElementById('no-user-matching'),
});

allChecker.addEventListener('click', checkAllVisibleUsers);
allUnchecker.addEventListener('click', uncheckAllVisibleUsers);
userList.addEventListener('change', updateSummary);
checkedUsersSummary.addEventListener('click', uncheckUser);

if (groupList) {
    groupList.querySelectorAll('.group-button').forEach((groupButton) => {
        groupButton.addEventListener('click', checkAllUserInAGroup);
    });
}

updateSummary();

if (restrictUserCheckbox) {
    restrictUserCheckbox.addEventListener('change', onRestrictUserChange);
    onRestrictUserChange();
}
