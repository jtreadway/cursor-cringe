class WeekCarousel {
    constructor(root) {
        this.root = root;
        this.viewport = root.querySelector('.week-carousel__viewport');
        this.track = root.querySelector('.week-carousel__track');
        this.panels = Array.from(root.querySelectorAll('.day-panel'));
        this.navButtons = Array.from(document.querySelectorAll('.week-nav [data-day-index]'));
        this.slideCount = this.panels.length;
        this.index = Math.min(
            Math.max(parseInt(root.dataset.startIndex || '0', 10), 0),
            Math.max(this.slideCount - 1, 0)
        );
        this.minSwipe = 50;
        this.dragging = false;
        this.decided = false;
        this.isHorizontal = false;
        this.startX = 0;
        this.startY = 0;
        this.currentX = 0;
        this.activePointerId = null;
        this.resizeObserver = null;
        this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.boundaryPrevDay = root.dataset.boundaryPrevDay || '';
        this.boundaryNextDay = root.dataset.boundaryNextDay || '';
        this.isWeekView = root.dataset.viewMode === 'week';

        this.stripLegacyHash();

        if (this.isWeekView) {
            window.scrollTo(0, 0);
            return;
        }

        this.bindNav();
        this.bindPointer();
        this.bindKeyboard();
        this.bindResize();
        this.goTo(this.index, false);
        window.scrollTo(0, 0);
        window.addEventListener('popstate', () => {
            this.syncFromQuery();
            window.scrollTo(0, 0);
        });

        document.addEventListener('calendar:reflow', () => {
            this.updateHeight();
        });
    }

    stripLegacyHash() {
        if (!window.location.hash) {
            return;
        }

        const url = new URL(window.location.href);
        url.hash = '';
        history.replaceState(null, '', url.toString());
    }

    get width() {
        return this.viewport.clientWidth;
    }

    bindNav() {
        this.navButtons.forEach((link) => {
            link.addEventListener('click', (event) => {
                const target = parseInt(link.dataset.dayIndex || '0', 10);

                if (target === this.index) {
                    event.preventDefault();
                    return;
                }

                event.preventDefault();
                this.goTo(target, true, 'push');
            });
        });
    }

    navigateToDay(dateYmd) {
        if (!dateYmd) {
            return false;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('date', dateYmd);
        url.hash = '';
        window.location.assign(url.toString());

        return true;
    }

    bindKeyboard() {
        this.root.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                this.stepDay(-1);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                this.stepDay(1);
            }
        });
    }

    stepDay(direction) {
        if (direction < 0) {
            if (this.index <= 0) {
                if (this.navigateToDay(this.boundaryPrevDay)) {
                    return;
                }
            }

            this.goTo(this.index - 1);
            return;
        }

        if (this.index >= this.slideCount - 1) {
            if (this.navigateToDay(this.boundaryNextDay)) {
                return;
            }
        }

        this.goTo(this.index + 1);
    }

    bindResize() {
        const update = () => {
            this.goTo(this.index, false);
            this.updateHeight();
        };

        window.addEventListener('resize', update);

        if ('ResizeObserver' in window) {
            this.resizeObserver = new ResizeObserver(() => update());
            this.panels.forEach((panel) => this.resizeObserver.observe(panel));
        }
    }

    bindPointer() {
        this.viewport.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            if (event.target.closest('a, button, [data-venue-favorite], .venue-favorite, [data-filter-favorites-only], .venue-scope-toggle__control')) {
                return;
            }

            if (this.viewport.setPointerCapture) {
                this.viewport.setPointerCapture(event.pointerId);
            }

            this.dragging = true;
            this.decided = false;
            this.isHorizontal = false;
            this.startX = event.pageX;
            this.startY = event.pageY;
            this.currentX = event.pageX;
            this.activePointerId = event.pointerId;
            this.root.classList.add('is-dragging');
            this.track.classList.remove('is-animating');
        });

        this.viewport.addEventListener('pointermove', (event) => {
            if (!this.dragging || event.pointerId !== this.activePointerId) {
                return;
            }

            const deltaX = event.pageX - this.startX;
            const deltaY = event.pageY - this.startY;

            if (!this.decided) {
                if (Math.abs(deltaX) < 8 && Math.abs(deltaY) < 8) {
                    return;
                }

                this.decided = true;
                this.isHorizontal = Math.abs(deltaX) > Math.abs(deltaY);
            }

            if (!this.isHorizontal) {
                return;
            }

            event.preventDefault();
            this.currentX = event.pageX;
            this.setOffset(this.offsetForIndex(this.index) + (this.currentX - this.startX));
        });

        const endPointer = (event) => {
            if (!this.dragging || event.pointerId !== this.activePointerId) {
                return;
            }

            this.dragging = false;
            this.activePointerId = null;
            this.root.classList.remove('is-dragging');

            if (!this.decided || !this.isHorizontal) {
                this.goTo(this.index);
                return;
            }

            const deltaX = event.pageX - this.startX;
            const deltaY = event.pageY - this.startY;

            if (Math.abs(deltaX) < this.minSwipe || Math.abs(deltaX) < Math.abs(deltaY)) {
                this.goTo(this.index);
                return;
            }

            this.stepDay(deltaX < 0 ? 1 : -1);
        };

        this.viewport.addEventListener('pointerup', endPointer);
        this.viewport.addEventListener('pointercancel', endPointer);

        this.viewport.addEventListener('mousemove', (event) => {
            if (event.target.closest('.venue-favorite, [data-venue-favorite], [data-filter-favorites-only], .venue-scope-toggle__control')) {
                this.viewport.style.cursor = 'default';
            } else if (!this.dragging) {
                this.viewport.style.removeProperty('cursor');
            }
        });
    }

    offsetForIndex(index) {
        return -index * this.width;
    }

    setOffset(px) {
        const min = this.offsetForIndex(this.slideCount - 1);
        const max = 0;
        let clamped = px;

        if (clamped > max) {
            clamped = max + (px - max) * 0.35;
        } else if (clamped < min) {
            clamped = min + (px - min) * 0.35;
        }

        this.track.style.transform = `translate3d(${clamped}px, 0, 0)`;
    }

    goTo(index, animate = true, historyMode = 'replace') {
        const nextIndex = Math.min(Math.max(index, 0), this.slideCount - 1);
        this.index = nextIndex;

        if (animate && !this.prefersReducedMotion) {
            this.track.classList.add('is-animating');
            this.viewport.classList.add('is-animating');
        } else {
            this.track.classList.remove('is-animating');
            this.viewport.classList.remove('is-animating');
        }

        this.setOffset(this.offsetForIndex(this.index));
        this.updateNav();
        this.updateUrl(historyMode);
        this.updateHeight();
        this.root.dispatchEvent(new CustomEvent('calendar:daychange', {
            bubbles: true,
            detail: {
                index: this.index,
                panel: this.panels[this.index] ?? null,
            },
        }));
    }

    updateNav() {
        this.navButtons.forEach((button) => {
            const dayIndex = parseInt(button.dataset.dayIndex || '0', 10);
            const isActive = dayIndex === this.index;
            button.classList.toggle('is-active', isActive);
            if (isActive) {
                button.setAttribute('aria-current', 'page');
            } else {
                button.removeAttribute('aria-current');
            }
        });
    }

    updateUrl(historyMode = 'replace') {
        const panel = this.panels[this.index];
        if (!panel || !panel.dataset.date) {
            return;
        }

        const url = new URL(window.location.href);
        if (url.searchParams.get('date') === panel.dataset.date) {
            return;
        }

        url.searchParams.set('date', panel.dataset.date);
        url.hash = '';

        if (historyMode === 'push') {
            history.pushState(null, '', url.toString());
        } else {
            history.replaceState(null, '', url.toString());
        }

        document.title = panel.querySelector('h3')?.textContent?.trim()
            ? `Columbus Live Music — ${panel.querySelector('h3').textContent.trim()}`
            : document.title;
    }

    syncFromQuery() {
        const params = new URLSearchParams(window.location.search);
        const date = params.get('date');
        if (!date) {
            return;
        }

        const targetIndex = this.panels.findIndex((panel) => panel.dataset.date === date);
        if (targetIndex >= 0 && targetIndex !== this.index) {
            this.goTo(targetIndex, !this.prefersReducedMotion, 'replace');
        }
    }

    updateHeight() {
        const panel = this.panels[this.index];
        if (!panel) {
            return;
        }

        this.viewport.style.height = `${panel.offsetHeight}px`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-week-carousel]').forEach((root) => {
        new WeekCarousel(root);
    });
});
