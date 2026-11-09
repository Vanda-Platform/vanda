<?php

namespace App\Vanda;

use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * A small file storage for the file browser demo (FileBrowserScreen): folders and files are a fixed
 * list, the files themselves lie in storage/app/public/demo-files (DatabaseSeeder makes them).
 */
final class DemoFiles
{
    /** key => [name, parent key] */
    public const FOLDERS = [
        'catalog' => ['Каталог', null],
        'photos' => ['Фото', 'catalog'],
        'documents' => ['Документы', null],
        'certificates' => ['Сертификаты', 'documents'],
        'empty' => ['Архив 2024', 'documents'],
    ];

    /** key => [name, folder key, file in demo-files, mime, date] */
    public const FILES = [
        '1' => ['Эспрессо-смесь.jpg', 'photos', 'espresso.jpg', 'image/jpeg', '2026-10-02'],
        '2' => ['Зелёное зерно.jpg', 'photos', 'beans.jpg', 'image/jpeg', '2026-10-02'],
        '3' => ['Капучино крупным планом с длинным названием.png', 'photos', 'cappuccino.png', 'image/png', '2026-10-05'],
        '4' => ['Логотип.svg', 'photos', 'logo.svg', 'image/svg+xml', '2026-09-14'],
        '5' => ['Прайс-лист.pdf', 'catalog', 'price.pdf', 'application/pdf', '2026-11-01'],
        '6' => ['Остатки на складе.xlsx', 'catalog', 'stock.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', '2026-11-03'],
        '7' => ['Договор поставки.docx', 'documents', 'contract.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-08-20'],
        '8' => ['Презентация для партнёров.pptx', 'documents', 'deck.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', '2026-07-11'],
        '9' => ['Сертификат соответствия.pdf', 'certificates', 'certificate.pdf', 'application/pdf', '2026-02-28'],
        '10' => ['Сканы декларации.zip', 'certificates', 'scans.zip', 'application/zip', '2026-03-01'],
    ];

    /** @return array<string, array{0: string, 1: ?string}> folders inside $parent (null: the top) */
    public static function foldersIn(?string $parent): array
    {
        return array_filter(self::FOLDERS, fn (array $folder) => $folder[1] === $parent);
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}> */
    public static function filesIn(string $folder): array
    {
        return array_filter(self::FILES, fn (array $file) => $file[1] === $folder);
    }

    public static function url(string $key): ?string
    {
        $path = 'demo-files/'.self::FILES[$key][2];

        return Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }

    public static function size(string $key): ?int
    {
        $path = 'demo-files/'.self::FILES[$key][2];

        return Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : null;
    }

    public static function date(string $key): DateTimeImmutable
    {
        return new DateTimeImmutable(self::FILES[$key][4]);
    }

    /**
     * Writes the demo files: pictures drawn with GD, small stand-ins for the documents.
     */
    public static function generate(): void
    {
        $disk = Storage::disk('public');
        $disk->makeDirectory('demo-files');

        $pictures = [
            'espresso.jpg' => [[60, 36, 24], [176, 120, 72]],
            'beans.jpg' => [[46, 92, 52], [168, 196, 120]],
            'cappuccino.png' => [[214, 186, 150], [120, 78, 48]],
        ];
        foreach ($pictures as $file => [$from, $to]) {
            $image = imagecreatetruecolor(640, 480);
            for ($y = 0; $y < 480; $y++) {
                $mix = $y / 479;
                $color = imagecolorallocate($image, ...array_map(fn (int $a, int $b) => (int) round($a + ($b - $a) * $mix), $from, $to));
                imageline($image, 0, $y, 639, $y, $color);
            }
            imagefilledellipse($image, 320, 250, 260, 200, imagecolorallocatealpha($image, 255, 255, 255, 80));
            ob_start();
            str_ends_with($file, '.png') ? imagepng($image) : imagejpeg($image, null, 82);
            $disk->put('demo-files/'.$file, (string) ob_get_clean());
            imagedestroy($image);
        }

        $disk->put('demo-files/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="#12805c"/></svg>');
        $disk->put('demo-files/price.pdf', "%PDF-1.4\n% Демо: прайс-лист\n".str_repeat("0 0 0 rg\n", 4000));
        $disk->put('demo-files/certificate.pdf', "%PDF-1.4\n% Демо: сертификат\n".str_repeat("1 0 0 rg\n", 9000));
        $disk->put('demo-files/stock.xlsx', str_repeat('stock;', 2500));
        $disk->put('demo-files/contract.docx', str_repeat('contract ', 3000));
        $disk->put('demo-files/deck.pptx', str_repeat('slide ', 180000));

        $zip = new ZipArchive;
        $path = $disk->path('demo-files/scans.zip');
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFromString('scan-1.txt', str_repeat('scan ', 20000));
            $zip->close();
        }
    }
}
