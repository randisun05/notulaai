import { ChatBubbleLeftRightIcon, ClipboardDocumentListIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

// Fixed order and labels of the groups returned by SearchController.
export const SEARCH_GROUPS = [
    { key: 'meetings', label: 'Rapat', icon: DocumentTextIcon },
    { key: 'tasks', label: 'Task', icon: ClipboardDocumentListIcon },
    { key: 'comments', label: 'Komentar forum', icon: ChatBubbleLeftRightIcon },
];

export const SEARCH_MIN_LENGTH = 2;
