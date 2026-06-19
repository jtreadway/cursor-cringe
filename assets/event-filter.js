class EventFilter {
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
        this.toggleButton = root.querySelector('[data-filter-toggle]');
        this.panel = root.querySelector('[data-filter-panel]');
        this.toggleLabel = root.querySelector('.event-filter__toggle-label');

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

        if (urlParams.has('scope')) {
            this.favoritesOnly = EventFilter.favoritesOnlyFromParams(urlParams);
        } else {
            this.favoritesOnly =
                Boolean(this.myVenuesButton?.classList.contains('is-active'))
                || saved.scope === 'favorites';
        }

        if (this.findInput) {
            this.findInput.value = this.findQuery;
        }

        this.totalVenueCount = 0;
        this.favoriteVenueCount = 0;

        this.bindControls();
        if (this.tagsContainer || this.venueScopeContainer) {
            this.syncAvailableTags();
        }
        this.apply();
        this.updateUrl();
        this.syncToggleLabel();
    }

    static favoritesOnlyFromParams(params) {
        return params.get('scope') === 'favorites';
    }

    static parseTagList(raw) {
        return raw
            .split(',')
            .map((tag) => tag.trim().toLowerCase())
            .filter(Boolean);
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
        }

        if (this.toggleButton && this.panel) {
            this.toggleButton.addEventListener('click', () => this.togglePanel());
        }

        document.addEventListener('calendar:favoriteschange', () => {
            this.favorites = CalendarPrefs.loadFavoritesSet();
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
        const params = new URLSearchParams(window.location.search);
        this.togglePanel(params.get('filter') === 'open');
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

        if (params.has('scope')) {
            this.favoritesOnly = EventFilter.favoritesOnlyFromParams(params);
        } else {
            this.favoritesOnly = false;
        }
    }

    togglePanel(forceOpen = null) {
        if (!this.toggleButton || !this.panel) {
            return;
        }

        const open = forceOpen === null ? !this.root.classList.contains('is-open') : forceOpen;
        this.root.classList.toggle('is-open', open);
        this.panel.hidden = !open;
        this.toggleButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        this.syncFilterOpenInUrl(open);
    }

    syncFilterOpenInUrl(open) {
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

        scope.querySelectorAll('.venue-block').forEach((venue) => {
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

        scope.querySelectorAll('.venue-block').forEach((venue) => {
            count += venue.querySelectorAll('.event-line').length;
        });

        return count;
    }

    countVenuesInScope(scope) {
        const slugs = new Set();
        let anonymous = 0;

        scope.querySelectorAll('.venue-block').forEach((venue) => {
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

        scope.querySelectorAll('.venue-block').forEach((venue) => {
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
        meta.querySelectorAll('.venue-schedule-link').forEach((link) => link.remove());
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
            const isEmpty = eventCount === 0 && tagCounts.size === 0 && this.filtersAreNeutral();
            this.typesSection.classList.toggle('is-empty', isEmpty);
            this.root.classList.toggle('is-empty', isEmpty);
        }

        this.syncTypeTagButtons();
    }

    syncAvailableTags(panel = null) {
        const scope = this.getFilterScope(panel);
        const tagCounts = this.collectTagCounts(scope);
        const eventCount = this.countEventsInScope(scope);
        const venueCount = this.countVenuesInScope(scope);
        const favoriteVenueCount = this.countFavoriteVenuesInScope(scope);
        this.syncVenueScope(venueCount, favoriteVenueCount);
        this.rebuildTypeTagButtons(tagCounts, eventCount);
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

        this.syncToggleLabel();
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

        this.syncToggleLabel();
    }

    syncToggleLabel() {
        if (!this.toggleLabel) {
            return;
        }

        const parts = [];

        if (this.activeTags.size > 0) {
            parts.push(Array.from(this.activeTags).sort().join(', '));
        }

        if (this.favoritesOnly) {
            parts.push('my venues');
        }

        if (this.effectiveFindQuery() !== '') {
            parts.push(`"${this.findQuery.trim()}"`);
        }

        this.toggleLabel.textContent = parts.length > 0 ? `Filters · ${parts.join(' · ')}` : 'Filters';
        this.root.classList.toggle('has-active-filters', parts.length > 0);
    }

    venueInScope(venue) {
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
    }

    filtersAreNeutral() {
        return this.activeTags.size === 0 && this.effectiveFindQuery() === '' && !this.favoritesOnly;
    }

    clearFilters() {
        this.activeTags.clear();
        this.findQuery = '';
        if (this.findInput) {
            this.findInput.value = '';
        }
        this.favoritesOnly = false;
        this.syncTypeTagButtons();
        this.syncVenueScopeButtons();
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

        if (this.favoritesOnly) {
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
        } else {
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
