<?php require APPROOT . '/views/inc/head.php';

// Holday Message
if (!empty($data['holiday_message'])) {
  echo '<div class="row">';
  echo '<section class="home-holiday-wrap">';
  echo '<h3 class="home-holiday">' . $data['holiday_message'] . '</h3>';
  echo '</section>';
  echo '</div>';
}

$featuredAnnouncement = $data['featured_announcement'] ?? false;
$hasFeaturedAnnouncement = is_array($featuredAnnouncement);
?>
<div class="row">
<section>
<div class="content-wrap">
  <img src="<?= theme::assetUrl('icons/icon.png') ?>" class="wrap-left" alt="Poe Mei">
  <h2>Greetings and Welcome</h2>
  <p>To Poe Mei dot Com</p>
  <p><em>"We met for a reason, either you're a blessing or a lesson" - Tu Pac</em></p>

  <p>
    This is my little corner of the Internet.
  </p>

  <p>
    It is messy, occasionally ridiculous, perpetually under construction,
    and unmistakably mine.
  </p>
</div>
</section>
</div>

<div class="row">
<?php 
// Announcements
if(isset($data['featured_announcement']) && $data['featured_announcement'] !== false) : 
$post = $data['featured_announcement']; ?>
<section id="latest-announcement">
    <div class="announcement-content">
        <h3><?php echo htmlspecialchars($post['title']); ?></h3>
        <p><?= (string) ($featuredAnnouncement['body'] ?? ''); ?></p>
        <small>Posted: <?php echo date('Y.m.d', strtotime($post['created_at'])); ?></small>
    </div>
</section>
<?php endif; ?>
</div>

<div class="row">
<section>
<div class="content-wrap">
  <img src="<?= theme::assetUrl('img/me_sl.png') ?>" class="wrap-right" alt="I dont fucking care">
  <?php
  $about_me = "
## A little about me

Hi. I'm **Poe Mei**.

I'm messy, but not careless.

Ridiculous, but not frivolous.

Serious about the things that matter, but allergic to taking myself too seriously.

I'm curious enough to wander into weird places and skeptical enough not to believe everything I find there.

I'm witchy, but no hocus-pocus, ever.

Spiritual, but not dogmatic, cause religion is control cloaked in salvation.

I'm a **Transgender Woman**. That's part of who I am, not everything there is to know about me.

I make things. I break things. Sometimes I spend entirely too much time making something only to look at it and decide, *nah, that ain't it*, and start over.

I don't need to know everything.

I'd rather say **I don't know** and go find out than pretend certainty I haven't earned.

This site reflects that.

There will be development stuff here because I develop things. There will be opinions because occasionally I have those too. There will be strange rabbit holes, unfinished ideas, things that make me laugh, things I care deeply about, and probably something broken because I was fucking with it again.

This isn't a corporate homepage.

It's mine.

So wander around.

You might find something interesting.

> **REMEMBER:** Hate is a **THEM** problem, their hatred of you is not **YOUR** problem.

Don't be a **THEM**, K? *Snowflake*?

---
  ";

  echo $this->render_md->markdown($about_me);
  ?>
</div>
</section>
</div>

<div class="row">
<section>
<div class="content-wrap">
  <img src="<?= theme::assetUrl('img/pm_developers.png') ?>" class="wrap-left" alt="Girlie Witchy Developers">
<?php
$recruiting = "
## Sometimes I Build Shit

Development is one of the things I do, and this website is one of the things I've built.

It changes.

Sometimes intentionally.

Sometimes because I touched something I probably should have left the fuck alone.

If you're interested in the development side of what I do, you can poke around there too.
";
echo $this->render_md->markdown($recruiting);
?>

<p>
  Check out the <a href="/changelog">Changelog</a>,
  take a look at <a href="/recruiter">Recruiting</a>,
  or wander into the <a href="/developer">Developers Portal</a> and learn a bit.
</p>
</div>
</section>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>