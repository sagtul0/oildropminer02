<?php
include 'header.php'; // Includes database connection as $conn and session start

// بررسی دسترسی ادمین
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login_web.php');
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $promo_id = (int)$_GET['id'];

    // گرفتن اطلاعات درخواست
    $stmt = $conn->prepare("SELECT user_id, status FROM promo_requests WHERE id = ?");
    $stmt->bind_param("i", $promo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $promo = $result->fetch_assoc();

    if ($promo && $promo['status'] === 'pending') {
        $user_id = $promo['user_id'];

        // Check if user is blocked
        $stmt = $conn->prepare("SELECT is_blocked FROM users WHERE id = ? OR chat_id = ?");
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if ($user['is_blocked']) {
            echo "<div class='alert alert-danger text-center mt-3'>This user account has been blocked.</div>";
            include 'footer.php';
            exit;
        }
        $stmt->close();

        // شروع تراکنش
        $conn->begin_transaction();
        try {
            // به‌روزرسانی وضعیت درخواست به approved
            $update_promo = $conn->prepare("UPDATE promo_requests SET status = 'approved' WHERE id = ?");
            $update_promo->bind_param("i", $promo_id);
            if (!$update_promo->execute()) {
                throw new Exception("Error updating promo request: " . $update_promo->error);
            }

            // گرفتن مقدار فعلی oil_drops کاربر
            $user_stmt = $conn->prepare("SELECT oil_drops FROM users WHERE id = ? OR chat_id = ?");
            $user_stmt->bind_param("ii", $user_id, $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user = $user_result->fetch_assoc();
            $current_oil = (int)$user['oil_drops'];

            // اضافه کردن 10000 قطره نفت
            $new_oil = $current_oil + 10000;

            // به‌روزرسانی oil_drops کاربر
            $update_user = $conn->prepare("UPDATE users SET oil_drops = ? WHERE id = ? OR chat_id = ?");
            $update_user->bind_param("iii", $new_oil, $user_id, $user_id);
            if (!$update_user->execute()) {
                throw new Exception("Error updating user oil drops: " . $update_user->error);
            }

            $conn->commit();
            echo "<div class='alert alert-success text-center mt-3'>
                    Promotion approved! User received 10000 oil drops.
                  </div>";
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error approving promo: " . $e->getMessage());
            echo "<div class='alert alert-danger text-center mt-3'>
                    Error approving promotion: " . $e->getMessage() . "
                  </div>";
        }

        $stmt->close();
        $update_promo->close();
        $user_stmt->close();
        $update_user->close();
    } else {
        echo "<div class='alert alert-danger text-center mt-3'>
                Invalid or already processed promotion request.
              </div>";
    }
} else {
    echo "<div class='alert alert-danger text-center mt-3'>
            Invalid promotion ID.
          </div>";
}

include 'footer.php';
exit();