class VenueFavorites {
    constructor() {
        this.favorites = CalendarPrefs.load().favorites;
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
        const slug = venue?.dataset.venueSlug || '';
        if (slug === '') {
            return;
        }

        if (this.favorites.has(slug)) {
            this.favorites.delete(slug);
        } else {
            this.favorites.add(slug);
        }

        CalendarPrefs.saveFavorites(this.favorites);
        this.syncButtons();
        document.dispatchEvent(
            new CustomEvent('calendar:favoriteschange', {
                detail: { favorites: this.favorites },
            })
        );
    }

    syncButtons() {
        document.querySelectorAll('.venue-block').forEach((venue) => {
            const slug = venue.dataset.venueSlug || '';
            const button = venue.querySelector('[data-venue-favorite]');
            if (!button) {
                return;
            }

            const isFavorite = slug !== '' && this.favorites.has(slug);
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
