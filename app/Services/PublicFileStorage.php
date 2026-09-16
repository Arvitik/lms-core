<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

final class PublicFileStorage
{
    public static function store(UploadedFile $file, $directory, $prefix)
    {
        $relativeDirectory = trim(str_replace('\\', '/', $directory), '/');
        $absoluteDirectory = public_path($relativeDirectory);

        if (!is_dir($absoluteDirectory) || !is_writable($absoluteDirectory)) {
            throw new RuntimeException('Каталог для загрузки файлов недоступен для записи.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = $prefix.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4));
        if ($extension !== '') {
            $filename .= '.'.$extension;
        }

        $file->move($absoluteDirectory, $filename);

        return $relativeDirectory.'/'.$filename;
    }

    public static function absolutePath($relativePath)
    {
        return public_path(ltrim(str_replace('\\', '/', (string) $relativePath), '/'));
    }

    public static function delete($relativePath)
    {
        if (!$relativePath) {
            return true;
        }

        $path = self::absolutePath($relativePath);
        return !file_exists($path) || unlink($path);
    }
}
