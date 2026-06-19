document.addEventListener('DOMContentLoaded', () => {
    const summary = document.querySelector('.venues-manage__summary');
    if (!summary) {
        return;
    }

    const updateSummary = () => {
        const favorites = VenueFavorites.getInstance()?.favorites ?? CalendarPrefs.loadFavoritesSet();
        let count = 0;

        document.querySelectorAll('.venue-manage__row').forEach((row) => {
            const slugs = CalendarPrefs.venueSlugs(row);
            if (slugs.some((slug) => favorites.has(slug))) {
                count += 1;
            }
        });

        summary.textContent = `${count} favorite${count === 1 ? '' : 's'} selected`;
    };

    document.addEventListener('calendar:favoriteschange', updateSummary);
    document.addEventListener('calendar:prefssaved', updateSummary);
    updateSummary();
});
