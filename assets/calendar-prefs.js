class CalendarPrefs {
    static COOKIE = {
        favorites: 'cringe_favorites',
        recent: 'cringe_recent',
        tags: 'cringe_tags',
        find: 'cringe_find',
        view: 'cringe_view',
        scope: 'cringe_scope',
        engaged: 'cringe_favorites_engaged',
    };

    static MAX_AGE_DAYS = 365;
    static MAX_RECENT = 5;

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

    static loadFavoritesSet() {
        const live = VenueFavorites.getInstance()?.favorites;
        if (live instanceof Set) {
            return new Set(live);
        }

        return CalendarPrefs.parseFavorites(CalendarPrefs.loadSaved().favorites);
    }

    static venueSlug(element) {
        if (!element) {
            return '';
        }

        return element.getAttribute('data-venue-slug') || '';
    }

    static venueSlugs(element) {
        if (!element) {
            return [];
        }

        const multi = element.getAttribute('data-venue-slugs');
        if (multi) {
            return multi
                .split(',')
                .map((slug) => slug.trim())
                .filter(Boolean);
        }

        const slug = CalendarPrefs.venueSlug(element);

        return slug !== '' ? [slug] : [];
    }

    static serializeFavorites(favorites) {
        return Array.from(favorites).sort().join(',');
    }

    static parseRecent(raw) {
        return raw
            .split(',')
            .map((slug) => slug.trim())
            .filter(Boolean)
            .slice(0, CalendarPrefs.MAX_RECENT);
    }

    static serializeRecent(recent) {
        return recent.slice(0, CalendarPrefs.MAX_RECENT).join(',');
    }

    static parseTags(raw) {
        return raw
            .split(',')
            .map((tag) => tag.trim().toLowerCase())
            .filter(Boolean);
    }

    static normalizeView(raw) {
        return raw === 'week' ? 'week' : 'day';
    }

    static normalizeScope(raw) {
        return raw === 'favorites' ? 'favorites' : 'all';
    }

    static snapshotFromValues({ view, tags, find, favorites, scope }) {
        return {
            view: CalendarPrefs.normalizeView(view || 'day'),
            tags: CalendarPrefs.parseTags(Array.isArray(tags) ? tags.join(',') : tags || ''),
            find: (find || '').trim(),
            favorites: CalendarPrefs.serializeFavorites(
                favorites instanceof Set ? favorites : CalendarPrefs.parseFavorites(favorites || '')
            ),
            scope: CalendarPrefs.normalizeScope(scope || 'all'),
        };
    }

    static loadSaved() {
        return CalendarPrefs.snapshotFromValues({
            view: CalendarPrefs.read(CalendarPrefs.COOKIE.view),
            tags: CalendarPrefs.read(CalendarPrefs.COOKIE.tags),
            find: CalendarPrefs.read(CalendarPrefs.COOKIE.find),
            favorites: CalendarPrefs.read(CalendarPrefs.COOKIE.favorites),
            scope: CalendarPrefs.read(CalendarPrefs.COOKIE.scope),
        });
    }

    static hasSavedPreferences(saved = CalendarPrefs.loadSaved()) {
        return (
            saved.view !== 'day'
            || saved.tags.length > 0
            || saved.find.length >= 2
            || saved.favorites !== ''
            || saved.scope === 'favorites'
        );
    }

    static snapshotFromLocation(favoritesOverride = null) {
        const params = new URLSearchParams(window.location.search);
        const favorites =
            favoritesOverride instanceof Set
                ? favoritesOverride
                : VenueFavorites.getInstance()?.favorites ?? CalendarPrefs.parseFavorites('');

        return CalendarPrefs.snapshotFromValues({
            view: params.get('view') || 'day',
            tags: params.get('tags') || '',
            find: params.get('find') || '',
            favorites,
            scope: params.get('scope') || 'all',
        });
    }

    static snapshotsEqual(a, b) {
        if (a.view !== b.view || a.find !== b.find || a.scope !== b.scope || a.favorites !== b.favorites) {
            return false;
        }

        if (a.tags.length !== b.tags.length) {
            return false;
        }

        const aTags = [...a.tags].sort();
        const bTags = [...b.tags].sort();

        return aTags.every((tag, index) => tag === bTags[index]);
    }

    static filterParamsFromSearchParams(sourceParams) {
        const params = sourceParams instanceof URLSearchParams ? sourceParams : new URLSearchParams(sourceParams);
        const out = new URLSearchParams();

        if (params.get('tags')) {
            out.set('tags', params.get('tags'));
        }

        if (params.get('find')) {
            out.set('find', params.get('find'));
        }

        if (params.get('scope') === 'favorites') {
            out.set('scope', 'favorites');
        }

        if (params.get('prefs') === 'neutral') {
            out.set('prefs', 'neutral');
        }

        return out;
    }

    static appendFilterParams(out, sourceParams) {
        CalendarPrefs.filterParamsFromSearchParams(sourceParams).forEach((value, key) => {
            out.set(key, value);
        });

        return out;
    }

    static buildIndexHref(dateYmd, view, sourceParams = null) {
        const src = sourceParams instanceof URLSearchParams
            ? sourceParams
            : new URLSearchParams(window.location.search);
        const out = new URLSearchParams();

        out.set('date', dateYmd);
        out.set('view', CalendarPrefs.normalizeView(view));
        CalendarPrefs.appendFilterParams(out, src);

        return `?${out.toString()}`;
    }

    static buildVenueHref(venueSlug, dateYmd, sourceParams = null) {
        const src = sourceParams instanceof URLSearchParams
            ? sourceParams
            : new URLSearchParams(window.location.search);
        const out = new URLSearchParams();

        out.set('venue', venueSlug);
        out.set('date', dateYmd);
        CalendarPrefs.appendFilterParams(out, src);

        return `venue.php?${out.toString()}`;
    }

    static saveAll(snapshot) {
        const normalized = CalendarPrefs.snapshotFromValues(snapshot);
        CalendarPrefs.saveFavoritesOnly(normalized.favorites);
        CalendarPrefs.saveFiltersOnly(normalized);
    }

    static filterSnapshotFromValues({ view, tags, find, scope }) {
        return {
            view: CalendarPrefs.normalizeView(view || 'day'),
            tags: CalendarPrefs.parseTags(Array.isArray(tags) ? tags.join(',') : tags || ''),
            find: (find || '').trim(),
            scope: CalendarPrefs.normalizeScope(scope || 'all'),
        };
    }

    static loadSavedFilters() {
        return CalendarPrefs.filterSnapshotFromValues({
            view: CalendarPrefs.read(CalendarPrefs.COOKIE.view),
            tags: CalendarPrefs.read(CalendarPrefs.COOKIE.tags),
            find: CalendarPrefs.read(CalendarPrefs.COOKIE.find),
            scope: CalendarPrefs.read(CalendarPrefs.COOKIE.scope),
        });
    }

    static snapshotFiltersFromLocation() {
        const params = new URLSearchParams(window.location.search);

        return CalendarPrefs.filterSnapshotFromValues({
            view: params.get('view') || 'day',
            tags: params.get('tags') || '',
            find: params.get('find') || '',
            scope: params.get('scope') || 'all',
        });
    }

    static filtersSnapshotsEqual(a, b) {
        if (a.view !== b.view || a.find !== b.find || a.scope !== b.scope) {
            return false;
        }

        if (a.tags.length !== b.tags.length) {
            return false;
        }

        const aTags = [...a.tags].sort();
        const bTags = [...b.tags].sort();

        return aTags.every((tag, index) => tag === bTags[index]);
    }

    static saveFiltersOnly(snapshot) {
        const normalized = CalendarPrefs.filterSnapshotFromValues(snapshot);

        if (normalized.tags.length === 0) {
            CalendarPrefs.delete(CalendarPrefs.COOKIE.tags);
        } else {
            CalendarPrefs.write(CalendarPrefs.COOKIE.tags, normalized.tags.sort().join(','));
        }

        if (normalized.find === '' || normalized.find.length < 2) {
            CalendarPrefs.delete(CalendarPrefs.COOKIE.find);
        } else {
            CalendarPrefs.write(CalendarPrefs.COOKIE.find, normalized.find);
        }

        CalendarPrefs.write(CalendarPrefs.COOKIE.view, normalized.view);

        if (normalized.scope === 'all') {
            CalendarPrefs.delete(CalendarPrefs.COOKIE.scope);
        } else {
            CalendarPrefs.write(CalendarPrefs.COOKIE.scope, normalized.scope);
        }
    }

    static saveFavoritesOnly(favorites) {
        const serialized = CalendarPrefs.serializeFavorites(
            favorites instanceof Set ? favorites : CalendarPrefs.parseFavorites(favorites || '')
        );

        if (serialized === '') {
            CalendarPrefs.delete(CalendarPrefs.COOKIE.favorites);
            CalendarPrefs.delete(CalendarPrefs.COOKIE.engaged);
        } else {
            CalendarPrefs.write(CalendarPrefs.COOKIE.favorites, serialized);
            CalendarPrefs.saveEngaged(true);
        }
    }

    static saveRecent(recent) {
        const serialized = CalendarPrefs.serializeRecent(recent);

        if (serialized === '') {
            CalendarPrefs.delete(CalendarPrefs.COOKIE.recent);
        } else {
            CalendarPrefs.write(CalendarPrefs.COOKIE.recent, serialized);
        }
    }

    static addRecent(slug) {
        if (!slug) {
            return;
        }

        const recent = CalendarPrefs.parseRecent(CalendarPrefs.read(CalendarPrefs.COOKIE.recent));
        const next = [slug, ...recent.filter((entry) => entry !== slug)].slice(0, CalendarPrefs.MAX_RECENT);
        CalendarPrefs.saveRecent(next);
    }

    static saveEngaged(active = true) {
        if (active) {
            CalendarPrefs.write(CalendarPrefs.COOKIE.engaged, '1');
        } else {
            CalendarPrefs.delete(CalendarPrefs.COOKIE.engaged);
        }
    }

    static buildUrlFromSnapshot(snapshot, dateYmd) {
        const normalized = CalendarPrefs.snapshotFromValues(snapshot);
        const filterParams = new URLSearchParams();

        if (normalized.tags.length > 0) {
            filterParams.set('tags', normalized.tags.sort().join(','));
        }

        if (normalized.find.length >= 2) {
            filterParams.set('find', normalized.find);
        }

        if (normalized.scope === 'favorites') {
            filterParams.set('scope', 'favorites');
        }

        return CalendarPrefs.buildIndexHref(dateYmd, normalized.view, filterParams);
    }

    static currentDateParam() {
        const params = new URLSearchParams(window.location.search);
        const date = params.get('date');

        return date && /^\d{8}$/.test(date) ? date : '';
    }
}
