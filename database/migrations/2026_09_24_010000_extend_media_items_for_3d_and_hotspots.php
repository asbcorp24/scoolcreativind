<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('media_items','hotspots_json')) {
            Schema::table('media_items', function (Blueprint $table) {
                $table->longText('hotspots_json')->nullable()->after('caption');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE media_items MODIFY type VARCHAR(32) NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('media_items','hotspots_json')) {
            Schema::table('media_items', function (Blueprint $table) {
                $table->dropColumn('hotspots_json');
            });
        }
    }
};
