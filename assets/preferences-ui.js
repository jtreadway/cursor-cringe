class PreferencesUi {
    constructor(root) {
        this.root = root;
        this.clearButton = root.querySelector('[data-prefs-clear]');
        this.saveButton = root.querySelector('[data-prefs-save]');

        this.bindControls();
        this.syncState();
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

    clearPreferences() {
        document.dispatchEvent(new CustomEvent('calendar:clearfilters'));
    }

    savePreferences() {
        const snapshot = CalendarPrefs.snapshotFiltersFromLocation();
        CalendarPrefs.saveFiltersOnly(snapshot);
        this.syncState();
        document.dispatchEvent(new CustomEvent('calendar:prefssaved', { detail: { filters: snapshot } }));
    }

    syncState() {
        const saved = CalendarPrefs.loadSavedFilters();
        const current = CalendarPrefs.snapshotFiltersFromLocation();
        const isDirty = !CalendarPrefs.filtersSnapshotsEqual(current, saved);

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
