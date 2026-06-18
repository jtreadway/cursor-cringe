class EventFilter {
    constructor(root) {
        this.root = root;
        this.tagsContainer = root.querySelector('.event-filter__type-tags');
        this.typesSection = root.querySelector('.event-filter__types');
        this.allButton = root.querySelector('[data-filter-all]');
        this.findInput = root.querySelector('[data-filter-find]');

        const prefs = CalendarPrefs.load();
        const urlParams = new URLSearchParams(window.location.search);

        this.favorites = VenueFavorites.getInstance()?.favorites ?? prefs.favorites;

        if (urlParams.has('tags')) {
            this.activeTags = new Set(EventFilter.parseTagList(urlParams.get('tags') || ''));
        } else if (prefs.tags.length > 0) {
            this.activeTags = new Set(prefs.tags);
        } else {
            this.activeTags = new Set(EventFilter.parseTagList(root.dataset.activeTags || ''));
        }

        if (urlParams.has('find')) {
            this.findQuery = (urlParams.get('find') || '').trim();
        } else {
            this.findQuery = prefs.find;
        }

        if (this.findInput) {
            this.findInput.value = this.findQuery;
        }

        if (urlParams.has('favorites')) {
            this.favoritesOnly = urlParams.get('favorites') === '1';
        } else {
            this.favoritesOnly = prefs.favoritesOnly;
        }

        this.bindControls();
        this.syncVenueScopeToggles();
        if (this.tagsContainer) {
            this.syncAvailableTags();
        }
        this.apply();
        this.savePrefs();
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
        if (this.tagsContainer) {
            this.tagsContainer.addEventListener('click', (event) => {
                const allButton = event.target.closest('[data-filter-all]');
                if (allButton) {
                    this.activeTags.clear();
                    this.syncButtons();
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

                this.syncButtons();
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

        document.addEventListener('click', (event) => {
            const favoritesToggle = event.target.closest('[data-filter-favorites-only]');
            if (!favoritesToggle) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.favoritesOnly = !this.favoritesOnly;
            this.syncVenueScopeToggles();
            this.syncAvailableTags();
            this.apply();
            this.persistState();
        });

        document.addEventListener('calendar:favoriteschange', (event) => {
            this.favorites = event.detail?.favorites ?? this.favorites;
            this.syncVenueScopeToggles();
            this.syncAvailableTags();
            this.apply();
        });

        document.addEventListener('calendar:daychange', (event) => {
            if (document.body.classList.contains('view-week')) {
                return;
            }

            VenueFavorites.getInstance()?.syncButtons();
            this.syncVenueScopeToggles();
            this.syncAvailableTags(event.detail?.panel ?? null);
        });
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

    venuePassesFavoritesFilter(venue) {
        if (!this.favoritesOnly) {
            return true;
        }

        const slug = venue.dataset.venueSlug || '';

        return slug !== '' && this.favorites.has(slug);
    }

    collectTagCounts(scope) {
        const counts = new Map();

        scope.querySelectorAll('.venue-block').forEach((venue) => {
            if (!this.venuePassesFavoritesFilter(venue)) {
                return;
            }

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
            if (!this.venuePassesFavoritesFilter(venue)) {
                return;
            }

            count += venue.querySelectorAll('.event-line').length;
        });

        return count;
    }

    effectiveFindQuery() {
        return this.findQuery.length >= 2 ? this.findQuery.toLowerCase() : '';
    }

    venueSearchText(venue) {
        const meta = venue.cloneNode(true);
        meta.querySelectorAll('.event-line').forEach((line) => line.remove());
        meta.querySelectorAll('.venue-week-link').forEach((link) => link.remove());
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

    rebuildTagButtons(tagCounts, eventCount) {
        if (!this.tagsContainer) {
            return;
        }

        const tags = Array.from(tagCounts.keys()).sort();
        let activeChanged = false;

        this.activeTags.forEach((tag) => {
            if (!tagCounts.has(tag)) {
                this.activeTags.delete(tag);
                activeChanged = true;
            }
        });

        const allButton = this.ensureAllButton();
        allButton.textContent = `all ${eventCount}`;
        allButton.setAttribute('aria-label', `all, ${eventCount} events`);

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
            this.typesSection.classList.toggle('is-empty', eventCount === 0 && tags.length === 0);
        }
        this.syncButtons();

        if (activeChanged) {
            this.apply();
            this.persistState();
        }
    }

    syncAvailableTags(panel = null) {
        const scope = this.getFilterScope(panel);
        const tagCounts = this.collectTagCounts(scope);
        const eventCount = this.countEventsInScope(scope);
        this.rebuildTagButtons(tagCounts, eventCount);
    }

    syncButtons() {
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
    }

    countVenuesInScope(scope) {
        return scope.querySelectorAll('.venue-block').length;
    }

    countFavoriteVenuesInScope(scope) {
        let count = 0;

        scope.querySelectorAll('.venue-block').forEach((venue) => {
            const slug = venue.dataset.venueSlug || '';
            if (slug !== '' && this.favorites.has(slug)) {
                count += 1;
            }
        });

        return count;
    }

    scopeContainers() {
        if (document.body.classList.contains('view-week')) {
            const week = document.querySelector('[data-week-by-venue]');

            return week ? [week] : [];
        }

        return Array.from(document.querySelectorAll('.day-panel'));
    }

    syncVenueScopeToggles() {
        this.scopeContainers().forEach((scope) => {
            const toggle = scope.querySelector('[data-venue-scope-toggle]');
            if (!toggle) {
                return;
            }

            const button = toggle.querySelector('[data-filter-favorites-only]');
            const label = toggle.querySelector('[data-venue-scope-label]');
            const countEl = toggle.querySelector('[data-venue-scope-count]');
            const shape = toggle.querySelector('.venue-scope-toggle__shape');
            const allCount = this.countVenuesInScope(scope);
            const favoriteCount = this.countFavoriteVenuesInScope(scope);

            if (this.favoritesOnly) {
                if (label) {
                    label.textContent = 'show all venues';
                }
                if (countEl) {
                    countEl.textContent = String(allCount);
                }
                if (shape) {
                    shape.setAttribute('fill', '#ee0000');
                }
                if (button) {
                    button.classList.add('is-favorites-only');
                    button.setAttribute('aria-pressed', 'true');
                    button.setAttribute(
                        'aria-label',
                        `Show all venues, ${allCount} venues`
                    );
                }
            } else {
                if (label) {
                    label.textContent = 'show favorite venues only';
                }
                if (countEl) {
                    countEl.textContent = String(favoriteCount);
                }
                if (shape) {
                    shape.setAttribute('fill', '#cccccc');
                }
                if (button) {
                    button.classList.remove('is-favorites-only');
                    button.setAttribute('aria-pressed', 'false');
                    button.setAttribute(
                        'aria-label',
                        `Show favorite venues only, ${favoriteCount} venues`
                    );
                }
            }
        });
    }

    syncFavoriteButtons() {
        VenueFavorites.getInstance()?.syncButtons();
    }

    apply() {
        const query = this.effectiveFindQuery();

        document.querySelectorAll('.venue-block').forEach((venue) => {
            if (!this.venuePassesFavoritesFilter(venue)) {
                venue.classList.add('is-filtered-out');
                return;
            }

            const venueMatches = query !== '' && this.venueMatchesSearch(venue, query);
            const eventLines = venue.querySelectorAll('.event-line');

            eventLines.forEach((line) => {
                const tags = EventFilter.parseTagList(line.dataset.tags || '');
                const tagVisible = this.activeTags.size === 0 || tags.some((tag) => this.activeTags.has(tag));
                const findVisible = query === '' || this.lineMatchesSearch(line, query, venueMatches);
                line.classList.toggle('is-filtered-out', !(tagVisible && findVisible));
            });

            const hasVisible = Array.from(eventLines).some((line) => !line.classList.contains('is-filtered-out'));
            venue.classList.toggle('is-filtered-out', eventLines.length > 0 && !hasVisible);
        });

        document.dispatchEvent(new CustomEvent('calendar:reflow'));
        this.syncVenueScopeToggles();
    }

    savePrefs() {
        CalendarPrefs.saveTags(Array.from(this.activeTags));
        CalendarPrefs.saveFind(this.findQuery);
        CalendarPrefs.saveFavoritesOnly(this.favoritesOnly);
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
            url.searchParams.set('favorites', '1');
        } else {
            url.searchParams.delete('favorites');
        }

        history.replaceState(null, '', url.toString());
    }

    persistState() {
        this.savePrefs();
        this.updateUrl();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.body.classList.contains('view-week')) {
        CalendarPrefs.saveView('week');
    } else if (document.body.classList.contains('view-day')) {
        CalendarPrefs.saveView('day');
    }

    document.querySelectorAll('[data-event-filter]').forEach((root) => {
        new EventFilter(root);
    });
});
