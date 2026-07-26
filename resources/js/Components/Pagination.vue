<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <!--
      Komponen pagination sederhana ini hanya akan menampilkan link "Sebelumnya" dan "Berikutnya".
      Laravel mengirimkan array 'links', di mana:
      - links[0] adalah link "Sebelumnya"
      - links[links.length - 1] adalah link "Berikutnya"

      Kita hanya perlu merender kedua link tersebut dan menonaktifkannya jika URL-nya null.
    -->
    <div v-if="links.length > 0" class="flex justify-between items-center w-full">

        <!-- Tombol Kiri (Previous) -->
        <Link
            v-if="links[0].url"
            :href="links[0].url"
            preserve-scroll
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
        >
            &laquo; Sebelumnya
        </Link>
        <!-- Tombol Kiri (Disabled) -->
        <span
            v-else
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-gray-300 rounded-md cursor-not-allowed"
        >
            &laquo; Sebelumnya
        </span>

        <!-- Tombol Kanan (Next) -->
        <Link
            v-if="links[links.length - 1].url"
            :href="links[links.length - 1].url"
            preserve-scroll
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
        >
            Berikutnya &raquo;
        </Link>
        <!-- Tombol Kanan (Disabled) -->
        <span
            v-else
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-gray-300 rounded-md cursor-not-allowed"
        >
            Berikutnya &raquo;
        </span>
    </div>
</template>

