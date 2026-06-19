class PreferencesUi {
    constructor(root) {
        this.root = root;
        this.clearButton = root.querySelector('[data-prefs-clear]');
        this.saveButton = root.querySelector('[data-prefs-save]');
        this.favoritesOnly = root.hasAttribute('data-prefs-favorites-only');

        this.bindControls();
        this.syncState();
        document.addEventListener('calendar:favoriteschange', () => this.syncState());
        document.addEventListener('calendar:draftchange', () => this.syncState());
    }

    bindControls() {
        this.clearButton?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            this.clearPreferences();
        });
        this.saveButton?.addEventListener('click', () => this.savePreferences());
    }

    currentDate() {
        return CalendarPrefs.currentDateParam() || document.body.dataset.selectedDate || '';
    }

    clearPreferences() {
        document.dispatchEvent(new CustomEvent('calendar:clearfilters'));
    }

    savePreferences() {
        if (this.favoritesOnly) {
            const favorites = VenueFavorites.getInstance()?.favorites ?? CalendarPrefs.parseFavorites('');
            CalendarPrefs.saveFavoritesOnly(favorites);
            this.syncState();
            document.dispatchEvent(
                new CustomEvent('calendar:prefssaved', {
                    detail: { favoritesOnly: true, favorites: CalendarPrefs.serializeFavorites(favorites) },
                })
            );

            return;
        }

        const snapshot = CalendarPrefs.snapshotFromLocation();
        CalendarPrefs.saveAll(snapshot);

        const favorites = CalendarPrefs.parseFavorites(snapshot.favorites);
        VenueFavorites.getInstance()?.setFavorites(favorites);

        this.syncState();
        document.dispatchEvent(new CustomEvent('calendar:prefssaved', { detail: { snapshot } }));
    }

    syncState() {
        if (this.favoritesOnly) {
            if (this.saveButton) {
                this.saveButton.disabled = !CalendarPrefs.favoritesDirty();
            }

            return;
        }

        const saved = CalendarPrefs.loadSaved();
        const current = CalendarPrefs.snapshotFromLocation();
        const isDirty = !CalendarPrefs.snapshotsEqual(current, saved);

        if (this.saveButton) {
            this.saveButton.disabled = !isDirty;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-prefs-actions]').forEach((root) => {
        new PreferencesUi(root);
    });
});
