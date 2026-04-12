Photo pipeline — invariants

- Original images are immutable and stored under `uploads/originals/...`
- Derived images live under `uploads/derived/...` and may be deleted/rebuilt
- Job styling is defined via `App\Services\JobPhotoStyle::forJob($jobType)`
- Pipeline manifests are written next to derived files as JSON (`.png.json`)
- To rebuild: `php artisan photo:rebuild --employee=123` or `--job-type="ADMINISTRATOR"` or `--all`
- Failure policy: configured in `config/photo_pipeline.php` under `failure_policy`.

Keep this file short — it's for future you.
