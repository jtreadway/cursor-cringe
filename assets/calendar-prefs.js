class CalendarPrefs {
    static STORAGE = {
        favorites: 'cringe-favorites',
        filters: 'cringe-filters',
        recent: 'cringe-recent',
    };

    static LEGACY_COOKIE = {
        favorites: 'cringe_favorites',
        recent: 'cringe_recent',
        tags: 'cringe_tags',
        find: 'cringe_find',
        view: 'cringe_view',
        scope: 'cringe_scope',
        engaged: 'cringe_favorites_engaged',
    };

    static MAX_RECENT = 5;
    static SCHEMA_VERSION = 1;

    static readCookie(name) {
        const match = document.cookie.match(
            new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)')
        );

        return match ? decodeURIComponent(match[1]) : '';
    }

    static deleteCookie(name) {
        document.cookie = `${name}=; Path=/; Max-Age=0; SameSite=Lax`;
    }

    static storageAvailable() {
        try {
            const key = '__cringe_storage_test__';
            window.localStorage.setItem(key, '1');
            window.localStorage.removeItem(key);

            return true;
        } catch (error) {
            return false;
        }
    }

    static readStorageJson(key, fallback) {
        if (!CalendarPrefs.storageAvailable()) {
            return fallback;
        }

        try {
            const raw = window.localStorage.getItem(key);
            if (!raw) {
                return fallback;
            }

            const parsed = JSON.parse(raw);

            return parsed && typeof parsed === 'object' ? parsed : fallback;
        } catch (error) {
            return fallback;
        }
    }

    static writeStorageJson(key, value) {
        if (!CalendarPrefs.storageAvailable()) {
            return;
        }

        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {
            // Ignore quota / privacy errors.
        }
    }

    static removeStorage(key) {
        if (!CalendarPrefs.storageAvailable()) {
            return;
        }

        try {
            window.localStorage.removeItem(key);
        } catch (error) {
            // Ignore storage errors.
        }
    }

    static emptyFavoritesData() {
        return {
            v: CalendarPrefs.SCHEMA_VERSION,
            venues: [],
        };
    }

    static emptyPersonalScope() {
        return {
            venues: false,
        };
    }

    static normalizePersonalScope(raw) {
        const scope = raw && typeof raw === 'object' ? raw : {};

        return {
            venues: scope.venues === true,
        };
    }

    static personalScopeFromLegacyScope(scope) {
        return {
            venues: scope === 'favorites',
        };
    }

    static personalScopeVenuesActive(snapshot) {
        if (!snapshot) {
            return false;
        }

        if (snapshot.personalScope) {
            return snapshot.personalScope.venues === true;
        }

        return snapshot.scope === 'favorites';
    }

    static personalScopeAnyActive(personalScope) {
        return CalendarPrefs.normalizePersonalScope(personalScope).venues;
    }

    static normalizeFavoritesData(raw) {
        const data = raw && typeof raw === 'object' ? raw : {};

        return {
            v: CalendarPrefs.SCHEMA_VERSION,
            venues: Array.isArray(data.venues)
                ? data.venues.map((slug) => String(slug).trim()).filter(Boolean)
                : [],
        };
    }

    static normalizeFiltersSnapshot(raw) {
        const data = raw && typeof raw === 'object' ? raw : {};
        const personalScope = data.personalScope
            ? CalendarPrefs.normalizePersonalScope(data.personalScope)
            : CalendarPrefs.personalScopeFromLegacyScope(data.scope);

        return {
            v: CalendarPrefs.SCHEMA_VERSION,
            view: CalendarPrefs.normalizeView(data.view),
            tags: CalendarPrefs.parseTags(Array.isArray(data.tags) ? data.tags.join(',') : data.tags || ''),
            find: String(data.find ?? '').trim(),
            personalScope,
        };
    }

    static loadFavoritesData() {
        return CalendarPrefs.normalizeFavoritesData(
            CalendarPrefs.readStorageJson(CalendarPrefs.STORAGE.favorites, CalendarPrefs.emptyFavoritesData())
        );
    }

    static saveFavoritesData(data) {
        const normalized = CalendarPrefs.normalizeFavoritesData(data);

        if (normalized.venues.length === 0) {
            CalendarPrefs.removeStorage(CalendarPrefs.STORAGE.favorites);
            return;
        }

        CalendarPrefs.writeStorageJson(CalendarPrefs.STORAGE.favorites, normalized);
    }

    static loadVenueFavoritesSet() {
        const live = VenueFavorites.getInstance()?.favorites;
        if (live instanceof Set) {
            return new Set(live);
        }

        return new Set(CalendarPrefs.loadFavoritesData().venues);
    }

    static parseFavorites(raw) {
        return new Set(
            String(raw ?? '')
                .split(',')
                .map((slug) => slug.trim())
                .filter(Boolean)
        );
    }

    static loadFavoritesSet() {
        return CalendarPrefs.loadVenueFavoritesSet();
    }

    static serializeVenueFavorites(favorites) {
        const list = favorites instanceof Set ? Array.from(favorites) : Array.from(favorites || []);

        return list.map((slug) => String(slug).trim()).filter(Boolean).sort().join(',');
    }

    static serializeFavorites(favorites) {
        return CalendarPrefs.serializeVenueFavorites(favorites);
    }

    static saveVenueFavorites(favorites) {
        CalendarPrefs.saveFavoritesData({
            v: CalendarPrefs.SCHEMA_VERSION,
            venues: Array.from(
                favorites instanceof Set ? favorites : CalendarPrefs.parseFavorites(favorites || '')
            ).sort(),
        });
    }

    static saveFavoritesOnly(favorites) {
        CalendarPrefs.saveVenueFavorites(favorites);
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

    static parseRecent(raw) {
        return String(raw ?? '')
            .split(',')
            .map((slug) => slug.trim())
            .filter(Boolean)
            .slice(0, CalendarPrefs.MAX_RECENT);
    }

    static serializeRecent(recent) {
        return recent.slice(0, CalendarPrefs.MAX_RECENT).join(',');
    }

    static loadRecent() {
        if (!CalendarPrefs.storageAvailable()) {
            return [];
        }

        try {
            const raw = window.localStorage.getItem(CalendarPrefs.STORAGE.recent);

            return CalendarPrefs.parseRecent(raw || '');
        } catch (error) {
            return [];
        }
    }

    static saveRecent(recent) {
        const serialized = CalendarPrefs.serializeRecent(recent);

        if (serialized === '') {
            CalendarPrefs.removeStorage(CalendarPrefs.STORAGE.recent);
            return;
        }

        if (!CalendarPrefs.storageAvailable()) {
            return;
        }

        try {
            window.localStorage.setItem(CalendarPrefs.STORAGE.recent, serialized);
        } catch (error) {
            // Ignore storage errors.
        }
    }

    static addRecent(slug) {
        if (!slug) {
            return;
        }

        const recent = CalendarPrefs.loadRecent();
        const next = [slug, ...recent.filter((entry) => entry !== slug)].slice(0, CalendarPrefs.MAX_RECENT);
        CalendarPrefs.saveRecent(next);
    }

    static parseTags(raw) {
        return String(raw ?? '')
            .split(',')
            .map((tag) => tag.trim().toLowerCase())
            .filter(Boolean);
    }

    static normalizeView(raw) {
        if (raw === 'week') {
            return 'week';
        }

        if (raw === '4week') {
            return '4week';
        }

        return 'day';
    }

    static normalizeScope(raw) {
        return raw === 'favorites' ? 'favorites' : 'all';
    }

    static loadSavedFilters() {
        const stored = CalendarPrefs.readStorageJson(CalendarPrefs.STORAGE.filters, null);

        if (!stored) {
            return CalendarPrefs.normalizeFiltersSnapshot({});
        }

        return CalendarPrefs.normalizeFiltersSnapshot(stored);
    }

    static filtersAreEmpty(snapshot) {
        const normalized = CalendarPrefs.normalizeFiltersSnapshot(snapshot);

        return (
            normalized.view === 'day'
            && normalized.tags.length === 0
            && normalized.find.length < 2
            && !CalendarPrefs.personalScopeAnyActive(normalized.personalScope)
        );
    }

    static saveFiltersOnly(snapshot) {
        const normalized = CalendarPrefs.normalizeFiltersSnapshot(snapshot);

        if (CalendarPrefs.filtersAreEmpty(normalized)) {
            CalendarPrefs.removeStorage(CalendarPrefs.STORAGE.filters);
            return;
        }

        CalendarPrefs.writeStorageJson(CalendarPrefs.STORAGE.filters, normalized);
    }

    static filterSnapshotFromValues({ view, tags, find, scope, personalScope }) {
        return CalendarPrefs.normalizeFiltersSnapshot({
            view,
            tags,
            find,
            scope,
            personalScope,
        });
    }

    static snapshotFiltersFromLocation() {
        const params = new URLSearchParams(window.location.search);
        const saved = CalendarPrefs.loadSavedFilters();
        const personalScope = { ...saved.personalScope };

        if (params.get('scope') === 'favorites') {
            personalScope.venues = true;
        } else if (params.has('scope')) {
            personalScope.venues = false;
        }

        return CalendarPrefs.filterSnapshotFromValues({
            view: params.get('view') || saved.view,
            tags: params.has('tags') ? params.get('tags') || '' : saved.tags.join(','),
            find: params.has('find') ? params.get('find') || '' : saved.find,
            personalScope,
        });
    }

    static filtersSnapshotsEqual(a, b) {
        const left = CalendarPrefs.normalizeFiltersSnapshot(a);
        const right = CalendarPrefs.normalizeFiltersSnapshot(b);

        if (left.view !== right.view || left.find !== right.find) {
            return false;
        }

        if (left.tags.length !== right.tags.length) {
            return false;
        }

        const leftTags = [...left.tags].sort();
        const rightTags = [...right.tags].sort();

        if (!leftTags.every((tag, index) => tag === rightTags[index])) {
            return false;
        }

        return (
            left.personalScope.venues === right.personalScope.venues
        );
    }

    static snapshotFromValues({ view, tags, find, favorites, scope, personalScope }) {
        const filters = CalendarPrefs.filterSnapshotFromValues({ view, tags, find, scope, personalScope });

        return {
            view: filters.view,
            tags: filters.tags,
            find: filters.find,
            personalScope: filters.personalScope,
            scope: CalendarPrefs.personalScopeVenuesActive(filters) ? 'favorites' : 'all',
            favorites: CalendarPrefs.serializeVenueFavorites(
                favorites instanceof Set ? favorites : CalendarPrefs.parseFavorites(favorites || '')
            ),
        };
    }

    static loadSaved() {
        const filters = CalendarPrefs.loadSavedFilters();
        const favorites = CalendarPrefs.loadFavoritesData();

        return CalendarPrefs.snapshotFromValues({
            view: filters.view,
            tags: filters.tags,
            find: filters.find,
            personalScope: filters.personalScope,
            favorites: favorites.venues,
        });
    }

    static hasSavedFilters(saved = CalendarPrefs.loadSavedFilters()) {
        const normalized = CalendarPrefs.normalizeFiltersSnapshot(saved);

        return (
            normalized.view !== 'day'
            || normalized.tags.length > 0
            || normalized.find.length >= 2
            || CalendarPrefs.personalScopeAnyActive(normalized.personalScope)
        );
    }

    static hasSavedPreferences(saved = CalendarPrefs.loadSaved()) {
        return CalendarPrefs.hasSavedFilters(saved) || saved.favorites !== '';
    }

    static preferencesExplicitInUrl(params = new URLSearchParams(window.location.search)) {
        if (params.get('prefs') === 'neutral') {
            return true;
        }

        return params.has('tags') || params.has('find') || params.has('scope') || params.has('view');
    }

    static resolvePersonalScopeForVisit(saved, venueFavorites) {
        const personalScope = CalendarPrefs.normalizePersonalScope(saved.personalScope);

        if (CalendarPrefs.personalScopeAnyActive(personalScope)) {
            return personalScope;
        }

        if (venueFavorites.size > 0) {
            return { venues: true };
        }

        return personalScope;
    }

    static shouldHydratePage() {
        const path = window.location.pathname;

        return path.endsWith('/index.php') || path.endsWith('index.php') || path.endsWith('venue.php');
    }

    static hydrateUrlFromStorageIfNeeded() {
        if (!CalendarPrefs.shouldHydratePage()) {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        if (CalendarPrefs.preferencesExplicitInUrl(params)) {
            return;
        }

        const saved = CalendarPrefs.loadSavedFilters();
        const venueFavorites = CalendarPrefs.loadVenueFavoritesSet();
        const personalScope = CalendarPrefs.resolvePersonalScopeForVisit(saved, venueFavorites);
        const url = new URL(window.location.href);
        let changed = false;

        if (saved.view !== 'day') {
            url.searchParams.set('view', saved.view);
            changed = true;
        }

        if (saved.tags.length > 0) {
            url.searchParams.set('tags', saved.tags.sort().join(','));
            changed = true;
        }

        if (saved.find.length >= 2) {
            url.searchParams.set('find', saved.find);
            changed = true;
        }

        if (personalScope.venues) {
            url.searchParams.set('scope', 'favorites');
            changed = true;
        }

        if (!changed) {
            return;
        }

        window.location.replace(url.toString());
    }

    static migrateFromCookies() {
        if (CalendarPrefs._migrationDone) {
            return;
        }

        CalendarPrefs._migrationDone = true;

        if (!CalendarPrefs.storageAvailable()) {
            return;
        }

        try {
            const legacyFavorites = CalendarPrefs.readCookie(CalendarPrefs.LEGACY_COOKIE.favorites);
            const hasFavoritesStorage = window.localStorage.getItem(CalendarPrefs.STORAGE.favorites);

            if (!hasFavoritesStorage && legacyFavorites !== '') {
                CalendarPrefs.saveFavoritesData({
                    v: CalendarPrefs.SCHEMA_VERSION,
                    venues: Array.from(CalendarPrefs.parseFavorites(legacyFavorites)),
                });
            }

            const hasFiltersStorage = window.localStorage.getItem(CalendarPrefs.STORAGE.filters);
            if (!hasFiltersStorage) {
                const legacyView = CalendarPrefs.readCookie(CalendarPrefs.LEGACY_COOKIE.view);
                const legacyTags = CalendarPrefs.readCookie(CalendarPrefs.LEGACY_COOKIE.tags);
                const legacyFind = CalendarPrefs.readCookie(CalendarPrefs.LEGACY_COOKIE.find);
                const legacyScope = CalendarPrefs.readCookie(CalendarPrefs.LEGACY_COOKIE.scope);
                const hasLegacyFilters =
                    legacyView !== '' || legacyTags !== '' || legacyFind !== '' || legacyScope !== '';

                if (hasLegacyFilters) {
                    CalendarPrefs.saveFiltersOnly({
                        view: legacyView || 'day',
                        tags: legacyTags,
                        find: legacyFind,
                        scope: legacyScope === 'favorites' ? 'favorites' : 'all',
                    });
                }
            }

            const legacyRecent = CalendarPrefs.readCookie(CalendarPrefs.LEGACY_COOKIE.recent);
            if (legacyRecent !== '' && !window.localStorage.getItem(CalendarPrefs.STORAGE.recent)) {
                CalendarPrefs.saveRecent(CalendarPrefs.parseRecent(legacyRecent));
            }
        } catch (error) {
            // Ignore migration errors.
        }

        Object.values(CalendarPrefs.LEGACY_COOKIE).forEach((name) => {
            CalendarPrefs.deleteCookie(name);
        });
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
        const left = CalendarPrefs.snapshotFromValues(a);
        const right = CalendarPrefs.snapshotFromValues(b);

        if (left.view !== right.view || left.find !== right.find || left.favorites !== right.favorites) {
            return false;
        }

        if (!CalendarPrefs.filtersSnapshotsEqual(left, right)) {
            return false;
        }

        return true;
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

        if (params.get('filter') === 'open') {
            out.set('filter', 'open');
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

        return `index.php?${out.toString()}`;
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
        CalendarPrefs.saveVenueFavorites(CalendarPrefs.parseFavorites(normalized.favorites));
        CalendarPrefs.saveFiltersOnly(normalized);
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

        if (CalendarPrefs.personalScopeVenuesActive(normalized)) {
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

CalendarPrefs.migrateFromCookies();
CalendarPrefs.hydrateUrlFromStorageIfNeeded();
