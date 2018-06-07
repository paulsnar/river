---
mediatype = "text/html; charset=UTF-8"
---
<!DOCTYPE html>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width">
<title>Frontpage – River</title>
<link rel="stylesheet" href="<?= url('/assets/style.css') ?>">
<div class="container">
  <h1><?= _('svaigas ziņas') ?></h1>
  <?php if (count($entries) > 0) { ?>
    <div class="feed-container">
      <?php foreach ($entries as $entry) { ?>
        <?php
          $len = 170 - mb_strlen($entry['title']);
          if ($len < 0) {
            $content = '';
          } else {
            $content = truncate($entry['content'], $len);
          }

          $feed = $feeds_map[$entry['of_feed']];
        ?>
        <a class="feed-entry<?= $feed['is_standout'] ? ' standout' : '' ?>"
            href="<?= he($entry['link']) ?>"
            data-id="<?= he($entry['_id']) ?>">
          <span class="feed-entry-publisher">
            <?= $feed['name'] ?>
          </span>
          <span class="feed-entry-timestamp"
              data-timestamp="<?= $entry['published_at'] ?>">
            <?= shortdate($entry['published_at']) ?>
          </span>
          <span class="feed-entry-title"><?= he($entry['title']) ?></span>
          <span class="feed-entry-summary"><?= he($content) ?></span>
        </a>
      <?php } ?>
    </div>
  <?php } else { ?>
    <p class="system-msg">
      Diemžēl nekādas ziņas neizdevās atrast.
    </p>
  <?php } ?>
</div>

<?php if (count($entries) > 0) { ?>
  <script src="<?= url('/assets/vendor/zepto.js') ?>"></script>
  <script src="<?= url('/assets/vendor/underscore.js') ?>"></script>
  <script src="<?= url('/assets/vendor/backbone.js') ?>"></script>
  <script src="<?= url('/assets/common.js') ?>"></script>
  <script src="<?= url('/frontend/config.js') ?>"></script>
  <script src="<?= url('/assets/frontpage.js') ?>"></script>
  <script type="application/json" id="rvr-fp-feeds">
    <?= json($feeds) ?>
  </script>
  <script type="application/json" id="rvr-fp-entries">
    <?= json($entries) ?>
  </script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      River.Frontpage.attach($('.container'), {
        feeds: JSON.parse($('#rvr-fp-feeds').text()),
        entries: JSON.parse($('#rvr-fp-entries').text()),
      })
    })
  </script>
<?php } ?>
