class EventFilter {
    constructor(root) {
        this.root = root;
        this.tagsContainer = root.querySelector('.event-filter__tags');
        this.activeTags = new Set(
            (root.dataset.activeTags || '')
                .split(',')
                .map((tag) => tag.trim().toLowerCase())
                .filter(Boolean)
        );

        this.bindControls();
        this.syncAvailableTags();
        this.apply();

        document.addEventListener('calendar:daychange', (event) => {
            this.syncAvailableTags(event.detail?.panel ?? null);
        });
    }

    bindControls() {
        this.tagsContainer.addEventListener('click', (event) => {
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
            this.updateUrl();
        });

        const clearButton = this.root.querySelector('[data-filter-clear]');
        if (clearButton) {
            clearButton.addEventListener('click', () => {
                this.activeTags.clear();
                this.syncButtons();
                this.apply();
                this.updateUrl();
            });
        }
    }

    getFilterScope(panel = null) {
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

        scope.querySelectorAll('.event-line').forEach((line) => {
            (line.dataset.tags || '')
                .split(/\s+/)
                .map((tag) => tag.trim().toLowerCase())
                .filter(Boolean)
                .forEach((tag) => {
                    counts.set(tag, (counts.get(tag) || 0) + 1);
                });
        });

        return counts;
    }

    formatTagName(tag) {
        return tag.replace(/\b\w/g, (char) => char.toUpperCase());
    }

    rebuildTagButtons(tagCounts) {
        const tags = Array.from(tagCounts.keys()).sort();
        let activeChanged = false;

        this.activeTags.forEach((tag) => {
            if (!tagCounts.has(tag)) {
                this.activeTags.delete(tag);
                activeChanged = true;
            }
        });

        const clearButton = this.tagsContainer.querySelector('[data-filter-clear]');
        this.tagsContainer.querySelectorAll('[data-filter-tag]').forEach((button) => button.remove());

        tags.forEach((tag) => {
            const count = tagCounts.get(tag) || 0;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'event-filter__tag';
            button.dataset.filterTag = tag;
            button.setAttribute('aria-label', `${this.formatTagName(tag)}, ${count} events`);

            button.textContent = `${this.formatTagName(tag)} ${count}`;
            this.tagsContainer.insertBefore(button, clearButton);
        });

        this.root.classList.toggle('is-empty', tags.length === 0);
        this.syncButtons();

        if (activeChanged) {
            this.apply();
            this.updateUrl();
        }
    }

    syncAvailableTags(panel = null) {
        const scope = this.getFilterScope(panel);
        const tagCounts = this.collectTagCounts(scope);
        this.rebuildTagButtons(tagCounts);
    }

    syncButtons() {
        this.tagsContainer.querySelectorAll('[data-filter-tag]').forEach((button) => {
            const tag = (button.dataset.filterTag || '').toLowerCase();
            const isActive = this.activeTags.has(tag);
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    apply() {
        const lines = document.querySelectorAll('.event-line');
        lines.forEach((line) => {
            const tags = (line.dataset.tags || '').split(/\s+/).filter(Boolean);
            const visible = this.activeTags.size === 0 || tags.some((tag) => this.activeTags.has(tag.toLowerCase()));
            line.classList.toggle('is-filtered-out', !visible);
        });

        document.querySelectorAll('.venue-block').forEach((venue) => {
            const eventLines = venue.querySelectorAll('.event-line');
            const hasVisible = Array.from(eventLines).some((line) => !line.classList.contains('is-filtered-out'));
            venue.classList.toggle('is-filtered-out', eventLines.length > 0 && !hasVisible);
        });

        document.dispatchEvent(new CustomEvent('calendar:reflow'));
    }

    updateUrl() {
        const url = new URL(window.location.href);
        if (this.activeTags.size === 0) {
            url.searchParams.delete('tags');
        } else {
            url.searchParams.set('tags', Array.from(this.activeTags).sort().join(','));
        }

        history.replaceState(null, '', url.toString());
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-event-filter]').forEach((root) => {
        new EventFilter(root);
    });
});
