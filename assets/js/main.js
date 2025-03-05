document.addEventListener('DOMContentLoaded', () => {
    /****************************************
     *  MINE OIL
     ****************************************/
    const mineBtn = document.getElementById("mine-btn");
    if (mineBtn && !mineBtn.hasAttribute('data-event-added')) {
        mineBtn.setAttribute('data-event-added', 'true');
        mineBtn.addEventListener("click", () => {
            fetch("mine.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: 'mine=1'
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error("Network response was not ok: " + res.statusText);
                }
                return res.text(); // ابتدا متن رو بگیر تا بررسی کنی JSON هست یا نه
            })
            .then(text => {
                try {
                    const data = JSON.parse(text); // تلاش برای پارس کردن به JSON
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
                        alert(data.message || "Mining failed.");
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e, 'Response:', text);
                    alert('An error occurred while mining: Invalid JSON response. Check console for details.');
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

    // پلن 2x
    const buy2xBtn = document.getElementById("buy-2x");
    if (buy2xBtn) {
        buy2xBtn.addEventListener("click", (e) => {
            e.preventDefault();
            fetch("purchase.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ plan: "2x" })
            })
            .then(res => {
                if (!res.ok) throw new Error("Network response was not ok");
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message || "2x Plan purchased successfully!");
                    if (data.boost_multiplier) {
                        document.querySelector(".fw-bold").textContent = data.boost_multiplier + "×";
                    }
                } else {
                    alert(data.message || "Purchase failed.");
                }
            })
            .catch(err => {
                console.error("Error:", err);
                alert("An error occurred while purchasing the plan. Check console for details.");
            });
        });
    }

    // پلن 5x
    const buy5xBtn = document.getElementById("buy-5x");
    if (buy5xBtn) {
        buy5xBtn.addEventListener("click", (e) => {
            e.preventDefault();
            fetch("purchase.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ plan: "5x" })
            })
            .then(res => {
                if (!res.ok) throw new Error("Network response was not ok");
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message || "5x Plan purchased successfully!");
                    if (data.boost_multiplier) {
                        document.querySelector(".fw-bold").textContent = data.boost_multiplier + "×";
                    }
                } else {
                    alert(data.message || "Purchase failed.");
                }
            })
            .catch(err => {
                console.error("Error:", err);
                alert("An error occurred while purchasing the plan. Check console for details.");
            });
        });
    }

    // پلن 10x
    const buy10xBtn = document.getElementById("buy-10x");
    if (buy10xBtn) {
        buy10xBtn.addEventListener("click", (e) => {
            e.preventDefault();
            fetch("purchase.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ plan: "10x" })
            })
            .then(res => {
                if (!res.ok) throw new Error("Network response was not ok");
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message || "10x Plan purchased successfully!");
                    if (data.boost_multiplier) {
                        document.querySelector(".fw-bold").textContent = data.boost_multiplier + "×";
                    }
                } else {
                    alert(data.message || "Purchase failed.");
                }
            })
            .catch(err => {
                console.error("Error:", err);
                alert("An error occurred while purchasing the plan. Check console for details.");
            });
        });
    }

    /****************************************
     *  DEPOSIT TON
     ****************************************/
    const depositForm = document.querySelector("form[action='deposit.php']");
    if (depositForm) {
        depositForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const amount = document.getElementById("amount").value;
            const csrfToken = document.querySelector("input[name='csrf_token']").value;
            fetch("deposit.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ amount: amount, csrf_token: csrfToken })
            })
            .then(res => {
                if (!res.ok) throw new Error("Network response was not ok: " + res.statusText);
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    const balanceText = document.querySelector(".balance-text");
                    if (balanceText) {
                        balanceText.textContent = data.new_balance || 0;
                    } else {
                        console.error('Balance element not found in DOM');
                        alert('UI update failed: Balance element not found. Check console for details.');
                    }
                } else {
                    alert(data.message);
                }
            })
            .catch(err => {
                console.error('Deposit Error:', err);
                alert('An error occurred while depositing: ' + err.message);
            });
        });
    }
});