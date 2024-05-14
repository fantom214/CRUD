<?php

namespace Backpack\CRUD\Tests\config;

use Illuminate\Http\UploadedFile;

trait HasUploadedFiles
{
    protected function getUploadedFile(string $fileName, string $mime = 'image/jpg')
    {
        return new UploadedFile(__DIR__.'/assets/'.$fileName, $fileName, $mime, null, true);
    }

    protected function getUploadedFiles(array $fileNames, string $mime = 'image/jpg')
    {
        return array_map(function ($fileName) use ($mime) {
            return new UploadedFile(__DIR__.'/assets/'.$fileName, $fileName, $mime, null, true);
        }, $fileNames);
    }
}