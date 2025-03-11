document.addEventListener('DOMContentLoaded', () => {
    // Helper function to get CSRF token
    const getCsrfToken = () => {
        const metaToken = document.querySelector('meta[name="csrf_token"]');
        const inputToken = document.querySelector('input[name="csrf_token"]');
        return metaToken ? metaToken.content : (inputToken ? inputToken.value : '');
    };

    // Helper function to check if user is blocked
    const checkUserBlocked = async () => {
        try {
            const response = await fetch('check_blocked.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: getCsrfToken() })
            });
            const data = await response.json();
            if (data.is_blocked) {
                alert('Your account has been blocked. Please contact support.');
                return true;
            }
            return false;
        } catch (err) {
            console.error('Error checking blocked status:', err);
            alert('An error occurred while checking your account status. Please try again.');
            return true;
        }
    };

    const chargeBtn = document.getElementById("charge-btn");
    if (chargeBtn) {
        chargeBtn.addEventListener("click", async () => {
            // Check if user is blocked
            if (await checkUserBlocked()) return;

            fetch("charge.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    csrf_token: getCsrfToken()
                })
            })
            .then(response => {
                if (!response.ok) throw new Error("Network response was not ok: " + response.statusText);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const oilCount = document.getElementById("oil-count");
                    if (oilCount) {
                        oilCount.textContent = data.oil_drops || 0;
                    } else {
                        console.error('Oil count element not found in DOM');
                    }
                    alert(data.message || "Oil charge successful!");
                } else {
                    alert(data.message || "Oil charge failed. Please try again.");
                }
            })
            .catch(err => {
                console.error("Charge Error:", err);
                alert("An error occurred while charging oil: " + err.message);
            });
        });
    }
});