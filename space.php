<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Ο χώρος μας';
$meta_description = 'Ο χώρος του ' . biz_name() . ' — δες φωτογραφίες από το γραφείο μας.';
$photos = [];
try { $photos = get_db()->query("SELECT * FROM gallery ORDER BY sort_order ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
include __DIR__ . '/includes/header.php';
?>

<!-- Φόρτωση του Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<style>
  /* CSS Ειδικά για το αυτόματο Slider */
  .space-gallery-wrapper {
    max-width: 900px;
    margin: 0 auto 40px auto;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 12px 32px rgba(10, 59, 46, 0.1);
  }
  
  .myGallerySwiper {
    width: 100%;
    height: 100%;
    position: relative;
  }
  
  .swiper-slide {
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f8fafc;
    position: relative;
  }
  
  .swiper-slide img {
    display: block;
    width: 100%;
    height: 550px; 
    object-fit: cover; 
  }
  
  .swiper-slide figcaption {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    background: linear-gradient(to top, rgba(10, 59, 46, 0.9), transparent);
    color: #ffffff;
    padding: 30px 20px 15px 20px;
    text-align: center;
    font-size: 1.05em;
    font-weight: 500;
    pointer-events: none;
  }
  
  @media (max-width: 768px) {
    .swiper-slide img { height: 400px; }
  }
</style>

<main>
  <section class="section container">
    <div class="section-head reveal">
      <span class="eyebrow">Ο χώρος μας</span>
      <h1>Ένα ζεστό, φιλόξενο περιβάλλον</h1>
      <p class="lede">Ρίξε μια ματιά στον χώρο όπου θα σε υποδεχτούμε.</p>
    </div>
    
    <?php if (!$photos): ?>
      <p class="blog-empty">Οι φωτογραφίες του χώρου θα προστεθούν σύντομα.</p>
    <?php else: ?>
    
    <div class="space-gallery-wrapper reveal">
      <div class="swiper myGallerySwiper">
        <div class="swiper-wrapper">
          <?php foreach ($photos as $ph): ?>
            <div class="swiper-slide">
              <figure class="gallery-item" style="margin: 0; width: 100%;">
                <img src="<?= e($ph['image_path']) ?>" alt="<?= e($ph['caption'] ?: 'Ο χώρος μας') ?>" loading="lazy">
                <?php if ($ph['caption']): ?>
                  <figcaption><?= e($ph['caption']) ?></figcaption>
                <?php endif; ?>
              </figure>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    
    <?php endif; ?>
  </section>
</main>

<!-- Φόρτωση του Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    if(document.querySelector('.myGallerySwiper')) {
      const swiper = new Swiper('.myGallerySwiper', {
        loop: true, 
        speed: 800, // Απαλή ταχύτητα κίνησης του slide
        allowTouchMove: false, // Απενεργοποιεί το "τράβηγμα" από τον χρήστη
        
        // Κλασική κίνηση καρουζέλ κάθε 1.5 δευτερόλεπτο
        autoplay: {
          delay: 1500, // 1500ms = 1.5 δευτερόλεπτα
          disableOnInteraction: false,
        }
      });
    }
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>