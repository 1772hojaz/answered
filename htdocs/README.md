# RevisionHub

A complete, self-hosted revision platform for students: subjects, rich-text
notes with images, past-paper PDFs, search and a secure admin dashboard.

Built for standard shared hosting (**VistaPanel / cPanel**) — plain PHP 7.4+
and MySQL, no Node.js, no Docker, no background processes. Everything is
deployed as plain files over FTP.

---

## Requirements

| Requirement | Notes |
|---|---|
| PHP 7.4 or 8.x | `pdo_mysql`, `dom`, `mbstring`, `json` (all enabled by default). |
| MySQL 5.7+ / MariaDB 10.3+ | One database, six tables (see `schema.sql`). |
| Apache with `.htaccess` | Optional. Clean URLs use `mod_rewrite`; the app works without it. |

---

## Deployment (VistaPanel / cPanel)

1. **Create the database**
   VistaPanel → *MySQL Databases* → create a database and a user, then add
   the user to the database. Note the database name, username and password.

2. **Import the schema**
   phpMyAdmin → select the database → *Import* → choose `schema.sql` → *Go*.
   (Alternatively run `install.php` in step 5, which creates the tables for you.)

3. **Configure the app**
   Edit `config/config.php` — it is the only file you need to touch:

   ```php
   'db' => [
       'host' => 'localhost',
       'name' => 'your_database_name',
       'user' => 'your_database_user',
       'pass' => 'your_database_password',
   ],
   ```

   Other useful settings in the same file:

   * `app.name` — platform name shown in the header.
   * `app.clean_urls` — `false` (default) generates `index.php?p=/subjects/...`
     links that work on **any** host; set to `true` for `/subjects/...` links
     (requires the `mod_rewrite` block in `.htaccess`, which is enabled by
     default).
   * `app.base_path` — leave `''` for the domain root, or set `'/subfolder'`
     if you install inside a subfolder.
   * `app.https` — set to `true` once the site is served over HTTPS so
     session cookies are marked `Secure`.

4. **Upload the files**
   Upload the whole project to `public_html/` with an FTP client, keeping the
   folder structure (the `.htaccess` files must be uploaded too — enable
   “show hidden files” in your FTP client).

5. **Install**
   * Set `app.install_token` in `config/config.php` to a private random value
     of at least 32 characters. Setup is disabled while this value is empty.
     Generate a token with `php -r "echo bin2hex(random_bytes(32));"`.
     Enter it in the setup authorization form; do not put it in a URL.
   * Visit `https://yourdomain.com/install.php`, create the administrator
     account, then **delete `install.php`** from the server.
   * No directories need to be writable: images and PDFs are added as external
     links, not uploaded to the server.
   * Clear `app.install_token` after setup. The installer rejects further
     changes once an administrator exists, even if the file remains present.

6. **Log in**
   The admin area lives at the secret path set in `config/config.php`
   (`app.admin_path`, default `/manage-8f3ffdb330600372`). Rename the shipped
   `manage-…` folder to match if you change it, then sign in and start adding
   subjects → topics → notes → past papers. The panel is not linked anywhere
   on the public site and is marked `noindex`.

> If clean URLs are enabled but every page returns 404, your host does not
> support `mod_rewrite`: comment out the `RewriteBlock` in `.htaccess` and set
> `'clean_urls' => false` in `config/config.php`.

---

## Local development

Any PHP 7.4+ with a MySQL database works:

```bash
# edit config/config.php first, then:
php -S localhost:8000 router.php
# open http://localhost:8000/ (links use index.php?p=... by default)
```

---

## Project structure

```text
public_html/
├── index.php              public front controller (routing)
├── install.php            one-time installer (delete after use)
├── schema.sql             database schema
├── .htaccess              clean URLs + security rules
│
├── pages/                 public page templates
│   ├── home.php  subjects.php  subject.php
│   ├── note.php  past_papers.php  past_paper.php
│   ├── search.php  404.php
│
├── manage-…/              secret admin folder (rename to app.admin_path)
│   ├── index.php          dashboard with statistics
│   ├── login.php  logout.php
│   ├── subjects/  topics/  notes/  past-papers/  images/
│   └── actions/           AJAX endpoints (save image links)
│
├── assets/
│   ├── css/app.css        public styles: search bar, category rail, green theme
│   ├── css/admin.css      admin styles
│   ├── js/app.js          mobile navigation
│   ├── js/editor.js       rich-text note editor
│   └── js/admin.js        confirm dialogs
│
├── includes/              application core (not served over HTTP)
│   ├── bootstrap.php      config + session + autoload
│   ├── config.php         (in config/) your credentials — never public
│   ├── database.php       PDO helpers (prepared statements only)
│   ├── functions.php      URLs, CSRF, slugs, queries
│   ├── auth.php           admin authentication
│   ├── sanitize.php       HTML whitelist sanitiser for note content
│   ├── uploads.php        media-link validation + image registry
│   ├── layout.php         public page shell
│   └── components.php     shared view helpers
```

> Images and PDFs are referenced by **external links** stored in the database
> (`subjects.icon_path`, `images.path`, `past_papers.file_path`). Nothing is
> uploaded to the server.

---

## Content workflow

```text
Admin login → Dashboard → Create subject → Create topic → Create note
    → write + paste image links → Preview → Publish → students read the note
```

```text
Admin login → Dashboard → Past papers → Add past paper
    → enter metadata (year, board, paper number) + PDF link → students open the PDF
```

---

## Sidebar advertising

Create two Adcash Display/Banner zones sized **160 x 600**, then put their
zone IDs in `ads.left_zone_id` and `ads.right_zone_id` in `config/config.php`.
The placements sit below the existing left and right sidebar content and
load only on desktop screens at least 1280 pixels wide. Empty zone IDs leave
the placements disabled. AutoTag zones cannot be used for these placements.

Upload `includes/layout.php`, `includes/components.php`, `assets/css/app.css`,
`assets/js/side-ads.js`, and the updated `config/config.php` after configuring
the zones.

## Security notes

Use `router.php` with the built-in development server: it enforces the
private-file restrictions that Apache reads from `.htaccess`.

Run the security regression checks from the repository root with
`php tests/security.php`. They use temporary fixtures and require no database.

* **Passwords** — stored only as `password_hash()` bcrypt hashes.
* **SQL** — every query uses PDO prepared statements; no string interpolation
  of user input.
* **Sessions** — strict cookie-only sessions, `HttpOnly` + `SameSite=Lax`
  cookies (`Secure` on HTTPS), ID regenerated at login.
* **Login throttling** — shared, locked storage in the PHP temporary directory
  limits attempts per username and peer IP across sessions. The PHP worker
  needs write access to its temporary directory; throttling fails closed when
  storage is unavailable. Multiple application hosts need a shared rate limiter.
* **CSRF** — every state-changing form (and the AJAX image-link endpoint)
  carries a per-session token that is verified with `hash_equals()`.
* **XSS** — all output is escaped with `htmlspecialchars()`; note HTML is run
  through a whitelist sanitiser on save *and* on render (tags, attributes,
  URLs and inline CSS are all allow-listed).
* **Media links** — image and PDF links must be absolute `http(s)` URLs
  (validated with `filter_var`/`parse_url`); no files are uploaded to or
  served from the server, and stored image links are re-sanitised on save
  and on render.
* **Authorization** — every admin page/action calls `require_admin()`.

## Roadmap (extensible by design)

The schema uses IDs and separate tables for users, subjects, topics, notes,
past papers and images, so student accounts, quizzes, bookmarks, progress
tracking and comments can be added later without a rewrite.
