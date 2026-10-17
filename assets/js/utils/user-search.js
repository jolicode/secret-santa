/**
 * Normalize a string for search purposes: no diacritics, lowercase, trimmed.
 */
export function normalize(string) {
    return string.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
}

/**
 * Filter a list of users while typing. Items are matched on their
 * data-search-index attribute: the ones matching none of the search terms get
 * the "not-matching" class, the list gets "hide-not-matching" as soon as there
 * is a query. Items already hidden (item.hidden) are ignored when checking
 * whether something matched.
 *
 * Returns the search function, to re-run it after changing the items.
 */
export function initUserSearch({ input, cleaner, list, items, noMatch }) {
    const indexes = Array.from(items, (item) => normalize(item.getAttribute('data-search-index')));

    function search() {
        const terms = normalize(input.value).split(' ').filter(Boolean);
        list.classList.toggle('hide-not-matching', terms.length > 0);
        cleaner.classList.toggle('is-hidden', terms.length === 0);

        let matchingCount = 0;
        items.forEach((item, i) => {
            const matching = terms.some((term) => indexes[i].includes(term));
            item.classList.toggle('not-matching', !matching);
            if (!item.hidden && (matching || terms.length === 0)) {
                matchingCount++;
            }
        });

        noMatch.classList.toggle('is-hidden', matchingCount > 0);
    }

    input.addEventListener('input', search);
    cleaner.addEventListener('click', () => {
        input.value = '';
        search();
        input.focus();
    });
    search();

    return search;
}
