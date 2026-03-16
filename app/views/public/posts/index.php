<?php require APPROOT . '/views/inc/head.php'; ?>

<div class="public-container" style="max-width: 1000px; margin: 0 auto; padding: 40px 20px;">
    <h1 style="margin-bottom: 30px; font-size: 2.5rem;">Latest Posts</h1>
    <hr style="margin-bottom: 40px; border: 0; border-top: 1px solid #eee;">

    <div class="post-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px;">
        <?php foreach($data['items'] as $item): ?>
            <div class="post-card" style="background: #fff; border: 1px solid #e1e4e8; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s;">
                
                <?php if(!empty($item['image_path'])): ?>
                    <div style="width: 100%; height: 200px; overflow: hidden; background: #f6f8fa;">
                        <img src="<?= $item['image_path'] ?>" 
                             alt="<?= $item['title'] ?>" 
                             style="width: 100%; height: 100%; object-fit: cover; display: block;">
                    </div>
                <?php else: ?>
                    <div style="width: 100%; height: 200px; background: #f6f8fa; display: flex; align-items: center; justify-content: center; color: #cbd5e0;">
                        <span>No Image</span>
                    </div>
                <?php endif; ?>
                
                <div style="padding: 20px; flex-grow: 1; display: flex; flex-direction: column;">
                    <h2 style="font-size: 1.5rem; margin-bottom: 10px; color: #1a1a1a;"><?= $item['title'] ?></h2>
                    <p style="color: #4a5568; line-height: 1.5; margin-bottom: 20px; flex-grow: 1;">
                        <?= substr(strip_tags($item['body']), 0, 120) ?>...
                    </p>
                    <a href="/posts/<?= $item['slug'] ?>" 
                       style="display: inline-block; text-decoration: none; color: #0366d6; font-weight: 600;">
                       Read More &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
