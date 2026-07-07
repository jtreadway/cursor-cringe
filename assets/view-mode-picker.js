class ViewModePicker {
    static init() {
        document.querySelectorAll('[data-view-mode-picker]').forEach((picker) => {
            ViewModePicker.bindPicker(picker);
        });
    }

    static bindPicker(picker) {
        const trigger = picker.querySelector('[data-view-mode-picker-trigger]');
        const panel = picker.querySelector('[data-view-mode-picker-panel]');
        const calendar = picker.querySelector('[data-view-mode-picker-calendar]');
        const viewLinks = Array.from(picker.querySelectorAll('.view-mode-picker__view'));
        if (!trigger || !panel || !calendar) {
            return;
        }

        const prevBtn = calendar.querySelector('[data-cal-prev]');
        const nextBtn = calendar.querySelector('[data-cal-next]');

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (picker.classList.contains('is-open')) {
                ViewModePicker.collapse(picker, trigger, panel);
                return;
            }

            ViewModePicker.expand(picker, trigger, panel);
        });

        panel.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        prevBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            ViewModePicker.shiftMonth(picker, -1);
        });

        nextBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            ViewModePicker.shiftMonth(picker, 1);
        });

        viewLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                const dateYmd = ViewModePicker.selectedYmd(picker);
                if (!dateYmd) {
                    return;
                }

                ViewModePicker.navigateFromPicker(
                    picker,
                    dateYmd,
                    link.dataset.navView || 'day',
                    link.dataset.navVenue || ''
                );
            });
        });

        document.addEventListener('click', (event) => {
            if (!picker.classList.contains('is-open') || picker.contains(event.target)) {
                return;
            }

            ViewModePicker.collapse(picker, trigger, panel);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && picker.classList.contains('is-open')) {
                ViewModePicker.collapse(picker, trigger, panel);
                trigger.focus();
            }
        });
    }

    static expand(picker, trigger, panel) {
        document.querySelectorAll('[data-view-mode-picker].is-open').forEach((other) => {
            if (other === picker) {
                return;
            }

            const otherTrigger = other.querySelector('[data-view-mode-picker-trigger]');
            const otherPanel = other.querySelector('[data-view-mode-picker-panel]');
            if (otherTrigger && otherPanel) {
                ViewModePicker.collapse(other, otherTrigger, otherPanel);
            }
        });

        ViewModePicker.syncSelected(picker);
        ViewModePicker.initCalendarMonth(picker);
        ViewModePicker.renderCalendar(picker);
        picker.classList.add('is-open');
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
    }

    static collapse(picker, trigger, panel) {
        picker.classList.remove('is-open');
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }

    static activeView(picker) {
        const active = picker.querySelector('.view-mode-picker__view.is-active');
        return active?.dataset.navView || 'day';
    }

    static selectedYmd(picker) {
        const params = new URLSearchParams(window.location.search);
        return params.get('date') || picker.dataset.selectedYmd || document.body.dataset.selectedDate || '';
    }

    static syncSelected(picker) {
        const ymd = ViewModePicker.selectedYmd(picker);
        if (!ymd) {
            return;
        }

        picker.dataset.selectedYmd = ymd;
        picker.querySelectorAll('.view-mode-picker__view').forEach((link) => {
            link.dataset.navDate = ymd;
        });
    }

    static initCalendarMonth(picker) {
        const ymd = ViewModePicker.selectedYmd(picker);
        const date = ymd ? ViewModePicker.ymdToDate(ymd) : new Date();

        picker.dataset.calYear = String(date.getFullYear());
        picker.dataset.calMonth = String(date.getMonth());
    }

    static shiftMonth(picker, delta) {
        const year = Number.parseInt(picker.dataset.calYear || '0', 10);
        const month = Number.parseInt(picker.dataset.calMonth || '0', 10);
        const date = new Date(year, month + delta, 1);

        picker.dataset.calYear = String(date.getFullYear());
        picker.dataset.calMonth = String(date.getMonth());
        ViewModePicker.renderCalendar(picker);
    }

    static renderCalendar(picker) {
        const calendar = picker.querySelector('[data-view-mode-picker-calendar]');
        const grid = calendar?.querySelector('[data-cal-grid]');
        const label = calendar?.querySelector('[data-cal-label]');
        if (!grid || !label) {
            return;
        }

        const year = Number.parseInt(picker.dataset.calYear || '0', 10);
        const month = Number.parseInt(picker.dataset.calMonth || '0', 10);
        const selectedYmd = ViewModePicker.selectedYmd(picker);
        const todayYmd = picker.dataset.todayYmd || '';

        label.textContent = new Date(year, month, 1).toLocaleDateString('en-US', {
            month: 'long',
            year: 'numeric',
        });

        grid.replaceChildren();

        const firstOfMonth = new Date(year, month, 1);
        const startOffset = (firstOfMonth.getDay() + 6) % 7;
        const cursor = new Date(year, month, 1 - startOffset);

        for (let i = 0; i < 42; i += 1) {
            const dayDate = new Date(cursor);
            cursor.setDate(cursor.getDate() + 1);

            const ymd = ViewModePicker.formatYmd(dayDate);
            const isOutside = dayDate.getMonth() !== month;
            const isSelected = ymd === selectedYmd;
            const isToday = todayYmd !== '' && ymd === todayYmd;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'view-mode-picker__cal-day';
            button.textContent = String(dayDate.getDate());
            button.setAttribute('role', 'gridcell');
            button.setAttribute('aria-label', dayDate.toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric',
            }));

            if (isOutside) {
                button.classList.add('is-outside');
            }

            if (isSelected) {
                button.classList.add('is-selected');
                button.setAttribute('aria-selected', 'true');
            }

            if (isToday) {
                button.classList.add('is-today');
            }

            button.addEventListener('click', (event) => {
                event.preventDefault();
                if (ymd === selectedYmd) {
                    ViewModePicker.collapse(
                        picker,
                        picker.querySelector('[data-view-mode-picker-trigger]'),
                        picker.querySelector('[data-view-mode-picker-panel]')
                    );
                    return;
                }

                ViewModePicker.navigateFromPicker(picker, ymd, ViewModePicker.activeView(picker));
            });

            grid.appendChild(button);
        }
    }

    static ymdToDate(ymd) {
        if (!/^\d{8}$/.test(ymd || '')) {
            return new Date();
        }

        return new Date(
            Number.parseInt(ymd.slice(0, 4), 10),
            Number.parseInt(ymd.slice(4, 6), 10) - 1,
            Number.parseInt(ymd.slice(6, 8), 10)
        );
    }

    static formatYmd(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}${month}${day}`;
    }

    static navigateFromPicker(picker, dateYmd, view, venue = '') {
        const params = new URLSearchParams(window.location.search);
        const venueSlug = venue || (view === '4week' ? picker.dataset.viewModePickerVenue || '' : '');
        const href = venueSlug !== ''
            ? CalendarPrefs.buildVenueHref(venueSlug, dateYmd, params)
            : CalendarPrefs.buildIndexHref(dateYmd, view, params);

        window.location.assign(href);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    ViewModePicker.init();
});
