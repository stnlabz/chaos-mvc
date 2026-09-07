<?php require APPROOT . '/views/inc/head.php';

?>
<div class="row">
<section>
<div class="content-wrap">
  <img src="<?= theme::assetUrl('icons/icon.png') ?>" class="wrap-left" alt="<?= $SITE['name'];?>">
  <h2>Greetings and Welcome</h2>
  <p>To <?= $SITE['name'];?></p>
  

  <p>
    This is the ChAoS MVC Default home page. Please edit this file in <code>/user/modules/home/views/index.php</code>
  </p>

  <p>
    We do hope you enjoy your site, powered by the <A href="https://www.chaos-mvc.org" target="_blank">ChAoS MVC</a>.
  </p>
</div>
</section>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>