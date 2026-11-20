<?php
/**
 * Small reusable view helpers for the public pages.
 *
 * Layout follows a GeeksforGeeks-style architecture: a left category rail,
 * a main content column and a right rail of popular/recommended items.
 */

declare(strict_types=1);

if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/ads.php';

/**
 * @param array[] $links list of ['label' => ..., 'href' => ...] (href optional)
 */
function breadcrumbs(array $links): void
{
    $GLOBALS['seo_breadcrumbs'] = $links;
    echo '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol>';
    $count = count($links);
    foreach ($links as $i => $link) {
        echo '<li>';
        if (!empty($link['href']) && $i < $count - 1) {
            echo '<a href="' . e($link['href']) . '">' . e($link['label']) . '</a>';
        } else {
            echo '<span aria-current="page">' . e($link['label']) . '</span>';
        }
        echo '</li>';
    }
    echo '</ol></nav>';
}

/**
 * Section heading with an optional "view all" link (GFG style).
 */
function section_heading(string $title, ?string $href = null, string $linkLabel = 'View all'): void
{
    echo '<div class="gfg-section-head"><h2 class="gfg-section-title">' . e($title) . '</h2>';
    if ($href !== null) {
        echo '<a class="gfg-section-link" href="' . e($href) . '">' . e($linkLabel) . '</a>';
    }
    echo '</div>';
}

/**
 * Card grid of subjects.
 *
 * @param array[] $subjects
 */
function render_subject_grid(array $subjects): void
{
    if (!$subjects) {
        echo '<p class="empty">No subjects have been added yet.</p>';
        return;
    }

    echo '<div class="gfg-card-grid">';
    foreach ($subjects as $subject) {
        $icon = public_media_url((string) ($subject['icon_path'] ?? ''));
        $letter = mb_strtoupper(mb_substr((string) $subject['name'], 0, 1, 'UTF-8'), 'UTF-8');
        $noteCount = isset($subject['note_count']) ? (int) $subject['note_count'] : null;
        $paperCount = isset($subject['paper_count']) ? (int) $subject['paper_count'] : null;

        echo '<a class="gfg-card" href="' . e(url('/subjects/' . $subject['slug'])) . '">';
        echo '<span class="gfg-card-icon">';
        if ($icon) {
            echo '<img src="' . e($icon) . '" alt="" loading="lazy">';
        } else {
            echo '<span aria-hidden="true">' . e($letter) . '</span>';
        }
        echo '</span>';
        echo '<span class="gfg-card-body">';
        echo '<span class="gfg-card-title">' . e($subject['name']) . '</span>';
        $description = trim((string) ($subject['description'] ?? ''));
        if ($description !== '') {
            echo '<span class="gfg-card-desc">' . e(mb_substr($description, 0, 110, 'UTF-8')) . '</span>';
        }
        if ($noteCount !== null || $paperCount !== null) {
            echo '<span class="gfg-card-meta">'
                . e((string) ($noteCount ?? 0)) . ' note' . (($noteCount ?? 0) === 1 ? '' : 's')
                . ' · ' . e((string) ($paperCount ?? 0)) . ' paper' . (($paperCount ?? 0) === 1 ? '' : 's')
                . '</span>';
        }
        echo '</span>';
        echo '<span class="gfg-card-go" aria-hidden="true">Explore subject &rarr;</span>';
        echo '</a>';
    }
    echo '</div>';
}

/**
 * Compact list of notes as article cards.
 *
 * @param array[] $notes
 */
function render_note_list(array $notes, bool $withExcerpt = false): void
{
    if (!$notes) {
        echo '<p class="empty">No notes published yet.</p>';
        return;
    }

    echo '<ul class="gfg-list">';
    foreach ($notes as $note) {
        echo '<li class="gfg-item">';
        echo '<span class="gfg-item-icon note-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 12h7M9 16h5"/></svg></span>';
        echo '<div class="gfg-item-body">';
        echo '<a class="gfg-item-title" href="' . e(url('/notes/' . $note['slug'])) . '">' . e($note['title']) . '</a>';
        echo '<div class="gfg-item-meta">';
        if (!empty($note['subject_name'])) {
            echo '<a class="chip" href="' . e(url('/subjects/' . $note['subject_slug'])) . '">' . e($note['subject_name']) . '</a>';
        }
        if (!empty($note['topic_name'])) {
            echo '<span class="chip chip-soft">' . e($note['topic_name']) . '</span>';
        }
        echo '<span class="gfg-item-date">' . e(format_date($note['published_at'] ?: ($note['updated_at'] ?? null))) . '</span>';
        echo '</div>';
        if ($withExcerpt && !empty($note['content'])) {
            echo '<p class="gfg-item-excerpt">' . e(excerpt_from_html((string) $note['content'], 165)) . '</p>';
        }
        echo '</div>';
        echo '<span class="gfg-item-arrow" aria-hidden="true">&rsaquo;</span>';
        echo '</li>';
    }
    echo '</ul>';
}

/**
 * List of past papers with open/download actions.
 *
 * @param array[] $papers
 */
