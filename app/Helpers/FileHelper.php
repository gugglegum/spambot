<?php

namespace App\Helpers;

class FileHelper
{
    /**
     * @param string $from
     * @param string $to
     * @return void
     * @throws \Exception
     */
    public static function copy(string $from, string $to): void
    {
        $fh1 = fopen($from, 'r');
        $fh2 = fopen($to, 'w');
        $bufferSize = 384 * 1024 * 1024;
        do {
            $buffer = fread($fh1, $bufferSize);
            if ($buffer !== false) {
                $result = fwrite($fh2, $buffer);
                if ($result === false || $result != strlen($buffer)) {
                    throw new \Exception("Copy error: failed to write to destination file");
                }
            } else {
                throw new \Exception("Copy error: failed to read from source file");
            }
        } while (strlen($buffer) == $bufferSize);
        FileHelper::touch($to, FileHelper::filemtime($from));
    }

    public static function mkdir(string $directory, int $permissions = 0777, bool $recursive = false, $context = null): void
    {
        if (!mkdir($directory, $permissions, $recursive, $context)) {
            throw new \RuntimeException("Can't create directory {$directory}");
        }
    }

    public static function createSubDir(string $baseDir, $relPath, $permissions = 0777): void
    {
        $pathParts = preg_split('/[\\\/]/', $relPath, -1, PREG_SPLIT_NO_EMPTY);
        $currentPath = $baseDir;
        foreach ($pathParts as $pathPart) {
            $currentPath .= DIRECTORY_SEPARATOR . $pathPart;
            if (!is_dir($currentPath)) {
                self::mkdir($currentPath, $permissions);
            }
        }
    }

    public static function filesize(string $file): int
    {
        $size = filesize($file);
        if ($size === false) {
            throw new \RuntimeException("Can't get size of the file \"{$file}\"");
        }
        return $size;
    }

    /**
     * @param string $dir
     * @param array $excludeFiles
     * @return int
     * @throws \Exception
     */
    public static function dirsize(string $dir, array $excludeFiles = []): int
    {
        $bytesTotal = 0;
        $rootAbsPath = FileHelper::realpath($dir);
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($rootAbsPath, \FilesystemIterator::SKIP_DOTS)) as $object) {
            if (str_starts_with($object->getPathname(), $rootAbsPath)) {
                $rootRelPath = str_replace('\\', '/', ltrim(substr($object->getPathname(), strlen($rootAbsPath)), '\\/'));
            } else {
                throw new \Exception("Strange situation: full file path doesn't contain it's root path");
            }

            if (!in_array($rootRelPath, $excludeFiles)) {
                $bytesTotal += $object->getSize();
            }
         }
        return $bytesTotal;
    }

    public static function filemtime(string $file): int
    {
        $time = filemtime($file);
        if ($time === false) {
            throw new \RuntimeException("Can't get last modified time of the file \"{$file}\"");
        }
        return $time;
    }

    public static function touch(string $file, ?int $mtime, ?int $atime = null): void
    {
        if (!touch($file, $mtime, $atime)) {
            throw new \RuntimeException("Can't set last modified time of the file \"{$file}\"");
        }
    }

    public static function realpath(string $path): string
    {
        $realpath = realpath($path);
        if ($realpath === false) {
            throw new \RuntimeException("Failed to get realpath for \"{$path}\"");
        }
        return $realpath;
    }

    public static function md5_file(string $file): string
    {
        $md5 = md5_file($file);
        if ($md5 === false) {
            throw new \RuntimeException("Can't get MD5 of the file \"{$file}\"");
        }
        return $md5;
    }

    public static function file_get_contents(string $filename, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null): string
    {
        $contents = file_get_contents($filename, $use_include_path, $context, $offset, $length);
        if ($contents === false) {
            throw new \RuntimeException("Can't get contents of the file \"{$filename}\"");
        }
        return $contents;
    }
}
