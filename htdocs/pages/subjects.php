<?php
/**
 * All subjects (GeeksforGeeks-style card listing).
 */

$subjects = subjects_with_counts();

$GLOBALS['page_title'] = 'Subjects';
$GLOBALS['nav_active'] = 'subjects';
?>
<div class="gfg-layout">
  <div class="gfg-container">
    <?php render_left_nav('subjects'); ?>

    <section class="gfg-content">
      <?php breadcrumbs([['label' => 'Home', 'href' => url('/')], ['label' => 'Subjects']]); ?>
      <header class="gfg-page-head">
        <h1>Subjects</h1>
        <p class="section-sub"><?= count($subjects) ?> subject<?= count($subjects) === 1 ? '' : 's' ?> available</p>
      </header>
      <?php render_subject_grid($subjects); ?>
    </section>

    <?php render_right_rail(); ?>
  </div>
</div>