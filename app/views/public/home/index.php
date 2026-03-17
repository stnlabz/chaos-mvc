<?php require APPROOT . '/views/inc/head.php'; ?>

<div class="container">

  <div class="row">
    <section>
      <h1>Chaos MVC</h1>
      <em>A disciplined PHP MVC framework built for clarity and control</em>.
      <p>
        <a class="btn btn-primary" href="/github" role="button">Get Started</a>
      </p>
      <h2>Minimal Core Philosophy</h2>
      <p>Chaos MVC intentionally maintains a very small footprint.</p>
      <p>The framework provides only the essential components required to operate an MVC architecture.</p>
      <p>Developers are encouraged to extend their applications through modules or project-specific code rather than expanding the core framework itself.</p>
      <p>This approach keeps the framework fast, understandable, and maintainable over time.</p>
    </section>
      <hr>
  </div>
  
  <div class="row">
  <?php 
  // Announcements
  if(isset($data['featured_announcement']) && $data['featured_announcement'] !== false) : 
      $post = $data['featured_announcement']; 
  ?>

    <section id="latest-announcement">

      <div class="announcement-content">

        <h3><?= htmlspecialchars($post['title']); ?></h3>

        <p>
          <?= nl2br(htmlspecialchars($post['body'])); ?>
        </p>

        <small>
          Posted: <?= date('Y.m.d', strtotime($post['created_at'])); ?>
        </small>

      </div>

    </section>

  <?php endif; ?>

  </div>

<div class="row">
      <p>
        Chaos MVC is a lightweight PHP MVC framework built around discipline,
        traceability, and architectural simplicity.
      </p>

      <p>
        This platform follows the traditional <strong>MVC</strong> pattern:
      </p>

      <ul>
        <li>
          <strong>Model</strong> — handles all database interaction and data logic.
        </li>

        <li>
          <strong>View</strong> — responsible for presentation and rendering output.
        </li>

        <li>
          <strong>Controller</strong> — coordinates requests, directing traffic between
          models and views.
        </li>
      </ul>

      <p>
        Chaos MVC focuses on predictable architecture, strict development discipline,
        and a minimal code footprint.
      </p>

      <hr>

      <h3>Developer Resources</h3>

      <ul>
        <li><a href="/developer">Developer Portal</a></li>
        <li><a href="/developer/flow">MVC Architecture</a></li>
        <li><a href="/developer/example">Example Module</a></li>
        <li><a href="/developer/rules">Development Rules</a></li>
      </ul>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
