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
2. Edit `htdocs/config/config.php` with those connection details:

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

## Sidebar advertisements

Create two Adcash **Display/Banner** zones sized **160 x 600**, then enter
their zone IDs in `htdocs/config/config.php`:

```php
'ads' => [
    'left_zone_id'  => 'YOUR_LEFT_BANNER_ZONE_ID',
    'right_zone_id' => 'YOUR_RIGHT_BANNER_ZONE_ID',
],
```

The banners sit below the existing content in the left and right sidebars.
They load on screens at least 1280 pixels wide. Empty zone IDs disable the
placements. AutoTag zone IDs cannot be used as Display/Banner zone IDs.

For an existing deployment, upload the updated configuration, both
`includes/layout.php` and `includes/components.php`, `assets/css/app.css`,
and `assets/js/side-ads.js`, preserving their paths inside `htdocs/`.

## Local development

Configure a local MySQL database, then run from the repository root:

```sh
cd htdocs
php -S localhost:8000 router.php
```

Open `http://localhost:8000/`. Use the router because PHP's development
server does not read Apache `.htaccess` rules.

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
| Sidebar ads are missing | Supply Display/Banner zone IDs, upload the ad-related files, and check at a desktop width of at least 1280 pixels. |
