# vanda/orchid-compat

Lets an Orchid 14 application drop `orchid/platform` without touching its models, services,
jobs and controllers. The admin screens themselves are rewritten as Vanda screens.

| Orchid | Here |
|---|---|
| `Orchid\Filters\Filterable`, `Types\Where`, `WhereIn`, `Like`, `WhereDate`, `WhereDateStartEnd` | same classes, `$allowedFilters` / `$allowedSorts` keep working |
| `Orchid\Screen\AsSource`, `Repository` | same |
| `Orchid\Attachment\Attachable`, `Models\Attachment`, `File` | on Vanda's `attachments` model |
| `Orchid\Platform\Models\User`, `Role` | on Vanda's users and roles |
| `route('platform.ticket.edit', …)` | builds the URL of `admin.ticket.edit` (`config/orchid-compat.php`, `route_aliases`) |
| `config('platform.prefix')` | the prefix of the first `Route::vanda()` panel, e.g. `/admin` |

Route aliases use Laravel's resolver for missing route names, so routes keep one name:
`Route::has('platform.…')` stays `false`, `route:cache` is not affected.

Orchid fields map to Vanda like this: `Quill` → `RichText`, `SimpleMDE` → `Markdown`,
`Cropper` / `Picture` → `Upload::image()->target(UploadTarget::RelativeUrl)->crop(...)`,
`Attach` → `Upload`, `Matrix` → `Matrix`, `Map` → `Map`, `UTM` → `Utm`, `Label` → `Label`,
`TimeZone` → `TimeZone`, `Relation` → `Relation`, `ViewField` / `Layout::view` → `Layout::view`.
