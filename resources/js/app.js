import Alpine from 'alpinejs';
import { mountCharts } from './charts';

window.Alpine = Alpine;

/**
 * Bulk selection for the admin QR table: "tick 5 of these 100 codes and assign
 * them to a customer". Kept here rather than inline so the table markup stays
 * readable.
 */
Alpine.data('bulkSelect', () => ({
    selected: [],
    action: '',
    assignTo: '',

    get count() {
        return this.selected.length;
    },

    get allSelected() {
        const boxes = this.$refs.rows?.querySelectorAll('[data-row-id]') ?? [];
        return boxes.length > 0 && this.selected.length === boxes.length;
    },

    toggleAll(event) {
        const ids = Array.from(this.$refs.rows?.querySelectorAll('[data-row-id]') ?? [])
            .map((input) => input.value);

        this.selected = event.target.checked ? ids : [];
    },

    clear() {
        this.selected = [];
        this.action = '';
    },

    /** Destructive actions get a confirmation; everything else submits straight away. */
    confirmSubmit(event) {
        if (this.action === 'delete' && !window.confirm(
            `Delete ${this.count} QR code(s)? Anyone scanning them will see a "not found" page.`
        )) {
            event.preventDefault();
        }
    },
}));

/** Copy-to-clipboard with a short confirmation, used on every short link. */
Alpine.data('copyable', (value) => ({
    copied: false,

    async copy() {
        try {
            await navigator.clipboard.writeText(value);
        } catch {
            // Clipboard API needs a secure context; fall back to a hidden input.
            const field = document.createElement('textarea');
            field.value = value;
            field.setAttribute('readonly', '');
            field.style.position = 'fixed';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();
            document.execCommand('copy');
            document.body.removeChild(field);
        }

        this.copied = true;
        setTimeout(() => (this.copied = false), 1800);
    },
}));

Alpine.start();

document.addEventListener('DOMContentLoaded', () => mountCharts());
