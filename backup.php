<?php
include 'config.php'; // شامل فایل تنظیمات

header('Content-Type: text/plain; charset=utf-8'); // خروجی به صورت متن ساده

// 1. بکاپ دیتابیس
$backupFolder = '/path/to/backup/folder';  // مسیر پوشه بکاپ
$timestamp = date('Y-m-d_H-i-s');          // برای نامگذاری فایل‌ها
$backupFile = $backupFolder . "/backup_db_{$timestamp}.sql";  // فایل بکاپ دیتابیس

// تنظیمات دیتابیس از config.php
$dbHost = $db_host;
$dbUser = $db_user;
$dbPass = $db_pass;
$dbName = $db_name;

// دستور بکاپ دیتابیس
$command = "mysqldump --opt -h {$dbHost} -u {$dbUser} -p{$dbPass} {$dbName} > \"{$backupFile}\"";
exec($command);

// چک کردن نتیجه بکاپ
if (file_exists($backupFile)) {
    echo "Database backup created successfully: {$backupFile}\n";
} else {
    echo "Error creating database backup.\n";
}

// 2. بکاپ گرفتن از فایل‌ها
$filesBackupFolder = $backupFolder . "/files_{$timestamp}";  // مسیر برای بکاپ فایل‌ها

if (!@mkdir($filesBackupFolder, 0777, true)) {
    echo "Error creating folder for files backup: {$filesBackupFolder}\n";
} else {
    // کپی کردن تمام فایل‌ها و پوشه‌ها به پوشه بکاپ
    $source = '/path/to/project/folder';  // مسیر پروژه
    $destination = $filesBackupFolder;

    function copyFiles($src, $dest) {
        $dir = opendir($src);
        @mkdir($dest);

        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $srcFile = $src . '/' . $file;
                $destFile = $dest . '/' . $file;

                if (is_dir($srcFile)) {
                    copyFiles($srcFile, $destFile);
                } else {
                    copy($srcFile, $destFile);
                }
            }
        }

        closedir($dir);
    }

    // کپی کردن فایل‌ها
    copyFiles($source, $destination);

    // چک کردن نتیجه بکاپ فایل‌ها
    if (is_dir($filesBackupFolder)) {
        echo "Files backup created successfully: {$filesBackupFolder}\n";
    } else {
        echo "Error creating files backup.\n";
    }
}

// 3. نکات اضافی (اختیاری)
echo "Backup completed at: " . date('Y-m-d H:i:s') . "\n";
