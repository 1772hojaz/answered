<?php
/** Public search, filters, and URL regression checks using an isolated SQLite fixture. */
declare(strict_types=1);
define('APP_BOOTSTRAPPED', true);
define('APP_ROOT', dirname(__DIR__) . '/htdocs');
$settings = ['app.clean_urls' => false, 'app.base_path' => '/study'];
function config(string $key, $default = null) { global $settings; return $settings[$key] ?? $default; }
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
function q_all(string $sql, array $params = []): array {
    global $database;
    $query = $database->prepare($sql); $query->execute($params); return $query->fetchAll();
}
function q_one(string $sql, array $params = []) {
    global $database;
    $query = $database->prepare($sql); $query->execute($params); return $query->fetchColumn();
}
require dirname(__DIR__) . '/htdocs/includes/functions.php';
require APP_ROOT . '/includes/uploads.php';
require APP_ROOT . '/includes/components.php';
$database->exec('CREATE TABLE subjects (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, description TEXT, sort_order INTEGER DEFAULT 0);
CREATE TABLE topics (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE notes (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, content TEXT, subject_id INTEGER, topic_id INTEGER, status TEXT, deleted_at TEXT, level TEXT, syllabus_code TEXT);
CREATE TABLE past_papers (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, description TEXT, subject_id INTEGER, year INTEGER, exam_board TEXT, paper_number TEXT, level TEXT, syllabus_code TEXT, exam_session TEXT, deleted_at TEXT);
INSERT INTO subjects (id,name,slug,description) VALUES (1,"Biology","biology","Living things"),(2,"Mathematics","mathematics","Numbers");
INSERT INTO topics VALUES (1,"Cells");
INSERT INTO notes VALUES (1,"Introduction","intro","Cell structure",1,1,"published",NULL,"O Level","4008"),
(2,"Biology","biology-note","Overview",1,1,"published",NULL,"A Level","5008"),
(3,"Hidden cells","draft","Cell structure",1,1,"draft",NULL,"O Level","4008"),
(4,"Deleted cells","deleted","Cell structure",1,1,"published","2026-01-01","O Level","4008"),
(5,"100% revision_notes","literal","C:\\revision",2,NULL,"published",NULL,"","");');
$insert = $database->prepare('INSERT INTO past_papers (id,title,slug,description,subject_id,year,exam_board,paper_number,level,syllabus_code,exam_session,deleted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
for ($id = 1; $id <= 24; $id++) {
    $insert->execute([$id,'Practice','paper-'.$id,'Questions',1,2024,'ZIMSEC','1','O Level','4008','June',null]);
}
$insert->execute([25,'Mathematics practice','math','Questions',2,2023,'ZIMSEC','2','A Level','6042','November',null]);
$insert->execute([26,'Deleted paper','deleted','Questions',1,2025,'ZIMSEC','1','O Level','4008','June','2026-01-01']);

$results = search_all('  Biology   cells  ');
check(count($results['notes']) === 2, 'search words can match subject and topic independently');
check(count(search_all('Biology 2024 June')['papers']) === 24, 'paper search combines subject, year, and session');
check(count(search_all('4008 O Level')['notes']) === 1, 'classification is searchable while drafts and deleted notes stay private');
check(search_all('Biology')['notes'][0]['id'] === 2, 'title matches appear before broader subject matches');
foreach (['100%', 'revision_', 'C:\\revision'] as $term) {
    check(count(search_all($term)['notes']) === 1, 'literal search characters work: ' . $term);
}
check(search_all('')['notes'] === [], 'empty searches return no results');
check(count(search_all('Biology', -5)['notes']) === 1, 'search limits are bounded');
check(search_all("%' OR 1=1 --")['notes'] === [], 'search input stays in bound parameters');

list($first, $total) = all_papers_paginated(1, 20, ['subject' => 1, 'year' => '2024', 'level' => 'O Level']);
list($second) = all_papers_paginated(2, 20, ['subject' => 1, 'year' => '2024', 'level' => 'O Level']);
check($total === 24 && count($first) === 20 && count($second) === 4, 'combined filters count and paginate matching papers');
check(!array_intersect(array_column($first,'id'),array_column($second,'id')), 'pagination has no repeated papers with identical metadata');
check(all_papers_paginated(1,20,['subject'=>2,'year'=>'2024'])[1] === 0, 'nonmatching filters return an empty result');
check(array_column(paper_years(),'year') === [2024,2023], 'year choices omit deleted papers');
check(excerpt_from_html('<p>Cells &amp; tissues</p><p>100&nbsp;percent<br>ready.</p>') === 'Cells & tissues 100 percent ready.', 'excerpts decode entities and separate paragraphs and line breaks');

$_GET = ['q'=>['unexpected'], 'page'=>['2'], 'p'=>['/search']];
check(query_string('q') === '' && query_int('page') === 1 && current_path() === '', 'array query parameters fall back safely');
$_GET = ['page'=>'999999999999999999999'];
check(query_int('page') === 1, 'huge page numbers cannot overflow pagination');
$_GET = [];
$_SERVER['REQUEST_URI'] = '/study-other/subjects';
check(current_path() === 'study-other/subjects', 'routing respects installation subfolder boundaries');
$_SERVER['REQUEST_URI'] = '/study/subjects';
check(current_path() === 'subjects', 'subfolder routes still resolve');

// Render the actual templates against the fixture, treating PHP warnings as failures.
set_error_handler(static function (int $severity, string $message): void { throw new RuntimeException($message); });
$settings['ads.enabled'] = false;
$database->exec('ALTER TABLE past_papers ADD COLUMN created_at TEXT; ALTER TABLE past_papers ADD COLUMN file_path TEXT;');
function render_page(string $template): string {
    ob_start(); require APP_ROOT . '/pages/' . $template . '.php'; return ob_get_clean();
}
$_GET = ['subject'=>'1','year'=>'2024','level'=>'O Level','page'=>'2'];
$html = render_page('past_papers');
check(strpos($html,'subject=1&amp;year=2024&amp;level=O+Level&amp;page=1') !== false, 'rendered pagination preserves all selected filters');
check(substr_count($html,' selected') === 3 && strpos($html,'Page 2 of 2') !== false, 'rendered filter selections and page status stay in sync');
check($GLOBALS['page_noindex'] === true, 'filtered collections are not indexed as duplicate pages');
$_GET = ['subject'=>'2','year'=>'2024'];
check(strpos(render_page('past_papers'),'No papers match these filters') !== false, 'filtered empty states explain how to recover');
$_GET = ['q'=>['unexpected']];
check(strpos(render_page('search'),'Type a keyword') !== false, 'search renders safely for malformed query parameters');
for ($id=10; $id<40; $id++) {
    $database->exec("INSERT INTO notes (id,title,slug,content,subject_id,topic_id,status) VALUES ($id,'Biology $id','biology-$id','Cells',1,1,'published')");
}
$_GET = ['q'=>'Biology'];
$html = render_page('search');
check(strpos($html,'More matches are available') !== false && strpos($html,'Showing ') !== false, 'capped search results do not claim to be a complete count');
restore_error_handler();
echo "All public resource checks passed.\n";
