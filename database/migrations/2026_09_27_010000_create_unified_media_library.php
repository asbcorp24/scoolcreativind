<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_items', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('type',32);
            $table->string('title')->nullable();
            $table->text('url');
            $table->string('thumbnail')->nullable();
            $table->text('caption')->nullable();
            $table->longText('hotspots_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['attachable_type','attachable_id'],'media_library_attachable_idx');
            $table->index(['type','is_visible']);
            $table->index('is_featured');
        });

        if (Schema::hasTable('media_items')) {
            $now=now();
            DB::table('media_items')->orderBy('id')->chunkById(200,function($items) use ($now){
                $rows=[];
                foreach($items as $item){
                    $rows[]=[
                        'attachable_type'=>'App\\Models\\Studio',
                        'attachable_id'=>$item->studio_id,
                        'type'=>$item->type,
                        'title'=>$item->title,
                        'url'=>$item->url,
                        'thumbnail'=>$item->thumbnail,
                        'caption'=>$item->caption,
                        'hotspots_json'=>$item->hotspots_json,
                        'sort_order'=>$item->sort_order ?? 0,
                        'is_visible'=>property_exists($item,'is_visible') ? (bool)$item->is_visible : true,
                        'is_featured'=>property_exists($item,'is_featured') ? (bool)$item->is_featured : false,
                        'created_at'=>$item->created_at ?? $now,
                        'updated_at'=>$item->updated_at ?? $now,
                    ];
                }
                if($rows)DB::table('media_library_items')->insert($rows);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library_items');
    }
};
