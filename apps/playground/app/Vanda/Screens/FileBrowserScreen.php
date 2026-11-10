<?php

namespace App\Vanda\Screens;

use App\Vanda\DemoFiles;
use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Actions\ModalToggle;
use Vanda\Attributes\Action;
use Vanda\Attributes\Locked;
use Vanda\Fields\Input;
use Vanda\Fields\InputType;
use Vanda\Fields\Select;
use Vanda\Layouts\Files;
use Vanda\Layouts\Layout;
use Vanda\Layouts\Sight;
use Vanda\Navigation\Crumb;
use Vanda\Panel;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

/**
 * Layout::files(): folders open by their address, a file opens a modal (->fileAction()), the
 * search looks through every folder. A sandbox: the files are a fixed list (DemoFiles), nothing is
 * saved or deleted.
 */
final class FileBrowserScreen extends Screen
{
    /** The open folder; '' — the top of the storage. */
    #[Locked]
    public string $folder = '';

    public string $q = '';

    /** The file in the modal. */
    #[Locked]
    public ?string $fileKey = null;

    public string $fileName = '';

    public ?string $fileFolder = null;

    public function query(string $folder = ''): array
    {
        abort_if($folder !== '' && ! isset(DemoFiles::FOLDERS[$folder]), 404);
        $this->folder = $folder;

        return [];
    }

    public function title(): string
    {
        return $this->folder === '' ? 'Файлы' : DemoFiles::FOLDERS[$this->folder][0];
    }

    public function description(): string
    {
        return 'Layout::files(): папка открывается по адресу, файл — в модальном окне. Ничего не сохраняется.';
    }

    public function breadcrumbs(): iterable
    {
        if ($this->folder === '') {
            return [];
        }

        $crumbs = [];
        for ($key = DemoFiles::FOLDERS[$this->folder][1]; $key !== null; $key = DemoFiles::FOLDERS[$key][1]) {
            array_unshift($crumbs, Crumb::make(DemoFiles::FOLDERS[$key][0])->to(self::class, ['folder' => $key]));
        }

        return [Crumb::make('Файлы')->to(self::class), ...$crumbs, Crumb::make($this->title())];
    }

    public function layout(): iterable
    {
        $file = $this->fileKey !== null ? DemoFiles::FILES[$this->fileKey] : null;

        return [
            Layout::card($this->folder === '' ? 'Все папки' : 'Содержимое папки')->schema([
                $this->browser(),
            ]),

            Layout::modal('file', 'Файл')->wide()
                ->schema([
                    Layout::legend([
                        'type' => $file !== null ? strtoupper(pathinfo($file[2], PATHINFO_EXTENSION)).' · '.$file[3] : '',
                        'size' => $this->fileKey !== null ? (string) Files::size(DemoFiles::size($this->fileKey)) : '',
                        'date' => $this->fileKey !== null ? DemoFiles::date($this->fileKey)->format('d.m.Y H:i') : '',
                        'url' => $this->fileKey !== null ? (string) DemoFiles::url($this->fileKey) : '',
                    ], [
                        // A long MIME type and a long address stay inside the modal: the legend breaks them.
                        Sight::make('type', 'Тип'),
                        Sight::make('size', 'Размер'),
                        Sight::make('date', 'Загружен'),
                        Sight::make('url', 'Адрес')->mono()->copyable(),
                    ]),
                    Input::make('fileName')->label('Название')->required()->help('Так файл называется при скачивании'),
                    Select::make('fileFolder')->label('Папка')->required()
                        ->options(array_map(fn (array $folder) => $folder[0], DemoFiles::FOLDERS)),
                    Link::make('Открыть файл')->icon('external')
                        ->href((string) ($this->fileKey !== null ? DemoFiles::url($this->fileKey) : ''), external: true)
                        ->visible($this->fileKey !== null && DemoFiles::url($this->fileKey) !== null),
                ])
                ->buttons([
                    Button::make('Удалить')->icon('trash')->danger()
                        ->confirm('Файл удаляется без возможности восстановления.', "Удалить «{$this->fileName}»?", 'Удалить файл')
                        ->action($this->deleteFile(...)),
                    Button::make('Сохранить')->icon('save')->primary()->action($this->saveFile(...)),
                ]),
        ];
    }

    /** Fills the modal for the clicked file. */
    #[Action(validate: false)]
    public function loadFile(string $key): Result
    {
        abort_unless(isset(DemoFiles::FILES[$key]), 404);

        $this->fileKey = $key;
        $this->fileName = DemoFiles::FILES[$key][0];
        $this->fileFolder = DemoFiles::FILES[$key][1];

        return Result::refresh()->openModal('file');
    }

    #[Action]
    public function saveFile(): Result
    {
        return Result::toast("«{$this->fileName}» в порядке (в песочнице ничего не сохраняется)");
    }

    #[Action(validate: false)]
    public function deleteFile(): Result
    {
        $name = $this->fileName;
        $this->fileKey = null;

        return Result::toast("«{$name}» удалён бы (в песочнице ничего не удаляется)");
    }

    /** The folder's content, or what the search finds in every folder. */
    private function browser(): Files
    {
        $browser = Layout::files()->id('files')
            ->toolbar([Input::make('q')->label('Поиск')->inputType(InputType::Search)->placeholder('Название папки или файла — по всем папкам')])
            ->selected($this->fileKey)
            ->fileAction(fn (string $key) => ModalToggle::make('Открыть')->modal('file')->load($this->loadFile(...), ['key' => $key]))
            ->dependsOn('q');

        $query = mb_strtolower(trim($this->q));

        if ($query !== '') {
            foreach (DemoFiles::FOLDERS as $key => [$name, $parent]) {
                if (str_contains(mb_strtolower($name), $query)) {
                    $browser->folder($name, Panel::url(self::class, ['folder' => $key]), $this->folderMeta($key), hint: $parent !== null ? 'в «'.DemoFiles::FOLDERS[$parent][0].'»' : 'верхний уровень');
                }
            }
            foreach (DemoFiles::FILES as $key => $file) {
                if (str_contains(mb_strtolower($file[0]), $query)) {
                    $this->addFile($browser, (string) $key, 'в «'.DemoFiles::FOLDERS[$file[1]][0].'»');
                }
            }

            return $browser->emptyText("По запросу «{$this->q}» ничего не нашлось");
        }

        foreach (DemoFiles::foldersIn($this->folder === '' ? null : $this->folder) as $key => [$name]) {
            $browser->folder($name, Panel::url(self::class, ['folder' => $key]), $this->folderMeta($key));
        }
        if ($this->folder !== '') {
            foreach (array_keys(DemoFiles::filesIn($this->folder)) as $key) {
                $this->addFile($browser, (string) $key);
            }
        }

        return $browser->emptyText($this->folder === '' ? 'Папок пока нет' : 'В папке пока пусто');
    }

    private function addFile(Files $browser, string $key, ?string $hint = null): void
    {
        [$name, , , $mime] = DemoFiles::FILES[$key];
        $browser->file($key, $name, url: DemoFiles::url($key), mime: $mime, size: DemoFiles::size($key), date: DemoFiles::date($key), hint: $hint);
    }

    private function folderMeta(string $key): string
    {
        $folders = count(DemoFiles::foldersIn($key));
        $files = count(DemoFiles::filesIn($key));

        return implode(' · ', array_filter([$folders > 0 ? "папок: {$folders}" : null, "файлов: {$files}"]));
    }
}
