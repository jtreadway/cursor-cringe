document.addEventListener('click', (event) => {
    const link = event.target.closest('.venue-details-link, .venue-block a[href*="venue.php"]');
    if (!link) {
        return;
    }

    const venue = link.closest('.venue-block');
    const slug = CalendarPrefs.venueSlug(venue);
    if (slug !== '') {
        CalendarPrefs.addRecent(slug);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const slug = document.body.dataset.trackVenue || '';
    if (slug !== '') {
        CalendarPrefs.addRecent(slug);
    }
});
