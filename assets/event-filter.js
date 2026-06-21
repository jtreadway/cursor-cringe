class EventFilter {
    static FIND_PLACEHOLDER = 'find text (2+ characters)';

    constructor(root) {
        EventFilter.activeInstance = this;
        this.root = root;
        this.venueScopeContainer = root.querySelector('.event-filter__venue-scope');
        this.tagsContainer = root.querySelector('.event-filter__type-tags');
        this.typesSection = root.querySelector('.event-filter__types');
        this.allVenuesButton = root.querySelector('[data-filter-all-venues]');
        this.myVenuesButton = root.querySelector('[data-filter-my-venues]');
        this.allButton = root.querySelector('[data-filter-all]');
        this.findInput = root.querySelector('[data-filter-find]');
        this.panel = root.querySelector('[data-filter-panel]');
        this.toggleButton = root.querySelector('[data-filter-toggle]');
        this.filterCard = root.querySelector('[data-filter-card]');
        this.summaryEl = root.querySelector('[data-filter-summary]');
        this.summaryVenuePill = root.querySelector('[data-summary-pill="venue"]');
        this.summaryTypesPill = root.querySelector('[data-summary-pill="types"]');
        this.hideVenueScope = root.dataset.hideVenueScope === 'true';
        this.filterAlwaysOpen = root.dataset.filterAlwaysOpen === 'true';
        this.totalEventCount = parseInt(root.dataset.totalEventCount ?? '', 10) || 0;

        const urlParams = new URLSearchParams(window.location.search);
        const saved = CalendarPrefs.loadSaved();

        this.favorites = CalendarPrefs.loadFavoritesSet();

        this.activeTags = new Set(
            urlParams.has('tags')
                ? EventFilter.parseTagList(urlParams.get('tags') || '')
                : EventFilter.parseTagList(root.dataset.activeTags || '')
        );

        this.findQuery = urlParams.has('find')
            ? (urlParams.get('find') || '').trim()
            : (this.findInput?.value || '').trim();

        if (this.hideVenueScope) {
            this.favoritesOnly = false;
        } else if (urlParams.has('scope')) {
            this.favoritesOnly = EventFilter.favoritesOnlyFromParams(urlParams);
        } else {
            this.favoritesOnly = EventFilter.resolveFavoritesOnly(urlParams, saved, this.favorites);
        }

        if (this.findInput) {
            this.findInput.value = this.findQuery;
        }

        this.totalVenueCount = 0;
        this.favoriteVenueCount = 0;

        this.syncVenueScopeButtons();
        this.bindControls();
        if (this.tagsContainer || this.venueScopeContainer) {
            this.syncAvailableTags();
        }
        if (this.filterAlwaysOpen) {
            this.ensurePanelOpen();
        }
        this.apply();
        this.updateUrl();
        this.syncFilterState();
    }

    static favoritesOnlyFromParams(params) {
        return params.get('scope') === 'favorites';
    }

    static resolveFavoritesOnly(params, saved, favorites) {
        if (params.has('scope')) {
            return EventFilter.favoritesOnlyFromParams(params);
        }

        if (params.get('prefs') === 'neutral') {
            return false;
        }

        if (saved.scope === 'favorites') {
            return true;
        }

        return favorites.size > 0;
    }

    static parseTagList(raw) {
        return raw
            .split(',')
            .map((tag) => tag.trim().toLowerCase())
            .filter(Boolean);
    }

    venueBlocksInScope(scope) {
        if (!(scope instanceof Element)) {
            return [];
        }

        if (scope.classList.contains('venue-block')) {
            return [scope];
        }

        return Array.from(scope.querySelectorAll('.venue-block'));
    }

    static wordsFromText(text) {
        const matches = text.toLowerCase().match(/[a-z0-9]+/g);

        return matches ?? [];
    }

    static matchesWordStarts(haystack, query) {
        const queryTokens = query.trim().toLowerCase().split(/\s+/).filter(Boolean);
        if (queryTokens.length === 0) {
            return false;
        }

        const words = EventFilter.wordsFromText(haystack);

        return queryTokens.every((token) => words.some((word) => word.startsWith(token)));
    }

    bindControls() {
        if (this.venueScopeContainer) {
            this.venueScopeContainer.addEventListener('click', (event) => {
                if (event.target.closest('[data-manage-venues]')) {
                    this.manageVenues();
                    return;
                }

                if (event.target.closest('[data-filter-all-venues]')) {
                    if (!this.favoritesOnly) {
                        return;
                    }

                    this.favoritesOnly = false;
                    this.syncVenueScopeButtons();
                    this.apply();
                    this.persistState();
                    return;
                }

                if (event.target.closest('[data-filter-my-venues]')) {
                    if (this.favoritesOnly) {
                        return;
                    }

                    this.favoritesOnly = true;
                    this.syncVenueScopeButtons();
                    this.apply();
                    this.persistState();
                }
            });
        }

        if (this.tagsContainer) {
            this.tagsContainer.addEventListener('click', (event) => {
                const allButton = event.target.closest('[data-filter-all]');
                if (allButton) {
                    this.activeTags.clear();
                    this.syncTypeTagButtons();
                    this.apply();
                    this.persistState();
                    return;
                }

                const button = event.target.closest('[data-filter-tag]');
                if (!button) {
                    return;
                }

                const tag = (button.dataset.filterTag || '').toLowerCase();
                if (!tag) {
                    return;
                }

                if (this.activeTags.has(tag)) {
                    this.activeTags.delete(tag);
                } else {
                    this.activeTags.add(tag);
                }

                this.syncTypeTagButtons();
                this.apply();
                this.persistState();
            });
        }

        if (this.findInput) {
            this.findInput.addEventListener('input', () => {
                this.findQuery = this.findInput.value.trim();
                this.apply();
                this.persistState();
            });
            this.findInput.addEventListener('focus', () => {
                if (!this.root.classList.contains('is-open')) {
                    this.togglePanel(true);
                }
            });
            this.findInput.addEventListener('click', (event) => {
                event.stopPropagation();
            });
        }

        if (this.filterCard && !this.filterAlwaysOpen) {
            this.filterCard.addEventListener('click', () => {
                if (!this.root.classList.contains('is-open')) {
                    this.togglePanel(true);
                }
            });
        }

        if (this.toggleButton && !this.filterAlwaysOpen) {
            this.toggleButton.addEventListener('click', (event) => {
                event.stopPropagation();
                this.togglePanel();
            });
        }

        this.root.addEventListener('keydown', (event) => {
            if (this.filterAlwaysOpen || event.key !== 'Escape' || !this.root.classList.contains('is-open')) {
                return;
            }

            event.preventDefault();
            this.togglePanel(false);
            this.findInput?.focus();
        });

        document.addEventListener('calendar:favoriteschange', () => {
            this.favorites = CalendarPrefs.loadFavoritesSet();
            if (!this.hideVenueScope && this.favorites.size === 0 && this.favoritesOnly) {
                this.favoritesOnly = false;
                this.syncVenueScopeButtons();
            }
            this.syncAvailableTags();
            this.apply();
        });

        document.addEventListener('calendar:daychange', (event) => {
            if (document.body.classList.contains('view-week')) {
                return;
            }

            VenueFavorites.getInstance()?.syncButtons();
            this.syncAvailableTags(event.detail?.panel ?? null);
        });

        window.addEventListener('popstate', () => this.handlePopstate());
    }

    handlePopstate() {
        this.syncFromUrl();
        this.syncAvailableTags();
        this.apply();
        if (this.filterAlwaysOpen) {
            this.ensurePanelOpen();
            return;
        }

        const params = new URLSearchParams(window.location.search);
        this.togglePanel(params.get('filter') === 'open');
    }

    ensurePanelOpen() {
        if (!this.panel) {
            return;
        }

        this.root.classList.add('is-open');
        this.panel.hidden = false;
        this.syncFilterState();
        this.syncFilterSummary();
    }

    syncFromUrl() {
        const params = new URLSearchParams(window.location.search);

        this.activeTags = new Set(
            params.has('tags')
                ? EventFilter.parseTagList(params.get('tags') || '')
                : []
        );

        this.findQuery = params.has('find') ? (params.get('find') || '').trim() : '';
        if (this.findInput) {
            this.findInput.value = this.findQuery;
        }

        if (this.hideVenueScope) {
            this.favoritesOnly = false;
        } else if (params.has('scope')) {
            this.favoritesOnly = EventFilter.favoritesOnlyFromParams(params);
        } else {
            this.favoritesOnly = EventFilter.resolveFavoritesOnly(
                params,
                CalendarPrefs.loadSaved(),
                this.favorites
            );
        }

        this.syncVenueScopeButtons();
    }

    buildTypesSummaryLabel(eventCount) {
        const tags = Array.from(this.activeTags).sort();

        if (tags.length === 0) {
            return `all events ${eventCount}`;
        }

        const shown = tags.slice(0, 3);
        let label = shown.join(', ');
        const extra = tags.length - shown.length;

        if (extra > 0) {
            label += ` +${extra}`;
        }

        return label;
    }

    syncFilterSummary() {
        if (!this.summaryEl) {
            return;
        }

        const eventCount = this.totalEventCount;

        if (this.summaryVenuePill && !this.hideVenueScope) {
            const venueCount = this.favoritesOnly ? (this.favoriteVenueCount ?? 0) : (this.totalVenueCount ?? 0);
            const venueClass = this.favoritesOnly ? 'event-filter__tag--my-venues' : 'event-filter__tag--all-venues';
            this.summaryVenuePill.className = `event-filter__summary-pill event-filter__summary-pill--venue event-filter__tag ${venueClass} is-active`;
            this.summaryVenuePill.textContent = `${this.favoritesOnly ? 'my venues' : 'all venues'} ${venueCount}`;
        }

        if (this.summaryTypesPill) {
            const tags = Array.from(this.activeTags).sort();
            const allEventsActive = tags.length === 0;

            if (this.hideVenueScope && this.summaryEl.querySelector('[data-summary-pill^="type-"]')) {
                this.summaryTypesPill.className = `event-filter__summary-pill event-filter__summary-pill--types event-filter__tag event-filter__tag--all${allEventsActive ? ' is-active' : ''}`;
                this.summaryTypesPill.textContent = `all events ${eventCount}`;

                this.summaryEl.querySelectorAll('[data-summary-pill^="type-"]').forEach((pill) => {
                    const tag = (pill.dataset.summaryPill || '').replace(/^type-/, '').toLowerCase();
                    pill.classList.toggle('is-active', tag !== '' && this.activeTags.has(tag));
                });
            } else {
                this.summaryTypesPill.className = `event-filter__summary-pill event-filter__summary-pill--types event-filter__tag${allEventsActive ? ' event-filter__tag--all' : ''} is-active`;
                this.summaryTypesPill.textContent = this.buildTypesSummaryLabel(eventCount);
            }
        }

        if (this.filterCard) {
            const open = this.root.classList.contains('is-open');
            this.filterCard.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                this.filterCard.removeAttribute('aria-label');
            } else {
                this.filterCard.setAttribute(
                    'aria-label',
                    this.hideVenueScope ? 'Edit event filters' : 'Edit venue and event filters'
                );
            }
        }

        if (this.toggleButton) {
            const open = this.root.classList.contains('is-open');
            this.toggleButton.setAttribute('aria-expanded', open ? 'true' : 'false');
            this.toggleButton.setAttribute('aria-label', open ? 'Close filters' : 'Show filters');
            this.toggleButton.setAttribute('title', open ? 'Close filters' : 'Filters');
        }
    }

    togglePanel(forceOpen = null) {
        if (this.filterAlwaysOpen) {
            this.ensurePanelOpen();
            return;
        }

        if (!this.panel) {
            return;
        }

        const open = forceOpen === null ? !this.root.classList.contains('is-open') : forceOpen;
        this.root.classList.toggle('is-open', open);
        this.panel.hidden = !open;
        if (this.toggleButton) {
            this.toggleButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        this.syncFilterState();
        this.syncFilterSummary();
        this.syncFilterOpenInUrl(open);
    }

    syncFilterOpenInUrl(open) {
        if (this.filterAlwaysOpen) {
            open = true;
        }

        const url = new URL(window.location.href);

        if (open) {
            url.searchParams.set('filter', 'open');
        } else {
            url.searchParams.delete('filter');
        }

        history.replaceState(null, '', url.toString());
        document.dispatchEvent(new CustomEvent('calendar:urlchange'));
    }

    manageVenues() {
        const params = new URLSearchParams(window.location.search);
        const out = new URLSearchParams();
        const date = CalendarPrefs.currentDateParam() || document.body.dataset.selectedDate;

        if (date) {
            out.set('date', date);
        }

        if (params.get('view') === 'week') {
            out.set('view', 'week');
        }

        CalendarPrefs.filterParamsFromSearchParams(params).forEach((value, key) => {
            out.set(key, value);
        });

        window.location.href = 'venues.php' + (out.toString() ? '?' + out.toString() : '');
    }

    getFilterScope(panel = null) {
        if (document.body.classList.contains('view-week')) {
            return document.querySelector('[data-week-by-venue]') || document;
        }

        if (panel) {
            return panel;
        }

        const venueBlock = document.querySelector('.venue-week__block');
        if (venueBlock) {
            return venueBlock;
        }

        const carousel = document.querySelector('[data-week-carousel]');
        if (!carousel) {
            return document;
        }

        const date = new URLSearchParams(window.location.search).get('date');
        if (date) {
            const datedPanel = carousel.querySelector(`.day-panel[data-date="${date}"]`);
            if (datedPanel) {
                return datedPanel;
            }
        }

        const startIndex = parseInt(carousel.dataset.startIndex || '0', 10);
        const panels = carousel.querySelectorAll('.day-panel');

        return panels[startIndex] || panels[0] || document;
    }

    collectTagCounts(scope) {
        const counts = new Map();

        this.venueBlocksInScope(scope).forEach((venue) => {
            venue.querySelectorAll('.event-line').forEach((line) => {
                EventFilter.parseTagList(line.dataset.tags || '').forEach((tag) => {
                    counts.set(tag, (counts.get(tag) || 0) + 1);
                });
            });
        });

        return counts;
    }

    countEventsInScope(scope) {
        let count = 0;

        this.venueBlocksInScope(scope).forEach((venue) => {
            count += venue.querySelectorAll('.event-line').length;
        });

        return count;
    }

    countVenuesInScope(scope) {
        const slugs = new Set();
        let anonymous = 0;

        this.venueBlocksInScope(scope).forEach((venue) => {
            if (venue.querySelectorAll('.event-line').length === 0) {
                return;
            }

            const slug = CalendarPrefs.venueSlug(venue);
            if (slug !== '') {
                slugs.add(slug);
            } else {
                anonymous += 1;
            }
        });

        return slugs.size + anonymous;
    }

    countFavoriteVenuesInScope(scope) {
        const slugs = new Set();

        this.venueBlocksInScope(scope).forEach((venue) => {
            if (venue.querySelectorAll('.event-line').length === 0) {
                return;
            }

            const slug = CalendarPrefs.venueSlug(venue);
            if (slug !== '' && this.favorites.has(slug)) {
                slugs.add(slug);
            }
        });

        return slugs.size;
    }

    effectiveFindQuery() {
        return this.findQuery.length >= 2 ? this.findQuery.toLowerCase() : '';
    }

    venueSearchText(venue) {
        const meta = venue.cloneNode(true);
        meta.querySelectorAll('.event-line').forEach((line) => line.remove());
        meta.querySelectorAll('.venue-details-link, .venue-profile-extras').forEach((node) => node.remove());
        meta.querySelectorAll('.venue-favorite').forEach((button) => button.remove());

        return meta.textContent.toLowerCase();
    }

    lineSearchText(line) {
        return line.textContent.toLowerCase();
    }

    venueMatchesSearch(venue, query) {
        return EventFilter.matchesWordStarts(this.venueSearchText(venue), query);
    }

    lineMatchesSearch(line, query, venueMatches) {
        if (venueMatches) {
            return true;
        }

        if (EventFilter.matchesWordStarts(this.lineSearchText(line), query)) {
            return true;
        }

        return EventFilter.parseTagList(line.dataset.tags || '').some((tag) =>
            EventFilter.matchesWordStarts(tag, query)
        );
    }

    formatTagName(tag) {
        return tag.toLowerCase();
    }

    ensureAllButton() {
        if (!this.tagsContainer) {
            return null;
        }

        let allButton = this.tagsContainer.querySelector('[data-filter-all]');
        if (!allButton) {
            allButton = document.createElement('button');
            allButton.type = 'button';
            allButton.className = 'event-filter__tag event-filter__tag--all';
            allButton.dataset.filterAll = '';
            this.tagsContainer.prepend(allButton);
        }

        this.allButton = allButton;

        return allButton;
    }

    syncVenueScope(venueCount, favoriteVenueCount) {
        this.totalVenueCount = venueCount;
        this.favoriteVenueCount = favoriteVenueCount;
        this.syncVenueScopeButtons();
    }

    rebuildTypeTagButtons(tagCounts, eventCount) {
        if (!this.tagsContainer) {
            return;
        }

        const tags = Array.from(tagCounts.keys()).sort();

        const allButton = this.ensureAllButton();
        allButton.textContent = `all events ${eventCount}`;
        allButton.setAttribute('aria-label', `all events, ${eventCount} events`);

        this.tagsContainer.querySelectorAll('[data-filter-tag]').forEach((button) => button.remove());

        tags.forEach((tag) => {
            const count = tagCounts.get(tag) || 0;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'event-filter__tag';
            button.dataset.filterTag = tag;
            button.setAttribute('aria-label', `${this.formatTagName(tag)}, ${count} events`);
            button.textContent = `${this.formatTagName(tag)} ${count}`;
            this.tagsContainer.appendChild(button);
        });

        if (this.typesSection) {
            const isEmpty = this.hideVenueScope
                ? false
                : eventCount === 0 && tagCounts.size === 0 && this.filtersAreNeutral();
            this.typesSection.classList.toggle('is-empty', isEmpty);
            this.root.classList.toggle('is-empty', isEmpty);
        }

        this.syncTypeTagButtons();
    }

    syncAvailableTags(panel = null) {
        const scope = this.getFilterScope(panel);
        const tagCounts = this.collectTagCounts(scope);
        this.totalEventCount = this.countEventsInScope(scope) || this.totalEventCount;
        const venueCount = this.countVenuesInScope(scope);
        const favoriteVenueCount = this.countFavoriteVenuesInScope(scope);
        this.syncVenueScope(venueCount, favoriteVenueCount);
        this.rebuildTypeTagButtons(tagCounts, this.totalEventCount);
        this.syncFilterSummary();
    }

    syncVenueScopeButtons() {
        if (this.allVenuesButton) {
            const count = this.totalVenueCount ?? 0;
            this.allVenuesButton.textContent = `all venues ${count}`;
            this.allVenuesButton.setAttribute('aria-label', `all venues, ${count} venues`);
            this.allVenuesButton.classList.toggle('is-active', !this.favoritesOnly);
            this.allVenuesButton.setAttribute('aria-pressed', !this.favoritesOnly ? 'true' : 'false');
        }

        if (this.myVenuesButton) {
            const count = this.favoriteVenueCount ?? 0;
            this.myVenuesButton.textContent = `my venues ${count}`;
            this.myVenuesButton.setAttribute('aria-label', `my venues, ${count} venues`);
            this.myVenuesButton.classList.toggle('is-active', this.favoritesOnly);
            this.myVenuesButton.setAttribute('aria-pressed', this.favoritesOnly ? 'true' : 'false');
        }

        this.syncFilterState();
    }

    syncTypeTagButtons() {
        if (!this.tagsContainer) {
            return;
        }

        const allActive = this.activeTags.size === 0;

        if (this.allButton) {
            this.allButton.classList.toggle('is-active', allActive);
            this.allButton.setAttribute('aria-pressed', allActive ? 'true' : 'false');
        }

        this.tagsContainer.querySelectorAll('[data-filter-tag]').forEach((button) => {
            const tag = (button.dataset.filterTag || '').toLowerCase();
            const isActive = this.activeTags.has(tag);
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        this.syncFilterState();
    }

    syncFilterState() {
        this.root.classList.toggle('has-active-filters', !this.filtersAreNeutral());
    }

    venueInScope(venue) {
        if (this.hideVenueScope) {
            return true;
        }

        if (!this.favoritesOnly) {
            return true;
        }

        const slug = CalendarPrefs.venueSlug(venue);

        return slug !== '' && this.favorites.has(slug);
    }

    apply() {
        const query = this.effectiveFindQuery();

        document.querySelectorAll('.venue-block').forEach((venue) => {
            const inScope = this.venueInScope(venue);
            const venueMatches = query !== '' && this.venueMatchesSearch(venue, query);
            const eventLines = venue.querySelectorAll('.event-line');

            eventLines.forEach((line) => {
                const tags = EventFilter.parseTagList(line.dataset.tags || '');
                const tagVisible = this.activeTags.size === 0 || tags.some((tag) => this.activeTags.has(tag));
                const findVisible = query === '' || this.lineMatchesSearch(line, query, venueMatches);
                line.classList.toggle('is-filtered-out', !(inScope && tagVisible && findVisible));
            });

            const hasVisible = Array.from(eventLines).some((line) => !line.classList.contains('is-filtered-out'));
            const hideVenue =
                eventLines.length > 0
                && !hasVisible
                && !document.body.classList.contains('venue-page');
            venue.classList.toggle('is-filtered-out', !inScope || hideVenue);
        });

        document.dispatchEvent(new CustomEvent('calendar:reflow'));
        this.syncFilterSummary();
        this.syncFilterState();
    }

    filtersAreNeutral() {
        const scopeNeutral = this.hideVenueScope || !this.favoritesOnly;

        return this.activeTags.size === 0 && this.effectiveFindQuery() === '' && scopeNeutral;
    }

    clearFilters() {
        this.activeTags.clear();
        this.findQuery = '';
        if (this.findInput) {
            this.findInput.value = '';
        }
        if (!this.hideVenueScope) {
            this.favoritesOnly = false;
            this.syncVenueScopeButtons();
        }
        this.syncTypeTagButtons();
        this.apply();
        this.persistState();
        this.togglePanel(true);
    }

    updateUrl() {
        const url = new URL(window.location.href);

        if (this.activeTags.size === 0) {
            url.searchParams.delete('tags');
        } else {
            url.searchParams.set('tags', Array.from(this.activeTags).sort().join(','));
        }

        if (this.effectiveFindQuery() === '') {
            url.searchParams.delete('find');
        } else {
            url.searchParams.set('find', this.findQuery);
        }

        if (!this.hideVenueScope && this.favoritesOnly) {
            url.searchParams.set('scope', 'favorites');
        } else {
            url.searchParams.delete('scope');
        }

        if (this.filtersAreNeutral()) {
            url.searchParams.set('prefs', 'neutral');
        } else {
            url.searchParams.delete('prefs');
        }

        if (this.root.classList.contains('is-open')) {
            url.searchParams.set('filter', 'open');
        } else if (!this.filterAlwaysOpen) {
            url.searchParams.delete('filter');
        }

        history.replaceState(null, '', url.toString());
    }

    persistState() {
        this.updateUrl();
        document.dispatchEvent(new CustomEvent('calendar:draftchange'));
        document.dispatchEvent(new CustomEvent('calendar:urlchange'));
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('calendar:clearfilters', () => {
        EventFilter.activeInstance?.clearFilters();
    });

    document.querySelectorAll('[data-event-filter]').forEach((root) => {
        new EventFilter(root);
    });
});
