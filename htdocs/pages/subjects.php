<?php
/**
 * All subjects (GeeksforGeeks-style card listing).
 */

$subjects = subjects_with_counts();

$GLOBALS['page_title'] = 'ZIMSEC Subjects - Revision Notes & Past Papers';
$GLOBALS['page_description'] = 'Browse ZIMSEC subjects on Answered. Find revision notes, topic explanations, and past papers to plan your study and prepare for exams.';
$GLOBALS['canonical_url'] = url('/subjects');
$GLOBALS['seo_page_type'] = 'CollectionPage';
$GLOBALS['nav_active'] = 'subjects';
?>
<div class="gfg-layout">
  <div class="gfg-container">
    <?php render_left_nav('subjects'); ?>

    <section class="gfg-content">
      <?php breadcrumbs([['label' => 'Home', 'href' => url('/')], ['label' => 'Subjects']]); ?>
      <header class="gfg-page-head">
        <span class="eyebrow section-eyebrow">YOUR STUDY LIBRARY</span>
        <h1>ZIMSEC Subjects</h1>
        <p class="section-sub">Find your focus. Explore <?= count($subjects) ?> subject<?= count($subjects) === 1 ? '' : 's' ?> with notes and papers in one place.</p>
      </header>
      <?php render_subject_grid($subjects); ?>
    </section>

    <?php render_right_rail(); ?>
  </div>
</div>
