document.addEventListener('DOMContentLoaded', () => {
    // Helper function to get CSRF token from meta or hidden input
    const getCsrfToken = () => {
        const metaToken = document.querySelector('meta[name="csrf_token"]');
        const inputToken = document.querySelector('input[name="csrf_token"]');
        return metaToken ? metaToken.content : (inputToken ? inputToken.value : '');
    };

    // Helper function to check if user is blocked before performing actions
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
            return true; // Assume blocked on error to prevent further actions
        }
    };

    /****************************************
     *  MINE OIL
     ****************************************/
    const mineBtn = document.getElementById("mine-btn");
    if (mineBtn && !mineBtn.hasAttribute('data-event-added')) {
        mineBtn.setAttribute('data-event-added', 'true');
        mineBtn.addEventListener("click", async () => {
            // Check if user is blocked
            if (await checkUserBlocked()) return;

            fetch("mine.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    mine: 1,
                    csrf_token: getCsrfToken()
                })
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error("Network response was not ok: " + res.statusText);
                }
                return res.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    console.log('Mine Response:', data);
                    if (data.success) {
                        const oilCount = document.getElementById('oil-count');
                        const clicksLeft = document.getElementById('clicks-left');
                        if (oilCount && clicksLeft) {
                            oilCount.textContent = data.oil_drops || 0;
                            clicksLeft.textContent = data.clicks_left || 0;
                        } else {
                            console.error('Elements oil-count or clicks-left not found in DOM');
                            alert('UI update failed: Elements not found. Check console for details.');
                        }
                        if (data.clicks_left <= 0) {
                            mineBtn.classList.remove("shine");
                            mineBtn.disabled = true;
                        } else {
                            mineBtn.classList.add("shine");
                            setTimeout(() => mineBtn.classList.remove("shine"), 500);
                        }
                    } else {
                        alert(data.message || "Mining failed. Please try again.");
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e, 'Response:', text);
                    alert('An error occurred while mining: Invalid response from server. Check console for details.');
                }
            })
            .catch(err => {
                console.error('Mine Error:', err);
                alert('An error occurred while mining: ' + err.message);
            });
        });
    }

    /****************************************
     *  PURCHASE BOOST PLANS
     ****************************************/

    // Generic function to handle boost plan purchase
    const purchaseBoostPlan = async (plan, buttonId) => {
        const btn = document.getElementById(buttonId);
        if (btn) {
            btn.addEventListener("click", async (e) => {
                e.preventDefault();
                // Check if user is blocked
                if (await checkUserBlocked()) return;

                fetch("purchase.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({
                        plan: plan,
                        csrf_token: getCsrfToken()
                    })
                })
                .then(res => {
                    if (!res.ok) throw new Error("Network response was not ok: " + res.statusText);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message || `${plan.toUpperCase()} Plan purchased successfully!`);
                        if (data.boost_multiplier) {
                            const boostDisplay = document.querySelector(".fw-bold");
                            if (boostDisplay) {
                                boostDisplay.textContent = data.boost_multiplier + "×";
                            } else {
                                console.error('Boost multiplier element not found in DOM');
                            }
                        }
                    } else {
                        alert(data.message || "Purchase failed. Please try again.");
                    }
                })
                .catch(err => {
                    console.error(`Error purchasing ${plan} plan:`, err);
                    alert(`An error occurred while purchasing the ${plan} plan. Check console for details.`);
                });
            });
        }
    };

    // Initialize boost plan buttons
    purchaseBoostPlan("2x", "buy-2x");
    purchaseBoostPlan("5x", "buy-5x");
    purchaseBoostPlan("10x", "buy-10x");

    /****************************************
     *  DEPOSIT TON
     ****************************************/
    const depositForm = document.querySelector("form[action='deposit.php']");
    if (depositForm) {
        depositForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            // Check if user is blocked
            if (await checkUserBlocked()) return;

            const amount = document.getElementById("amount").value;
            const csrfToken = getCsrfToken();
            fetch("deposit.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    amount: amount,
                    csrf_token: csrfToken
                })
            })
            .then(res => {
                if (!res.ok) throw new Error("Network response was not ok: " + res.statusText);
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message || "Deposit successful!");
                    const balanceText = document.querySelector(".balance-text");
                    if (balanceText) {
                        balanceText.textContent = numberFormat(data.new_balance || 0, 2) + ' TON';
                    } else {
                        console.error('Balance element not found in DOM');
                        alert('UI update failed: Balance element not found. Check console for details.');
                    }
                } else {
                    alert(data.message || "Deposit failed. Please try again.");
                }
            })
            .catch(err => {
                console.error('Deposit Error:', err);
                alert('An error occurred while depositing: ' + err.message);
            });
        });
    }

    // Helper function to format numbers
    function numberFormat(number, decimals) {
        return number.toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
});