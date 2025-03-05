<?php
include 'header.php'; // شامل ناوبر و استایل‌ها
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Terms & Conditions - Oil Drop Miner</title>
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
    .terms-text {
      color: #ffffff !important; /* سفید برای تضمین خوانایی */
      font-size: 1.0rem !important; /* اندازه فونت برای خوانایی */
      line-height: 1.4; /* کاهش ارتفاع خطوط برای فضای کمتر */
    }
    .terms-header {
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
    <h1 class="terms-header">Terms & Conditions</h1>
    <p class="terms-text">Last updated: February 28, 2025</p>
    <p class="terms-text">Welcome to Oil Drop Miner! These Terms & Conditions govern your use of our website and services. By accessing or using Oil Drop Miner, you agree to comply with and be bound by these terms. Please read them carefully.</p>

    <h2 class="terms-header">1. Acceptance of Terms</h2>
    <p class="terms-text">By registering, mining oil drops, purchasing plans, or using any part of our services, you agree to these Terms & Conditions. If you do not agree, please do not use our platform.</p>

    <h2 class="terms-header">2. User Accounts</h2>
    <p class="terms-text">You must register an account to use Oil Drop Miner. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>

    <h2 class="terms-header">3. Mining and Rewards</h2>
    <p class="terms-text">Oil drops are virtual rewards earned through gameplay (manual clicks, auto clickers, or boosts). Rewards are subject to daily limits (e.g., 1000 clicks/day) and may be adjusted at our discretion. Oil drops have no real-world monetary value and cannot be exchanged for cash.</p>

    <h2 class="terms-header">4. Purchases and Payments</h2>
    <p class="terms-text">Purchases of boost plans or auto clickers are made using TON coins via your connected wallet. All transactions are final, and we are not responsible for any issues with third-party payment processors or blockchain networks.</p>

    <h2 class="terms-header">5. Referral Program</h2>
    <p class="terms-text">You may invite friends using your unique invite code to earn 50 oil drops per successful referral. Referrals must be new users who register and verify their accounts. Abuse of the referral system (e.g., self-referrals) may result in account suspension.</p>

    <h2 class="terms-header">6. Intellectual Property</h2>
    <p class="terms-text">All content, logos, and designs on Oil Drop Miner are the property of Oil Drop Miner and protected by copyright laws. You may not reproduce, distribute, or modify any content without our written permission.</p>

    <h2 class="terms-header">7. Termination</h2>
    <p class="terms-text">We reserve the right to terminate or suspend your account at any time for violation of these terms, illegal activities, or fraud. Terminated accounts will lose access to all oil drops, balances, and rewards.</p>

    <h2 class="terms-header">8. Limitation of Liability</h2>
    <p class="terms-text">Oil Drop Miner is provided "as is" without warranties of any kind. We are not liable for any damages arising from your use of the platform, including but not limited to lost profits or data issues.</p>

    <h2 class="terms-header">9. Governing Law</h2>
    <p class="terms-text">These Terms & Conditions are governed by the laws of [Your Country/State]. Any disputes will be resolved in the courts of [Your Location].</p>

    <h2 class="terms-header">10. Changes to Terms</h2>
    <p class="terms-text">We may update these Terms & Conditions at any time. Changes will be posted on this page with the updated date. Continued use of Oil Drop Miner after changes constitutes acceptance of the new terms.</p>

    <h2 class="terms-header">11. Contact Us</h2>
    <p class="terms-text">If you have questions about these Terms & Conditions, please contact us at:</p>
    <ul class="terms-text">
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