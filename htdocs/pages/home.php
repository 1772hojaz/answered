<?php
/**
 * Homepage: subjects, recently added notes and past papers (GFG layout).
 */

$subjects = subjects_with_counts();
$recentNotes = recent_notes(6);
$recentPapers = recent_papers(6);

$GLOBALS['page_title'] = (string) config('app.name');
$GLOBALS['nav_active'] = 'home';
?>
<div class="gfg-layout">
  <div class="gfg-container">
    <?php render_left_nav('home', ''); ?>

    <section class="gfg-content" aria-label="Homepage content">
      <section class="gfg-block">
        <div class="gfg-hero-card">
          <div class="gfg-hero-text">
            <h1>Revise smarter, not harder</h1>
            <p>Clear revision notes and past papers, organised by subject and ready on any device.</p>
            <form class="gfg-search-hero" action="<?= e(form_action('/search')) ?>" method="get" role="search">
              <?= route_field('/search') ?>
              <label class="visually-hidden" for="gfg-hero-search">Search notes and past papers</label>
              <input id="gfg-hero-search" type="search" name="q" placeholder="Search notes, subjects, past papers…" autocomplete="off">
              <button type="submit" class="btn btn-primary">Search</button>
            </form>
          </div>
        </div>
      </section>

      <section class="gfg-block">
        <?php section_heading('Subjects', url('/subjects'), 'All subjects'); ?>
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