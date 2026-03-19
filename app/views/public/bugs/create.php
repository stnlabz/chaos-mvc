<?php require APPROOT . '/views/inc/head.php'; ?>
<div class="container">
  <div class="row">
    <section class="bug-create" style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <header style="border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 30px;">
        <h2>Report new issue</h2>
        <p style="font-size: 0.9em; color: #666;">Provide a concise title and detailed description of the behavior.</p>
    </header>

    <form action="/bugs/create" method="post" style="display: flex; flex-direction: column; gap: 20px;">
        <div class="form-group">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Issue title</label>
            <input type="text" name="title" required placeholder="e.g., router failing on specific hex hash" 
                   style="width: 100%; padding: 10px; border: 1px solid #333; font-family: inherit;">
        </div>

        <div class="form-group">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Description</label>
            <textarea name="description" required placeholder="steps to reproduce, expected vs actual behavior..." 
                      style="width: 100%; height: 200px; padding: 10px; border: 1px solid #333; font-family: inherit;"></textarea>
        </div>

        <div class="form-actions" style="border-top: 1px solid #eee; padding-top: 20px;">
            <button type="submit" style="padding: 10px 30px; background: #333; color: #fff; border: 0; cursor: pointer; font-weight: bold;">
                Submit report
            </button>
            <a href="/bugs" style="margin-left: 15px; color: #666; text-decoration: none; font-size: 0.9em;">Cancel</a>
        </div>
    </form>
</section>
  </div>
</div>
<?php require APPROOT . '/views/inc/foot.php'; ?>
