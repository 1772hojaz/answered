# Admin workspace upgrade

For an existing installation, import `20261116_admin.sql` once through phpMyAdmin
before uploading the updated PHP files. Keep a database backup before applying
schema changes. The migration adds columns and tables; it preserves existing
accounts, notes, and papers. Existing accounts remain active administrators.

Do not import this migration into a fresh database after importing the current
`schema.sql`: the fresh schema already contains the new structures. Do not rerun
the migration after a successful import.

After deployment, sign in again. Existing sessions are intentionally expired so
the new account status and permissions take effect. No default administrator
password or new production account is created by this migration.

Administrators manage accounts, resources, publication, trash, and activity.
Editors can create, preview, autosave, and revise only their own draft notes.
An administrator reviews and publishes those drafts. An editor cannot access
account management, other editors' drafts, published-note editing, or trash.

Notes and papers moved to trash disappear from public pages, search, counts,
and sitemaps. Notes restored from trash or history return as drafts. A paper
restored from trash becomes public again. Permanent deletion requires typing
`DELETE`; it also removes a note's recovery data and revision history.

The editor saves recovery every eight seconds when changes exist. Recovery is
stored per account and draft in MySQL, never in the published note. Recovered
work is available in the dashboard and the editor. A saved note's version is
checked before updating it to prevent silently overwriting another edit.
