<?php
namespace App\Services;

use App\Models\SiteSetting;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class StorageQuota
{
    public static function quotaMb(): int
    {
        return max(0,(int) SiteSetting::getValue('storage_quota_mb','0'));
    }

    public static function quotaBytes(): int
    {
        return self::quotaMb() * 1024 * 1024;
    }

    public static function usedBytes(): int
    {
        $path=storage_path('app/public');
        if(!is_dir($path)) return 0;

        $total=0;
        $iterator=new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach($iterator as $file){
            if($file->isFile()) $total += $file->getSize();
        }

        return $total;
    }

    public static function remainingBytes(): ?int
    {
        $quota=self::quotaBytes();
        if($quota<=0) return null;
        return max(0,$quota-self::usedBytes());
    }

    public static function canStore(int $bytes): bool
    {
        $quota=self::quotaBytes();
        if($quota<=0) return true;
        return self::usedBytes()+max(0,$bytes) <= $quota;
    }

    public static function stats(): array
    {
        $used=self::usedBytes();
        $quota=self::quotaBytes();
        $remaining=$quota>0 ? max(0,$quota-$used) : null;
        $percent=$quota>0 ? min(100,round(($used/$quota)*100,1)) : 0;

        return compact('used','quota','remaining','percent');
    }

    public static function formatBytes(?int $bytes): string
    {
        if($bytes===null) return 'Без лимита';
        $units=['Б','КБ','МБ','ГБ','ТБ'];
        $size=max(0,$bytes);
        $i=0;
        while($size>=1024 && $i<count($units)-1){
            $size/=1024;
            $i++;
        }
        return round($size,$i ? 1 : 0).' '.$units[$i];
    }
}
