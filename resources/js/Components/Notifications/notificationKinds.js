import {
    AtSymbolIcon,
    BellAlertIcon,
    CheckCircleIcon,
    ClipboardDocumentListIcon,
    ClockIcon,
    DocumentCheckIcon,
    ExclamationTriangleIcon,
    InboxArrowDownIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';

// Icon + tint per InAppNotifier::KINDS. Tints are decorative: the title text
// always says what happened, so meaning never rests on colour alone.
export const NOTIFICATION_KINDS = {
    mention: { icon: AtSymbolIcon, tint: 'bg-brand-50 text-brand-600' },
    task_assigned: { icon: ClipboardDocumentListIcon, tint: 'bg-brand-50 text-brand-600' },
    task_review_requested: { icon: InboxArrowDownIcon, tint: 'bg-yellow-50 text-yellow-700' },
    task_approved: { icon: CheckCircleIcon, tint: 'bg-green-50 text-green-700' },
    task_rejected: { icon: XCircleIcon, tint: 'bg-red-50 text-red-700' },
    task_deadline: { icon: ClockIcon, tint: 'bg-yellow-50 text-yellow-700' },
    task_escalated: { icon: ExclamationTriangleIcon, tint: 'bg-red-50 text-red-700' },
    meeting_processed: { icon: DocumentCheckIcon, tint: 'bg-green-50 text-green-700' },
    meeting_failed: { icon: ExclamationTriangleIcon, tint: 'bg-red-50 text-red-700' },
};

export const kindMeta = (kind) => NOTIFICATION_KINDS[kind] || { icon: BellAlertIcon, tint: 'bg-gray-100 text-gray-600' };

const rtf = new Intl.RelativeTimeFormat('id-ID', { numeric: 'auto' });

/** "5 menit yang lalu", "kemarin", … ; falls back to a date after a week. */
export function timeAgo(iso) {
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const abs = Math.abs(seconds);
    if (abs < 60) return 'baru saja';
    if (abs < 3600) return rtf.format(Math.round(seconds / 60), 'minute');
    if (abs < 86400) return rtf.format(Math.round(seconds / 3600), 'hour');
    if (abs < 7 * 86400) return rtf.format(Math.round(seconds / 86400), 'day');
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}
