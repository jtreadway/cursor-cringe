class VenueFavorites {
    constructor() {
        const saved = CalendarPrefs.loadSaved();
        this.favorites = CalendarPrefs.parseFavorites(saved.favorites);
        this.bindControls();
        this.syncButtons();
        VenueFavorites.instance = this;
    }

    static getInstance() {
        return VenueFavorites.instance;
    }

    bindControls() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-venue-favorite]');
            if (!button) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.toggleFavorite(button);
        });

        document.addEventListener('keydown', (event) => {
            const button = event.target.closest('[data-venue-favorite]');
            if (!button) {
                return;
            }

            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            this.toggleFavorite(button);
        });
    }

    toggleFavorite(button) {
        const venue = button.closest('.venue-block');
        const slugs = CalendarPrefs.venueSlugs(venue);
        if (slugs.length === 0) {
            return;
        }

        const anyFavorite = slugs.some((slug) => this.favorites.has(slug));
        if (anyFavorite) {
            slugs.forEach((slug) => this.favorites.delete(slug));
        } else {
            slugs.forEach((slug) => this.favorites.add(slug));
        }

        CalendarPrefs.saveFavoritesOnly(this.favorites);
        this.syncButtons();
        document.dispatchEvent(
            new CustomEvent('calendar:favoriteschange', {
                detail: { favorites: this.favorites },
            })
        );
        document.dispatchEvent(
            new CustomEvent('calendar:prefssaved', {
                detail: { favoritesOnly: true, favorites: CalendarPrefs.serializeFavorites(this.favorites) },
            })
        );
    }

    setFavorites(favorites) {
        this.favorites = favorites instanceof Set ? new Set(favorites) : CalendarPrefs.parseFavorites(favorites);
        CalendarPrefs.saveFavoritesOnly(this.favorites);
        this.syncButtons();
        document.dispatchEvent(
            new CustomEvent('calendar:favoriteschange', {
                detail: { favorites: this.favorites },
            })
        );
    }

    syncButtons() {
        document.querySelectorAll('.venue-block').forEach((venue) => {
            const slugs = CalendarPrefs.venueSlugs(venue);
            const button = venue.querySelector('[data-venue-favorite]');
            if (!button || slugs.length === 0) {
                return;
            }

            const isFavorite = slugs.some((slug) => this.favorites.has(slug));
            button.classList.toggle('is-favorite', isFavorite);
            const shape = button.querySelector('.venue-favorite__shape');
            if (shape) {
                shape.setAttribute('fill', isFavorite ? '#ee0000' : '#cccccc');
            }
            button.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
            button.setAttribute('aria-label', isFavorite ? 'Remove from favorites' : 'Add to favorites');
            button.setAttribute('title', isFavorite ? 'Remove from favorites' : 'Add to favorites');
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[data-venue-favorite]')) {
        new VenueFavorites();
    }
});
