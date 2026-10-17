<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class AdminBackupController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->is_admin,403);

        return view('admin.backups.index',[
            'driver'=>DB::connection()->getDriverName(),
            'storageBytes'=>$this->directorySize(storage_path('app/public')),
        ]);
    }

    public function database()
    {
        abort_unless(auth()->user()->is_admin,403);

        $driver=DB::connection()->getDriverName();

        if($driver==='mysql'){
            $rows=DB::select('SHOW TABLES');
            $tables=collect($rows)->map(fn($row)=>(string)array_values((array)$row)[0])->values();
        }elseif($driver==='sqlite'){
            $tables=collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name')
                ->values();
        }else{
            abort(422,'Автоматический экспорт для драйвера '.$driver.' пока не поддерживается.');
        }

        $payload=[
            'created_at'=>now()->toIso8601String(),
            'app'=>config('app.name'),
            'driver'=>$driver,
            'tables'=>[],
        ];

        foreach($tables as $table){
            if($table==='migrations') {
                $payload['tables'][$table]=DB::table($table)->orderBy('id')->get()->map(fn($r)=>(array)$r)->all();
                continue;
            }

            $payload['tables'][$table]=DB::table($table)->get()->map(fn($r)=>(array)$r)->all();
        }

        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);

        ActivityLog::record('backup.database',null,'Скачана резервная копия базы данных');

        return response($json,200,[
            'Content-Type'=>'application/json; charset=utf-8',
            'Content-Disposition'=>'attachment; filename="shki-db-'.now()->format('Y-m-d-His').'.json"',
        ]);
    }

    public function files()
    {
        abort_unless(auth()->user()->is_admin,403);

        abort_unless(class_exists(ZipArchive::class),422,'На сервере не установлено PHP-расширение zip.');

        $source=storage_path('app/public');
        $temp=storage_path('app/backup-files-'.auth()->id().'-'.now()->format('YmdHis').'.zip');

        $zip=new ZipArchive();
        abort_unless($zip->open($temp,ZipArchive::CREATE|ZipArchive::OVERWRITE)===true,500,'Не удалось создать ZIP.');

        if(is_dir($source)){
            $iterator=new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source,\FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach($iterator as $file){
                if(!$file->isFile()) continue;
                $real=$file->getRealPath();
                $relative=substr($real,strlen($source)+1);
                $zip->addFile($real,$relative);
            }
        }

        $zip->close();

        ActivityLog::record('backup.files',null,'Скачана резервная копия загруженных файлов');

        return response()->download(
            $temp,
            'shki-files-'.now()->format('Y-m-d-His').'.zip',
            ['Content-Type'=>'application/zip']
        )->deleteFileAfterSend(true);
    }

    private function directorySize(string $path): int
    {
        if(!is_dir($path)) return 0;

        $size=0;
        $iterator=new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS)
        );

        foreach($iterator as $file){
            if($file->isFile()) $size+=$file->getSize();
        }

        return $size;
    }
}
