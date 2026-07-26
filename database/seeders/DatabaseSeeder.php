<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Unit;
use App\Models\User;
use App\Models\Meeting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
         Unit::create([
            'name' => 'Pusbin',
        ]);

          Unit::create([
            'name' => 'SDM',
        ]);

         User::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
            'unit_id' => 1,
            'phone_number' => '081572398890',
        ]);

        User::create([
            'name' => 'Admin Unit',
            'email' => 'unit1@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'unit_id' => 1,
            'phone_number' => '081572398890',
        ]);

         User::create([
            'name' => 'Admin Unit',
            'email' => 'unit2@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'unit_id' => 2,
            'phone_number' => '081572398890',
        ]);

        User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'unit_id' => 1,
            'phone_number' => '081572398890',
        ]);

        User::create([
            'name' => 'Citra Lestari',
            'email' => 'citra@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'unit_id' => 2,
            'phone_number' => '081572398890',
        ]);

         Meeting::create([
            'id' => 1,
            'title' => 'Rapat Kinerja Q3',
            'unit_id' => 1,
            'date' => '2025-10-24 10:00:00',
            'agenda' => 'Membahas pencapaian kuartal ketiga dan perencanaan kuartal keempat.',
            'attendees' => 'Budi Santoso, Citra Lestari, Tim Marketing',
            'status' => 'Selesai Diproses',
            'user_id' => 2,
            'transcript' => "Budi: Selamat pagi semua. Mari kita mulai rapat kinerja Q3. Citra, bisa dimulai dari update tim marketing?\nCitra: Tentu, Pak Budi. Untuk Q3, campaign digital kita berhasil melampaui target sebesar 20%...",
            'summary' => "Poin Penting:\n- Campaign digital marketing Q3 melampaui target 20%.\n- Perlu fokus pada retensi pelanggan di Q4.\n\nAction Items:\n- Tim Marketing menyiapkan proposal budget untuk campaign Q4 (PIC: Citra).\n- Tim Sales melakukan analisis customer churn rate (PIC: Budi)."
        ]);

        Meeting::create([
            'id' => 2,
               'unit_id' => 1,
            'title' => 'Brainstorming Fitur Baru',
            'date' => '2025-10-28 14:00:00',
            'agenda' => 'Diskusi ide untuk fitur aplikasi mobile selanjutnya.',
            'attendees' => 'Budi Santoso, Tim Produk',
            'status' => 'Dijadwalkan',
            'user_id' => 2,
            'transcript' => null,
            'summary' => null
        ]);

        Meeting::create([
            'id' => 3,
               'unit_id' => 2,
            'title' => 'Evaluasi Proyek "Alpha"',
            'date' => '2025-10-20 09:00:00',
            'agenda' => 'Post-mortem dan evaluasi keberhasilan proyek Alpha.',
            'attendees' => 'Citra Lestari, Tim Developer',
            'status' => 'Selesai Diproses',
            'user_id' => 3,
            'transcript' => "Citra: Oke tim, mari kita bahas proyek Alpha. Secara keseluruhan, proyek ini selesai tepat waktu. Apa saja kendala yang kita hadapi? \nDev1: Integrasi dengan API pihak ketiga sempat memakan waktu lebih lama dari estimasi.",
            'summary' => "Poin Penting:\n- Proyek Alpha selesai sesuai jadwal.\n- Terdapat kendala pada integrasi API eksternal.\n\nAction Items:\n- Buat dokumentasi best practice untuk integrasi API (PIC: Tim Developer)."
        ]);
    }
}
