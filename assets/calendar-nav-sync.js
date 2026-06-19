class CalendarNavSync {
    static init() {
        if (!document.querySelector('[data-nav-sync]')) {
            return;
        }

        CalendarNavSync.sync();
        document.addEventListener('calendar:urlchange', CalendarNavSync.sync);
        document.addEventListener('calendar:draftchange', CalendarNavSync.sync);
        window.addEventListener('popstate', CalendarNavSync.sync);
    }

    static sync() {
        const params = new URLSearchParams(window.location.search);
        const currentView = CalendarPrefs.normalizeView(params.get('view') || 'day');

        document.querySelectorAll('[data-nav-sync]').forEach((link) => {
            const date = link.dataset.navDate || '';
            if (!/^\d{8}$/.test(date)) {
                return;
            }

            const view = link.dataset.navView || currentView;
            const venue = link.dataset.navVenue || '';

            link.href = venue !== ''
                ? CalendarPrefs.buildVenueHref(venue, date, params)
                : CalendarPrefs.buildIndexHref(date, view, params);
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    CalendarNavSync.init();
});
