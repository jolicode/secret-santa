import { guardFormSubmit } from '../utils/form-submit-guard.js';
import { initUserSearch } from '../utils/user-search.js';

// The form is not rendered when exclusions are not allowed
const runForm = document.getElementById('run_form');

if (runForm) {
    const submitButton = document.getElementById('submit-button');
    const addExclusionsButtons = document.querySelectorAll('.js-add-exclusion');

    const userDialog = document.querySelector('.user-dialog');
    const dialogCloseButtons = userDialog.querySelectorAll('.dialog-close-button');
    const selectableUsers = userDialog.querySelectorAll('.js-select-user');
    const selectableUserList = document.getElementById('exclusion-user-list');
    const searchInput = document.getElementById('exclusion-search-input');
    const confirmButton = userDialog.querySelector('.js-confirm-exclusions');
    const removeExclusionButtons = document.querySelectorAll('.js-remove-exclusion');

    let currentExclusionContainer = null;

    guardFormSubmit(runForm, submitButton);

    const search = initUserSearch({
        input: searchInput,
        cleaner: document.getElementById('exclusion-search-cleaner'),
        list: selectableUserList,
        items: selectableUsers,
        noMatch: selectableUserList.querySelector('.no-result'),
    });

    function getExclusionsForUser(exclusionContainer) {
        return Array.from(exclusionContainer.querySelectorAll('input[type="hidden"]'), (input) => input.value);
    }

    function refreshAddButtonVisibility(exclusionContainer) {
        const excludedUserIds = getExclusionsForUser(exclusionContainer);
        const addButton = exclusionContainer.parentElement.querySelector('.js-add-exclusion');

        if (!addButton) {
            return;
        }

        addButton.hidden = excludedUserIds.length >= selectableUsers.length - 1;
    }

    function openUserSelection(exclusionContainer) {
        // Store the current exclusion container
        currentExclusionContainer = exclusionContainer;

        // Hide already excluded users and self
        const excludedUserIds = getExclusionsForUser(exclusionContainer);
        selectableUsers.forEach((button) => {
            const userId = button.getAttribute('data-user-id');
            button.hidden = userId === exclusionContainer.getAttribute('data-user-id') || excludedUserIds.includes(userId);
            button.classList.remove('selected');
        });

        searchInput.value = '';
        search();
        refreshConfirmButton();
        userDialog.showModal();
        searchInput.focus();
    }

    function refreshConfirmButton() {
        confirmButton.disabled = userDialog.querySelectorAll('.js-select-user.selected').length === 0;
    }

    function closeUserSelection() {
        userDialog.close();
        currentExclusionContainer = null;
    }

    function removeExclusion(button) {
        const item = button.closest('.exclusion-item');
        if (item) {
            const container = item.closest('.exclusion-container');
            container.removeChild(item);

            refreshAddButtonVisibility(container);
        }
    }

    function selectUser(button) {
        button.classList.toggle('selected');
        refreshConfirmButton();
    }

    function confirmExclusions() {
        if (!currentExclusionContainer) {
            return;
        }

        userDialog.querySelectorAll('.js-select-user.selected').forEach(addExclusion);

        refreshAddButtonVisibility(currentExclusionContainer);

        closeUserSelection();
    }

    function addExclusion(button) {
        const excludedUserId = button.getAttribute('data-user-id');
        const santaUserId = currentExclusionContainer.getAttribute('data-user-id');

        if (!excludedUserId || !santaUserId) {
            return;
        }

        const removeExclusionButton = document.createElement('button');
        removeExclusionButton.type = 'button';
        removeExclusionButton.className = 'fas fa-times js-remove-exclusion';
        removeExclusionButton.addEventListener('click', () => removeExclusion(removeExclusionButton));

        const exclusionInput = document.createElement('input');
        exclusionInput.type = 'hidden';
        exclusionInput.name = currentExclusionContainer.getAttribute('data-form-name') + '[]';
        exclusionInput.value = excludedUserId;

        const item = document.createElement('span');
        item.className = 'exclusion-item';
        item.innerHTML = button.getAttribute('data-prototype');
        item.appendChild(removeExclusionButton);
        item.appendChild(exclusionInput);

        currentExclusionContainer.appendChild(item);
    }

    dialogCloseButtons.forEach((button) => {
        button.addEventListener('click', closeUserSelection);
    });
    addExclusionsButtons.forEach((button) => {
        button.addEventListener('click', () => openUserSelection(button.previousSibling));
        refreshAddButtonVisibility(button.previousSibling);
    });
    removeExclusionButtons.forEach((button) => {
        button.addEventListener('click', () => removeExclusion(button));
    });
    selectableUsers.forEach((button) => {
        button.addEventListener('click', () => selectUser(button));
    });
    confirmButton.addEventListener('click', confirmExclusions);
}