function render_paper_list(array $papers): void
{
    if (!$papers) {
        echo '<p class="empty">No past papers have been added yet.</p>';
        return;
    }

    echo '<ul class="gfg-list">';
    foreach ($papers as $paper) {
        $href = url('/past-papers/' . $paper['slug']);
        $meta = [];
        if (!empty($paper['year'])) {
            $meta[] = (string) $paper['year'];
        }
        if (!empty($paper['exam_board'])) {
            $meta[] = $paper['exam_board'];
        }
        if (!empty($paper['paper_number'])) {
            $meta[] = 'Paper ' . $paper['paper_number'];
        }

        echo '<li class="gfg-item">';
        echo '<span class="gfg-item-icon" aria-hidden="true">PDF</span>';
        echo '<div class="gfg-item-body">';
        echo '<a class="gfg-item-title" href="' . e($href) . '">' . e($paper['title']) . '</a>';
        echo '<div class="gfg-item-meta">';
        if ($meta) {
            echo '<span class="chip chip-soft">' . e(implode(' · ', $meta)) . '</span>';
        }
        if (!empty($paper['subject_name'])) {
            echo '<a class="chip" href="' . e(url('/subjects/' . $paper['subject_slug'])) . '">' . e($paper['subject_name']) . '</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '<div class="gfg-item-actions">';
        $paperHref = public_media_url((string) ($paper['file_path'] ?? ''));
        if ($paperHref !== null) {
            echo '<a class="btn btn-sm" href="' . e($paperHref) . '" target="_blank" rel="noopener">View</a>';
            echo '<a class="btn btn-sm btn-primary" href="' . e($paperHref) . '" download>Download</a>';
        }
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';
}

/**
 * Left navigation sidebar (Explore + subject list).
 */
function render_left_nav(string $active = '', string $activeSubject = ''): void
{
    try {
        $subjects = subjects_with_counts();
    } catch (Throwable $e) {
        $subjects = [];
    }
    ?>
    <aside class="gfg-side">
      <nav class="gfg-side-card" aria-label="Sections">
        <h2 class="gfg-side-title">Explore</h2>
        <ul class="gfg-side-list">
          <li><a href="<?= e(url('/')) ?>" class="<?= $active === 'home' ? 'is-active' : '' ?>">Home</a></li>
          <li><a href="<?= e(url('/subjects')) ?>" class="<?= $active === 'subjects' && $activeSubject === '' ? 'is-active' : '' ?>">All Subjects</a></li>
          <li><a href="<?= e(url('/past-papers')) ?>" class="<?= $active === 'papers' ? 'is-active' : '' ?>">Past Papers</a></li>
          <li><a href="<?= e(url('/search')) ?>" class="<?= $active === 'search' ? 'is-active' : '' ?>">Search</a></li>
        </ul>
      </nav>

      <?php if ($subjects): ?>
      <nav class="gfg-side-card" aria-label="Subjects">
        <h2 class="gfg-side-title">Subjects</h2>
        <ul class="gfg-side-list">
          <?php foreach ($subjects as $subject): ?>
            <li>
              <a href="<?= e(url('/subjects/' . $subject['slug'])) ?>" class="<?= $activeSubject === $subject['slug'] ? 'is-active' : '' ?>">
                <span><?= e($subject['name']) ?></span>
                <span class="gfg-count"><?= (int) $subject['note_count'] ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <?php endif; ?>
    </aside>
    <?php
}

/**
 * Right rail with popular subjects and the latest past papers.
 */
function render_right_rail(): void
{
    try {
        $subjects = subjects_with_counts();
        usort($subjects, static function (array $a, array $b): int {
            return ((int) $b['note_count'] <=> (int) $a['note_count']) ?: strcmp((string) $a['name'], (string) $b['name']);
        });
        $subjects = array_slice($subjects, 0, 6);
        $papers = recent_papers(5);
    } catch (Throwable $e) {
        $subjects = [];
        $papers = [];
    }
    ?>
    <aside class="gfg-rail" aria-label="Recommended">
      <?php if ($subjects): ?>
      <section class="gfg-rail-card">
        <h2 class="gfg-rail-title">Popular Subjects</h2>
        <ol class="gfg-rail-list">
          <?php foreach ($subjects as $subject): ?>
            <li>
              <a href="<?= e(url('/subjects/' . $subject['slug'])) ?>"><?= e($subject['name']) ?></a>
              <span class="gfg-rail-count"><?= (int) $subject['note_count'] ?> notes</span>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>
      <?php endif; ?>

      <?php if ($papers): ?>
      <section class="gfg-rail-card">
        <h2 class="gfg-rail-title">Latest Past Papers</h2>
        <ul class="gfg-rail-list">
          <?php foreach ($papers as $paper): ?>
            <li>
              <a href="<?= e(url('/past-papers/' . $paper['slug'])) ?>"><?= e($paper['title']) ?></a>
              <?php if (!empty($paper['subject_name'])): ?>
                <span class="gfg-rail-count"><?= e($paper['subject_name']) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <section class="gfg-rail-card gfg-rail-cta">
        <h2 class="gfg-rail-title">Keep revising</h2>
        <p>Browse every subject and open the notes you need.</p>
        <a class="btn btn-primary btn-block" href="<?= e(url('/subjects')) ?>">Browse subjects</a>
      </section>
      <?php render_ad_placement('skyscraper'); ?>
    </aside>
    <?php
}
