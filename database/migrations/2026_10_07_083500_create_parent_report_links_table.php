<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Таблица создаётся предыдущей миграцией
        // 2026_10_07_083000_create_parent_report_links_table.php.
        // Эта миграция оставлена только для совместимости с уже развернутыми
        // копиями проекта и не должна повторно создавать таблицу.
        if (Schema::hasTable('parent_report_links')) {
            return;
        }
    }

    public function down(): void
    {
        // Ничего не удаляем: таблицей владеет миграция 083000.
    }
};
