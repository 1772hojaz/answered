<?php
/**
 * Deployment configuration template.
 *
 * Copy this file to config/config.php (same directory) and edit the values.
 * config/config.php is the only file that needs to be edited for deployment.
 */
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'your_database_name',
        'user'    => 'your_database_user',
        'pass'    => 'your_database_password',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        // Private random setup token (at least 32 characters); empty disables setup.
        // Clear this value after creating the administrator.
        'install_token' => '',

        // Platform name shown in the header and page titles.
        'name' => 'RevisionHub',

        // Canonical origin only (scheme and host); use base_path below for subfolders.
        'site_url' => 'https://answered.qzz.io',
        // Set false for staging and local previews to prevent search indexing.
        'indexable' => true,

        // URL path where the application is installed.
        // '' when installed at the domain root, '/revision' inside a subfolder.
        'base_path' => '',

        // false -> /index.php?p=/subjects/mathematics (works on every host,
        //          including plain `php -S`, no mod_rewrite required)
        // true  -> clean URLs such as /subjects/mathematics (uses the
        //          RewriteBlock in .htaccess; needs mod_rewrite)
        'clean_urls' => false,

        // Set to true when the site is served over HTTPS so that
        // session cookies are marked Secure.
        'https' => false,

        // Secret folder name of the admin area. This MUST match the real
        // folder on the server (rename the `admin/` folder to this value).
        // Keep it long and random so the panel cannot be found by guessing.
        'admin_path' => 'manage-8f3ffdb330600372',
    ],

    // Adcash Display/Banner zones for 160x600 desktop sidebar placements.
    // AutoTag zone IDs cannot be used here. Empty values disable the slots.
    'ads' => [
        'left_zone_id' => '',
        'right_zone_id' => '',
    ],

    // Images and past-paper PDFs are stored as external links in the
    // database; nothing is uploaded to this server, so there is no upload
    // directory or size limit to configure.
];
