import { ref } from 'vue';

// One palette for the whole app: the navbar has a trigger on desktop and on
// mobile, but only AuthenticatedLayout renders the dialog (and its shortcut).
export const searchOpen = ref(false);

export const openSearch = () => {
    searchOpen.value = true;
};
