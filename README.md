# Answered

Answered is a revision website for students, with subjects, study notes,
past papers, and search. Administrators manage content through a private
dashboard.

**Website: [https://answered.qzz.io/](https://answered.qzz.io/)**

## Features

- Browse revision materials by subject and topic.
- Read formatted notes with images.
- Preview and download past-paper PDFs from external links.
- Search published revision materials.
- Create, preview, publish, and manage content from the admin dashboard.
- Configure desktop banner advertisements in the left and right sidebars.

## Technology

The application uses PHP, MySQL, HTML, CSS, and JavaScript. It can be hosted
on VistaPanel shared hosting by uploading files through File Manager or FTP.
Production hosting does not require Node.js, Docker, or a build step.

The PHP environment needs `pdo_mysql`, `dom`, and `mbstring`, with JSON
support enabled. Apache uses the supplied `.htaccess` files for access rules
and optional clean URLs.

## File structure

```text
answered/
|-- README.md
|-- tests/
|   `-- security.php
`-- htdocs/                     Website document root
    |-- index.php               Public entry point
    |-- .htaccess               Routing and access rules
    |-- install.php             Initial setup; delete after installation
    |-- schema.sql              Database tables
    |-- router.php              Local development router
    |-- assets/
    |   |-- css/                Public and admin styles
    |   `-- js/                 Navigation, editor, and sidebar ads
    |-- config/
    |   |-- .htaccess
    |   |-- config.php          Active database and application settings
    |   `-- config.sample.php   Configuration template
    |-- includes/              Authentication, queries, layout, and helpers
    |-- pages/                 Public page templates
    |-- manage-.../            Admin directory matching app.admin_path
    `-- uploads/               Legacy media directories
```

Images and PDFs are normally stored as external URLs. New media files are
not uploaded to the hosting account by the application.

## Deploy with VistaPanel

1. Open **MySQL Databases** and create the database. Record the exact database
   hostname, full database name, username, and password shown by the host.
2. Copy `htdocs/config/config.sample.php` to `htdocs/config/config.php`, then
   edit the copy with those connection details. Private configuration files
   are ignored by Git:

   ```php
   'db' => [
       'host'    => 'YOUR_MYSQL_HOST',
       'name'    => 'YOUR_DATABASE_NAME',
       'user'    => 'YOUR_MYSQL_USERNAME',
       'pass'    => 'YOUR_MYSQL_PASSWORD',
       'charset' => 'utf8mb4',
   ],
   ```

   The application loads `config.php`; `config.local.php` is not loaded.
   Keep real passwords out of documentation and public repositories.
3. For deployment at `https://answered.qzz.io/`, leave `app.base_path` empty
   and set `app.https` to `true`. Leave `app.clean_urls` set to `false` unless
   clean URL rewriting has been configured on the host.
4. Upload the **contents** of the local `htdocs/` directory into the domain's
   existing `htdocs/` directory. Enable **Show hidden files** and include all
   `.htaccess` files. The entry point must be `htdocs/index.php`, without an
   extra `htdocs/` folder inside it.
   For a complete update ZIP, run `python tools/package_release.py` and extract
   the resulting `answered-design-update.zip` into the server's existing
   `htdocs/` directory. The archive contains `index.php` at its root. Preserve
   the server's private `config/config.php`; the ZIP does not contain credentials.
   The update package excludes the one-time installer. Use the source checkout
   for a new installation that still needs setup.
5. For a new installation, set `app.install_token` to a private random value
   of at least 32 characters. For example, generate one locally with:

   ```sh
   php -r "echo bin2hex(random_bytes(32));"
   ```

   Visit `https://answered.qzz.io/install.php`, authorize setup with the token,
   create the tables if needed, and create the first administrator. The schema
   can also be imported through phpMyAdmin before creating the administrator.
6. Delete `install.php` from the server and clear `app.install_token` after
   setup. Existing installations should leave setup disabled.
7. Sign in through the admin folder configured by `app.admin_path`. Its value
   must match the actual uploaded directory name.

## Public advertisements

The public site uses the supplied answered.qzz.io tags defined in
`htdocs/includes/ads.php`:

- A 160 x 600 banner below the right sidebar, on screens at least 1280px wide.
- A 728 x 90 banner below the navigation, only when the page has enough width.
- A 320 x 50 banner in the same position on smaller screens, instead of the leaderboard.
- One native banner replacing the homepage hero illustration; on other pages,
  it appears below the content. This placement also works on mobile screens.

Placements are labelled Advertisement. They load once, near the viewport.
Fixed banners run in separate sandboxed documents through `ad-frame.php` to
preserve the provider's synchronous tags and separate their `atOptions` values.
The native script loads asynchronously after its target container exists.
Admin screens, internal search, error pages, and print layouts have no ads.

Set `ads.enabled` to `false` in your private `config/config.php` to disable all
public placements. Old sidebar zone settings are no longer used. Upload the
updated public PHP templates, `includes/ads.php`, `ad-frame.php`,
`assets/js/ads.js`, and `assets/css/app.css`. The old `assets/js/side-ads.js`
file is no longer needed. Actual ad fill depends on the provider and its
configuration for the production domain.

Run `php tests/ads.php` and `node --test tests/ads_loader.cjs` for placement checks.

## Local development

Configure a local MySQL database, then run from the repository root:

```sh
cd htdocs
php -S localhost:8000 router.php
```

Open `http://localhost:8000/`. Use the router because PHP's development
server does not read Apache `.htaccess` rules.

## Admin workspace

The admin workspace includes account management, password changes, active and
disabled accounts, administrator/editor permissions, searchable and paginated
notes/papers/images, ZIMSEC level and syllabus fields, editable SEO previews,
server-side note recovery, revision history, trash/restore, and activity history.

**Existing installations must import
`htdocs/migrations/20261116_admin.sql` once before uploading this release.**
See `htdocs/migrations/README.md` for upgrade order and recovery behavior. Fresh
installations use the updated `schema.sql` without running the migration.

For local integration testing only, use an isolated seeded preview database and
set `ANSWERED_TEST_PASSWORD` to its administrator password, then run:

```sh
python tests/admin_workflows.py --base-url http://127.0.0.1:8000 --admin-user preview
```

The workflow tests accept only loopback URLs and create accounts and resource
records with a unique QA prefix. They cover authorization, CSRF, autosave,
optimistic conflicts, revisions, trash, classification, SEO overrides, pagination,
and session invalidation. Use a disposable database for these tests.

## Search metadata

Public pages generate unique ZIMSEC titles and descriptions, canonical URLs,
Open Graph and Twitter metadata, and JSON-LD for the site, breadcrumbs, and
learning resources. Search results, errors, and admin pages are not indexable.
This is an independent study platform; metadata does not claim ZIMSEC affiliation.

The canonical origin defaults to `https://answered.qzz.io`. When hosting on a
different domain, add `app.site_url` to `htdocs/config/config.php` with the origin
only (for example, `https://example.com`). Continue to use `app.base_path` for a
subfolder. For staging, set `app.indexable` to `false` and use the staging origin.
Production should use `app.indexable => true` (the default).

`/robots.txt` advertises `/sitemap.xml`, a dynamic sitemap index containing subjects,
subject sections, published notes, past papers, and paginated paper listings.
Draft notes and internal search URLs are excluded. Upload the updated root
`.htaccess` to enable these endpoints on Apache; `/robots.php` and `/sitemap.php`
also work directly. Submit the production sitemap to Google Search Console after
deployment. No verification token is bundled with the application.

Run `php tests/seo.php` for the database-free SEO checks.

## Security checks

Run from the repository root:

```sh
php tests/security.php
```

The checks use temporary fixtures and do not require a live database. They
cover installer authorization, CSRF, private-file access, session settings,
login throttling, media URL validation, and legacy file path boundaries.

The application uses prepared queries, password hashing, CSRF tokens, and
rich-text sanitization. Login throttling requires a writable PHP temporary
directory; deployments across multiple application hosts need a shared
rate limiter.

## Troubleshooting

| Problem | What to check |
| --- | --- |
| 403 on the homepage | Confirm the domain's document root contains lowercase `index.php` and the root `.htaccess`. Keep the separate restrictive files inside `config/` and `includes/`. |
| Database connection failed | Verify all four database settings in the uploaded `config/config.php`. Use the host's MySQL hostname and inspect server logs for the underlying error. |
| Database tables are missing | Import `schema.sql` in phpMyAdmin or complete initial setup. |
| Clean URLs return 404 | Set `app.clean_urls` to `false` or check the host's rewrite configuration. |
| Login temporarily unavailable | Check PHP temporary-directory permissions and server logs. |
| Ads are missing | Check `ads.enabled`, upload the new ad files, confirm banner width requirements, and check provider domain approval and browser ad blocking. |
