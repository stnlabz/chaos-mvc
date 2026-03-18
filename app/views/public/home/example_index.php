<?php require APPROOT . '/views/inc/head.php'; ?>

<div class="container">

  <div class="row">
    <section>
      <h1>Site Name</h1>
      <em>Site slogan</em>.
      <p>
        <a class="btn btn-primary" href="#" role="button">Some action</a>
      </p>
      <h2>Title</h2>
      <p>Context</p>
    </section>
      <hr>
  </div>

<div class="row">
      <p>
        You could even echo the data from the controller where <code>data</code> is the <code>array</code> created in the controller.
        <?= htmlspecialchars($data['title']) ?>
      </p>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
