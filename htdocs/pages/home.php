<?php
/**
 * Homepage: subjects, recently added notes and past papers (GFG layout).
 */

$subjects = subjects_with_counts();
$recentNotes = recent_notes(6);
$recentPapers = recent_papers(6);

$GLOBALS['page_title'] = 'ZIMSEC Revision Notes & Past Papers';
$GLOBALS['page_description'] = 'Prepare for ZIMSEC exams with revision notes and past papers on Answered. Find your subject, understand key topics, and practise at your own pace.';
$GLOBALS['canonical_url'] = url('/');
$GLOBALS['nav_active'] = 'home';
$GLOBALS['body_class'] = 'page-home';
?>
<div class="gfg-container home-intro">
  <section class="study-hero" aria-labelledby="home-title">
    <div class="study-hero-copy">
      <span class="eyebrow"><span class="status-dot" aria-hidden="true"></span> ZIMSEC revision. A little more confidence.</span>
      <h1 id="home-title">Your next chapter<br>starts <em>here.</em></h1>
      <p>Make sense of the tricky topics. Find ZIMSEC revision notes, practise with past papers, and take your next step with confidence.</p>
      <form class="gfg-search-hero" action="<?= e(form_action('/search')) ?>" method="get" role="search">
        <?= route_field('/search') ?>
        <label class="visually-hidden" for="gfg-hero-search">Search notes, subjects and past papers</label>
        <input id="gfg-hero-search" type="search" name="q" placeholder="What are you learning today?" autocomplete="off">
        <button type="submit" class="btn btn-primary">Find resources <span aria-hidden="true">&rarr;</span></button>
      </form>
      <div class="hero-shortcuts"><span>Start exploring</span><a href="<?= e(url('/subjects')) ?>">Revision notes &nearr;</a><a href="<?= e(url('/past-papers')) ?>">Past papers &nearr;</a></div>
    </div>
    <div class="study-art" aria-hidden="true">
      <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
      <span class="art-spark spark-one">✦</span><span class="art-spark spark-two">✦</span>
      <div class="art-note"><span class="art-note-label">ONE TOPIC AT A TIME</span><strong>Small steps.<br>Big possibilities.</strong><div class="art-rule"></div><div class="art-rule short"></div><span class="art-note-check">✓</span></div>
      <div class="art-book book-back"><span>PRACTISE</span></div>
      <div class="art-book book-front"><span>LEARN. UNDERSTAND. REPEAT.</span><strong>Aa</strong><i></i></div>
      <div class="art-caption"><span>✓</span> A clearer way to learn</div>
    </div>
  </section>
  <div class="resource-strip" aria-label="Study resources">
    <div><strong><?= count($subjects) ?></strong><span>subjects to explore</span></div>
    <div><strong><?= array_sum(array_column($subjects, 'note_count')) ?></strong><span>revision notes</span></div>
    <div><strong><?= array_sum(array_column($subjects, 'paper_count')) ?></strong><span>past papers</span></div>
    <p>Find your subject.<br><strong>Build your understanding.</strong></p>
  </div>
</div>
<div class="gfg-layout">
  <div class="gfg-container">
    <?php render_left_nav('home', ''); ?>

    <section class="gfg-content" aria-label="Homepage content">
      <section class="gfg-block">
        <span class="eyebrow section-eyebrow">FIND YOUR FOCUS</span>
        <?php section_heading('What will you learn today?', url('/subjects'), 'All subjects'); ?>
        <?php render_subject_grid($subjects); ?>
      </section>

      <section class="gfg-block">
        <?php section_heading('Recently added notes'); ?>
        <?php render_note_list($recentNotes, true); ?>
      </section>

      <section class="gfg-block">
        <?php section_heading('Recently added past papers', url('/past-papers'), 'All past papers'); ?>
        <?php render_paper_list($recentPapers); ?>
      </section>
    </section>

    <?php render_right_rail(); ?>
  </div>
</div>
