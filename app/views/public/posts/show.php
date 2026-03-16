<?php
require APPROOT . '/views/inc/head.php';
require_once APPROOT . '/lib/share.php';

$post_url = rtrim(URLROOT, '/') . '/posts/' . urlencode((string)($post['slug'] ?? ''));
?>

<div class="post-container" style="width: 100%; margin: 0; padding: 0;">
    
    <?php if (!empty($post['image_path'])): ?>
        <div class="featured-image" style="margin-bottom: 20px;">
            <img src="<?= $post['image_path'] ?>" 
                 style="width: 100%; height: auto; display: block;">
        </div>
    <?php endif; ?>

    <article style="padding: 0; margin: 0;">
        <h1 style="margin: 0 0 10px 0;"><?= $post['title'] ?></h1>

        <?php if (function_exists('share_buttons')): ?>
            <div style="margin: 10px 0 20px 0;">
                <?= share_buttons($post_url, (string)($post['title'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <div class="post-body" style="line-height: 1.6; white-space: pre-wrap;">
            <?= $post['body'] ?>
        </div>
    </article>

    <hr style="margin: 40px 0; border: 0; border-top: 1px solid #333;">

    <section class="replies" style="padding: 0; margin: 0;">
        <h3 style="margin: 0 0 20px 0;">Replies</h3>
        <p><small>Allowed Markdown</small>
<pre>
# text = h1
## text = h2
**text** = strong/bold
*text* = italic/empasized
~~text~~ = small text
*** or --- = a horizontal rule hr
`text` = code (pretty pink text)
replace text with your content
</pre>
</p>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="reply-form" style="margin-bottom: 30px;">
                <form action="/posts/reply" method="POST">
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                    <textarea name="body" required style="width: 100%; height: 100px; padding: 10px; background: #1a1a1a; color: #fff; border: 1px solid #333;"></textarea>
                    <br>
                    <button type="submit" style="margin-top: 10px; padding: 10px 20px; background: #eee; color: #000; border: none; cursor: pointer; font-weight: bold;">Post Reply</button>
                </form>
            </div>
        <?php else: ?>
            <p style="margin-bottom: 30px;">
                Please <a href="/login" style="color: #eee; font-weight: bold;">Login</a> to leave a reply.
            </p>
        <?php endif; ?>

        <?php if (!empty($comments)): ?>
            <?php foreach ($comments as $comment): ?>
                <div class="comment" style="margin-bottom: 30px; border-top: 1px solid #222; padding-top: 15px;">
                    <div style="margin-bottom: 5px;">
                        <strong><?= htmlspecialchars($comment['author_name']) ?></strong>
                        <small style="color: #666; margin-left: 10px;"><?= $comment['created_at'] ?></small>
                    </div>
                    <div style="white-space: pre-wrap; color: #ccc;">
                        <?php 
                        $com = (string)$comment['body'];
                        echo $this->render_md->markdown($com);
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
