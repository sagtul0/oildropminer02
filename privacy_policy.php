<?php
include 'header.php'; // شامل ناوبر و استایل‌ها
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Privacy Policy - Oil Drop Miner</title>
  <!-- لینک‌های CSS و Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
    body {
      background: url('assets/images/oil_rig_background.jpg') no-repeat center center fixed;
      background-size: cover;
      color: #ffffff !important; /* سفید برای خوانایی روی پس‌زمینه تیره */
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      min-height: 100vh; /* حداقل ارتفاع صفحه برای نمایش کامل */
      overflow-x: hidden; /* جلوگیری از اسکرول افقی غیرضروری */
    }
    .container {
      max-width: 1000px;
      margin-top: 100px !important; /* افزایش مارجین برای فاصله بیشتر از ناوبر */
      padding-top: 20px !important; /* پدینگ بالا برای فاصله داخلی */
      padding-bottom: 40px !important; /* پدینگ پایین برای فاصله از فوتر */
      background: rgba(20, 20, 20, 0.9); /* پس‌زمینه نیمه‌شفاف برای خوانایی */
      border: 2px solid #D4A017; /* حاشیه طلایی */
      border-radius: 15px;
      padding: 10px; /* کاهش پدینگ کلی برای ارتفاع کمتر */
      box-shadow: 0 5px 15px rgba(212, 160, 23, 0.3);
      overflow-y: auto; /* فعال کردن اسکرول عمودی */
      max-height: 70vh; /* حداکثر ارتفاع برای اسکرول */
      position: relative; /* برای مدیریت لایه‌بندی */
      z-index: 1; /* بالاتر از particles.js */
    }
    .policy-text {
      color: #ffffff !important; /* سفید برای تضمین خوانایی */
      font-size: 1.0rem !important; /* اندازه فونت برای خوانایی */
      line-height: 1.4; /* کاهش ارتفاع خطوط برای فضای کمتر */
    }
    .policy-header {
      color: #ffcc00 !important; /* طلایی روشن برای عنوان‌ها */
      font-weight: bold;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
      font-size: 1.0rem !important; /* اندازه فونت عنوان */
      margin-bottom: 10px !important; /* کاهش فاصله زیر عنوان */
      padding: 5px 0; /* پدینگ عنوان */
    }
    /* تنظیم ناوبر و فوتر مشابه دیگر صفحات */
    .navbar {
      height: 60px !important;
      background-color: #1a1a1a !important;
      position: fixed; /* ناوبر ثابت در بالای صفحه */
      top: 0;
      width: 100%;
      z-index: 1000; /* بالاتر از تمام المان‌ها */
    }
    .navbar-brand {
      font-size: 1.1rem !important;
      color: #ffcc00 !important;
      font-weight: bold;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7);
    }
    .nav-link {
      font-size: 1rem !important;
      color: #ffffff !important;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    }
    .nav-link:hover { color: #ffcc00 !important; }
    footer {
      position: fixed !important; /* فوتر ثابت در پایین صفحه */
      bottom: 0 !important;
      width: 100% !important;
      background-color: #1a1a1a !important;
      padding: 10px 0 !important;
      z-index: 1; /* بالاتر از particles.js */
    }
    footer p {
      color: #ffffff !important;
      font-size: 0.9rem !important;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    }
    footer a {
      color: #ffcc00 !important;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    }
    #particles-js {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: -1; /* زیر تمام المان‌ها قرار می‌گیره */
    }
  </style>
</head>
<body>
  <!-- کانتینر برای افکت خطوط -->
  <div id="particles-js"></div>

  <div class="container mt-5">
    <h1 class="policy-header">Privacy Policy</h1>
    <p class="policy-text">Last updated: February 28, 2025</p>
    <p class="policy-text">At Oil Drop Miner, we value your privacy and are committed to protecting the personal information you provide when using our services. This Privacy Policy outlines how we collect, use, and protect your information.</p>

    <h2 class="policy-header">1. Information We Collect</h2>
    <p class="policy-text">We may collect the following types of information:</p>
    <ul class="policy-text">
      <li>Personal Information: When you register on our platform, we may ask for personal information such as your username, TON wallet address, and other details provided during registration.</li>
      <li>Usage Data: We collect information on how you use our platform, including IP addresses, browser types, access times, pages visited, and click data for mining oil drops.</li>
      <li>Cookies and Tracking Technologies: We use cookies and similar technologies to enhance your experience on our website and track user interactions.</li>
    </ul>

    <h2 class="policy-header">2. How We Use Your Information</h2>
    <p class="policy-text">We use the information we collect for the following purposes:</p>
    <ul class="policy-text">
      <li>To provide and improve our services: We use your information to maintain and enhance your user experience on Oil Drop Miner.</li>
      <li>To communicate with you: We may send you updates, promotions, and other communications related to our services via email or in-app notifications.</li>
      <li>For security purposes: Your information helps us ensure the security of our platform, protect against unauthorized access, and prevent fraud.</li>
    </ul>

    <h2 class="policy-header">3. Data Security</h2>
    <p class="policy-text">We take your privacy and the security of your data seriously. We implement industry-standard security measures, including encryption and secure storage, to protect your personal information from unauthorized access, alteration, or disclosure. However, please note that no security system is completely foolproof.</p>

    <h2 class="policy-header">4. Sharing Your Information</h2>
    <p class="policy-text">We do not sell, rent, or trade your personal information to third parties. We may share your information with trusted third parties only in the following circumstances:</p>
    <ul class="policy-text">
      <li>With your explicit consent.</li>
      <li>To comply with legal obligations, enforce our policies, or protect our rights.</li>
      <li>To service providers who assist us in operating our platform (e.g., payment processors, analytics providers), under strict confidentiality agreements.</li>
    </ul>

    <h2 class="policy-header">5. Your Rights</h2>
    <p class="policy-text">You have the following rights regarding your personal information:</p>
    <ul class="policy-text">
      <li>Access: You can request access to the information we have collected about you by contacting us.</li>
      <li>Correction: You can update or correct your personal information through your account settings or by contacting us.</li>
      <li>Deletion: You can request the deletion of your personal information, subject to certain legal limitations, by emailing us at support@oildropminer.com.</li>
    </ul>

    <h2 class="policy-header">6. Cookies</h2>
    <p class="policy-text">We use cookies to improve your experience on our platform. Cookies are small files stored on your device that help us remember your preferences, track your interactions, and provide a more personalized experience. You can control cookie settings through your browser preferences or opt-out of non-essential cookies via our website settings.</p>

    <h2 class="policy-header">7. Third-Party Links</h2>
    <p class="policy-text">Our website may contain links to third-party sites, such as social media platforms or payment processors. We are not responsible for the privacy practices or content of these external sites. We encourage you to read the privacy policies of any third-party sites you visit.</p>

    <h2 class="policy-header">8. Changes to This Privacy Policy</h2>
    <p class="policy-text">We reserve the right to update this Privacy Policy at any time to reflect changes in our practices or legal requirements. When we make changes, we will update the "Last updated" date at the top of the policy and notify users via email or in-app notifications. We encourage you to review this policy periodically to stay informed about how we are protecting your information.</p>

    <h2 class="policy-header">9. Contact Us</h2>
    <p class="policy-text">If you have any questions, concerns, or requests regarding this Privacy Policy or our data practices, please contact us at:</p>
    <ul class="policy-text">
      <li>Email: support@oildropminer.com</li>
      <li>Address: 123 Oil Street, Mining City, MC 12345</li>
    </ul>
  </div>

  <?php include 'footer.php'; ?>

  <!-- لینک JS Bootstrap و افکت خطوط -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
  <script>
    // افکت خطوط طلایی براق
    particlesJS("particles-js", {
      "particles": {
        "number": { "value": 50, "density": { "enable": true, "value_area": 800 } },
        "color": { "value": "#D4A017" }, // طلایی غنی برای حس ثروت
        "shape": { "type": "line", "stroke": { "width": 2, "color": "#D4A017" } },
        "opacity": { 
          "value": 0.8, 
          "random": true,
          "anim": { "enable": true, "speed": 1, "opacity_min": 0.5 }
        },
        "size": { "value": 0 }, // اندازه ذرات صفر می‌ذاریم چون خطوط داریم
        "line_linked": { 
          "enable": true, 
          "distance": 150, 
          "color": "#D4A017", 
          "opacity": 0.8, 
          "width": 2 
        },
        "move": {
          "enable": true,
          "speed": 2, // سرعت ملایم برای حس لوکسی
          "direction": "random", // حرکت تصادفی برای خطوط براق
          "random": true,
          "straight": false,
          "out_mode": "out",
          "bounce": false,
          "attract": { "enable": false }
        }
      },
      "interactivity": {
        "detect_on": "canvas",
        "events": { "onhover": { "enable": true, "mode": "repulse" }, "onclick": { "enable": false } }
      },
      "retina_detect": true
    });
  </script>
</body>
</html>