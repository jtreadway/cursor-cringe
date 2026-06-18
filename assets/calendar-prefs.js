class CalendarPrefs {
    static COOKIE = {
        favorites: 'cringe_favorites',
        tags: 'cringe_tags',
        find: 'cringe_find',
        view: 'cringe_view',
        favoritesOnly: 'cringe_favorites_only',
    };

    static MAX_AGE_DAYS = 365;

    static read(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));

        return match ? decodeURIComponent(match[1]) : '';
    }

    static write(name, value) {
        const maxAge = CalendarPrefs.MAX_AGE_DAYS * 24 * 60 * 60;
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';

        document.cookie = `${name}=${encodeURIComponent(value)}; Path=/; Max-Age=${maxAge}; SameSite=Lax${secure}`;
    }

    static delete(name) {
        document.cookie = `${name}=; Path=/; Max-Age=0; SameSite=Lax`;
    }

    static parseFavorites(raw) {
        return new Set(
            raw
                .split(',')
                .map((slug) => slug.trim())
                .filter(Boolean)
        );
    }

    static serializeFavorites(favorites) {
        return Array.from(favorites).sort().join(',');
    }

    static parseTags(raw) {
        return raw
            .split(',')
            .map((tag) => tag.trim().toLowerCase())
            .filter(Boolean);
    }

    static load() {
        const favoritesOnlyRaw = CalendarPrefs.read(CalendarPrefs.COOKIE.favoritesOnly);

        return {
            favorites: CalendarPrefs.parseFavorites(CalendarPrefs.read(CalendarPrefs.COOKIE.favorites)),
            tags: CalendarPrefs.parseTags(CalendarPrefs.read(CalendarPrefs.COOKIE.tags)),
            find: CalendarPrefs.read(CalendarPrefs.COOKIE.find).trim(),
            view: CalendarPrefs.read(CalendarPrefs.COOKIE.view) === 'week' ? 'week' : 'day',
            favoritesOnly: favoritesOnlyRaw === '1',
        };
    }

    static saveFavorites(favorites) {
        const serialized = CalendarPrefs.serializeFavorites(favorites);

        if (serialized === '') {
            CalendarPrefs.delete(CalendarPrefs.COOKIE.favorites);
        } else {
            CalendarPrefs.write(CalendarPrefs.COOKIE.favorites, serialized);
        }
    }

    static saveTags(tags) {
        const serialized = tags.sort().join(',');

        if (serialized === '') {
            CalendarPrefs.delete(CalendarPrefs.COOKIE.tags);
        } else {
            CalendarPrefs.write(CalendarPrefs.COOKIE.tags, serialized);
        }
    }

    static saveFind(query) {
        const trimmed = query.trim();

        if (trimmed === '' || trimmed.length < 2) {
            CalendarPrefs.delete(CalendarPrefs.COOKIE.find);
        } else {
            CalendarPrefs.write(CalendarPrefs.COOKIE.find, trimmed);
        }
    }

    static saveView(view) {
        CalendarPrefs.write(CalendarPrefs.COOKIE.view, view === 'week' ? 'week' : 'day');
    }

    static saveFavoritesOnly(active) {
        if (active) {
            CalendarPrefs.write(CalendarPrefs.COOKIE.favoritesOnly, '1');
        } else {
            CalendarPrefs.delete(CalendarPrefs.COOKIE.favoritesOnly);
        }
    }
}
