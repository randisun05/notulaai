<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Format notula mengikuti template instansi: judul, Resume berupa poin
     * berurutan (pernyataan + tanggapan per pembicara), foto dokumentasi, dan
     * tanda tangan notulen dengan NIP.
     */
    public function up(): void
    {
        Schema::table('meeting_minutes', function (Blueprint $table) {
            $table->string('title', 500)->nullable()->after('number');
            // [{speaker: ?string, text: string, response: ?string}]
            $table->json('resume')->nullable()->after('agenda');
            // Path foto/tangkapan layar di disk `local`.
            $table->json('documentation')->nullable()->after('closing');
        });

        Schema::table('meeting_minutes', function (Blueprint $table) {
            $table->dropColumn(['opening', 'discussion']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('nip', 30)->nullable()->after('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nip');
        });

        Schema::table('meeting_minutes', function (Blueprint $table) {
            $table->text('opening')->nullable();
            $table->json('discussion')->nullable();
            $table->dropColumn(['title', 'resume', 'documentation']);
        });
    }
};
