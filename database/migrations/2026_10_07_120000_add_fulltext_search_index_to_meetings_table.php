<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FULLTEXT index for Meeting::scopeSearch(), replacing LIKE '%...%' scans
 * over the long agenda/transcript/summary TEXT columns. MySQL/MariaDB only:
 * SQLite (tests) has no FULLTEXT and the scope falls back to LIKE there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->supportsFullText()) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            $table->fullText(['agenda', 'transcript', 'summary'], 'meetings_search_fulltext');
        });
    }

    public function down(): void
    {
        if (! $this->supportsFullText()) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropFullText('meetings_search_fulltext');
        });
    }

    private function supportsFullText(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
