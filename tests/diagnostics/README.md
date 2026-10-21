# Diagnostics for a real site

These are not tests. They are scripts you run **inside a WordPress install** to see what this
build makes of a site you did not build — a tester's staging site, or a local copy loaded from
their database dump. They print, and where noted they write.

```
wp eval-file wp-content/plugins/million-dollar-script/tests/diagnostics/<script>.php \
    --allow-root --path=<install path> --url=<site url>
```

| Script | What it does | Writes? |
| --- | --- | --- |
| `migration-report.php` | Runs the MDS2 → MDS3 migration the way the wizard runs it, then prints before/after table counts, the report totals, repairs grouped by reason, page outcomes, every grid page URL, sample placement attachments, and the migration-run rows. Optional first argument overrides the legacy table prefix (default: this site's `$wpdb->prefix` + `mds_`). | Yes — it imports. |
| `restore-legacy-cell-images.php` | For a copy whose media files did not travel with the database: writes the picture bytes MDS2 kept inside its legacy block rows into the upload paths the migrated attachments already claim, so the ads show their real art at legacy cell size. | Files only, plus attachment meta. |
| `plant-legacy-order-uploads.php` | Stands in for the files a SQL dump cannot carry: writes one `uploads/milliondollarscript/images/tmp_<order id>_…png` per order from the picture bytes in its legacy block rows, under the name MDS2 gave ad uploads, so the import can be checked against the media route it prefers. **Only for a throwaway copy** - a real site already has these files. Run it before the migration. | Files only. |

`tests/` is excluded from built packages and from the public plugin repository, so these never
ship to a site that installs a release. The local reproduction of a tester's dump is driven
from the workspace: `scripts/migration-repro/README.md`.
