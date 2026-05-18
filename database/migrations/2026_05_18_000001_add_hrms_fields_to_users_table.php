<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'tanggal_masuk')) {
                $table->date('tanggal_masuk')->nullable()->after('is_active');
            }

            if (!Schema::hasColumn('users', 'tanggal_keluar')) {
                $table->date('tanggal_keluar')->nullable()->after('tanggal_masuk');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'tanggal_masuk')) {
                $table->dropColumn('tanggal_masuk');
            }

            if (Schema::hasColumn('users', 'tanggal_keluar')) {
                $table->dropColumn('tanggal_keluar');
            }
        });
    }
};
