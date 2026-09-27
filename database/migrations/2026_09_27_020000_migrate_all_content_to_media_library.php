<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_library_items', function (Blueprint $table) {
            $table->string('file_name')->nullable()->after('url');
            $table->string('mime_type')->nullable()->after('file_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
        });

        $now=now();

        if(Schema::hasTable('news_media')){
            DB::table('news_media')->orderBy('id')->chunkById(200,function($items) use($now){
                $rows=[];
                foreach($items as $item){
                    $rows[]=[
                        'attachable_type'=>'App\\Models\\NewsPost',
                        'attachable_id'=>$item->news_post_id,
                        'type'=>$item->type==='image' ? 'photo' : $item->type,
                        'title'=>$item->title,
                        'url'=>$item->path ?: $item->url,
                        'file_name'=>$item->file_name,
                        'mime_type'=>$item->mime_type,
                        'file_size'=>$item->file_size,
                        'thumbnail'=>null,
                        'caption'=>null,
                        'hotspots_json'=>null,
                        'sort_order'=>$item->sort_order ?? 0,
                        'is_visible'=>true,
                        'is_featured'=>false,
                        'created_at'=>$item->created_at ?? $now,
                        'updated_at'=>$item->updated_at ?? $now,
                    ];
                }
                if($rows)DB::table('media_library_items')->insert($rows);
            });
        }

        if(Schema::hasTable('custom_page_media')){
            DB::table('custom_page_media')->orderBy('id')->chunkById(200,function($items) use($now){
                $rows=[];
                foreach($items as $item){
                    $rows[]=[
                        'attachable_type'=>'App\\Models\\CustomPage',
                        'attachable_id'=>$item->custom_page_id,
                        'type'=>$item->type==='image' ? 'photo' : $item->type,
                        'title'=>$item->title,
                        'url'=>$item->path ?: $item->url,
                        'file_name'=>$item->file_name,
                        'mime_type'=>$item->mime_type,
                        'file_size'=>$item->file_size,
                        'thumbnail'=>null,
                        'caption'=>null,
                        'hotspots_json'=>null,
                        'sort_order'=>$item->sort_order ?? 0,
                        'is_visible'=>true,
                        'is_featured'=>false,
                        'created_at'=>$item->created_at ?? $now,
                        'updated_at'=>$item->updated_at ?? $now,
                    ];
                }
                if($rows)DB::table('media_library_items')->insert($rows);
            });
        }

        if(Schema::hasTable('portfolio_items')){
            DB::table('portfolio_items')->orderBy('id')->chunkById(100,function($items) use($now){
                foreach($items as $item){
                    $base=[
                        'attachable_type'=>'App\\Models\\PortfolioItem',
                        'attachable_id'=>$item->id,
                        'thumbnail'=>null,
                        'caption'=>null,
                        'hotspots_json'=>null,
                        'sort_order'=>0,
                        'is_visible'=>(bool)$item->is_public,
                        'created_at'=>$item->created_at ?? $now,
                        'updated_at'=>$item->updated_at ?? $now,
                    ];

                    if(!empty($item->cover)){
                        DB::table('media_library_items')->insert(array_merge($base,[
                            'type'=>'photo','title'=>'Обложка','url'=>$item->cover,
                            'file_name'=>null,'mime_type'=>null,'file_size'=>null,
                            'is_featured'=>true,
                        ]));
                    }

                    if(!empty($item->file_path)){
                        DB::table('media_library_items')->insert(array_merge($base,[
                            'type'=>'file','title'=>$item->file_name ?: 'Файл проекта','url'=>$item->file_path,
                            'file_name'=>$item->file_name,'mime_type'=>$item->mime_type,'file_size'=>$item->file_size,
                            'is_featured'=>false,'sort_order'=>10,
                        ]));
                    }

                    if(!empty($item->video_url)){
                        DB::table('media_library_items')->insert(array_merge($base,[
                            'type'=>'video','title'=>'Видео','url'=>$item->video_url,
                            'file_name'=>null,'mime_type'=>null,'file_size'=>null,
                            'is_featured'=>false,'sort_order'=>20,
                        ]));
                    }

                    if(!empty($item->project_url)){
                        DB::table('media_library_items')->insert(array_merge($base,[
                            'type'=>'link','title'=>'Ссылка на проект','url'=>$item->project_url,
                            'file_name'=>null,'mime_type'=>null,'file_size'=>null,
                            'is_featured'=>false,'sort_order'=>30,
                        ]));
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('media_library_items', function (Blueprint $table) {
            $table->dropColumn(['file_name','mime_type','file_size']);
        });
    }
};
