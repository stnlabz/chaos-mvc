<nav>
  <div class="nav-container">
    <a href="/"><img alt="Chaos MVC" src="/assets/icons/icon.svg" height="80" width="80"></a>
    <ul>
      <li><a href="/">Home</a></li>
      <li><a href="/developer">Developer</a></li>
      <li><a href="/downloads">Download</a></li>
      <li><a href="/certification">Get Certified</a></li>
      <li><a href="/github">Quick Start</a></li>
      <li><a href="/posts">Updates</a></li>
      
      <li class="dropdown">
        <a href="javascript:void(0)" class="dropbtn">System ▾</a>
        <ul class="dropdown-content">
          <li><a href="/roadmap">Progress</a></li>
          <li><a href="/usage_sites">Usage Sites</a></li>
          <li><a href="/capabilities">Capabilities</a></li>
          <li><a href="/changelog">Changelog</a></li>
          <li><a href="/bugs">Issues</a></li>
        </ul>
      </li>

      <?php if (isset($_SESSION['user_id'])): ?>
          <?php if (isset($_SESSION['user_level']) && $_SESSION['user_level'] >= 9): ?>
              <li><a href="/admin" class="btn btn-sm btn-outline-primary">Admin</a></li>
          <?php endif; ?>
          
          <li><a href="/logout" style="color: #666; text-decoration: none;">Logout</a></li>
      <?php else: ?>
          <li><a href="/login">Login</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>
