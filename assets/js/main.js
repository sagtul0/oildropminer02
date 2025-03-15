document.addEventListener('DOMContentLoaded', () => {
    const tg = window.Telegram.WebApp;
    if (!tg) {
        console.error("Telegram WebApp script failed to load.");
        document.querySelector('.container').innerHTML = `
            <p class="text-center text-danger">Error: Telegram WebApp script not loaded. Please check your internet connection or try again.</p>
        `;
        return;
    }
    tg.ready();
    tg.expand();

    // Helper function to get CSRF token
    const getCsrfToken = () => {
        const inputToken = document.querySelector('input[name="csrf_token"]');
        return inputToken ? inputToken.value : '';
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

    // Helper function to handle errors consistently
    const handleError = (message, error) => {
        console.error(`${message}:`, error);
        document.querySelector('.container').innerHTML = `
            <p class="text-center text-danger">Error: ${message}. Please try again or contact support.</p>
        `;
    };

    // Send InitData to server and handle redirect
    if (tg.initDataUnsafe && tg.initDataUnsafe.user && tg.initDataUnsafe.user.id) {
        fetch('/setInitData', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(tg.initDataUnsafe)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('InitData sent successfully');
                // به جای ریلود، ریدایرکت به URL دریافت‌شده از سرور
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    handleError('No redirect URL provided by server', new Error('Missing redirect_url in response'));
                }
            } else {
                handleError('Failed to authenticate user', new Error(data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            handleError('Error sending initData to server', error);
        });
    } else {
        console.error("No valid Telegram Init Data available.");
        document.querySelector('.container').innerHTML = `
            <p class="text-center text-danger">Error: Unable to authenticate. Please open via Telegram bot.</p>
        `;
    }

    /****************************************
     *  MINE OIL
     ****************************************/
    const mineBtn = document.getElementById("mine-btn");
    if (mineBtn && !mineBtn.hasAttribute('data-event-added')) {
        mineBtn.setAttribute('data-event-added', 'true');
        mineBtn.addEventListener("click", async () => {
            if (await checkUserBlocked()) return;

            fetch("mine.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ mine: 1, csrf_token: getCsrfToken() })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const oilCount = document.getElementById('oil-count');
                    const clicksLeft = document.getElementById('clicks-left');
                    if (oilCount && clicksLeft) {
                        oilCount.textContent = data.oil_drops || 0;
                        clicksLeft.textContent = `Clicks Left: ${data.clicks_left || 0}`;
                        if (data.clicks_left <= 0) {
                            mineBtn.classList.remove("shine");
                            mineBtn.disabled = true;
                        } else {
                            mineBtn.classList.add("shine");
                            setTimeout(() => mineBtn.classList.remove("shine"), 500);
                        }
                    } else {
                        console.error('Elements oil-count or clicks-left not found');
                        alert('UI update failed. Check console.');
                    }
                } else {
                    alert(data.message || "Mining failed.");
                }
            })
            .catch(err => {
                console.error('Mine Error:', err);
                alert('An error occurred while mining.');
            });
        });
    }

    /****************************************
     *  DEPOSIT TON
     ****************************************/
    const depositForm = document.querySelector("form[action='deposit.php']");
    if (depositForm) {
        depositForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            if (await checkUserBlocked()) return;

            const amount = document.getElementById("amount").value;
            fetch("deposit.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ amount: amount, csrf_token: getCsrfToken() })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || "Deposit successful!");
                    const balanceText = document.querySelector(".balance-text");
                    if (balanceText) {
                        balanceText.textContent = data.new_balance.toFixed(2) + ' TON';
                    }
                } else {
                    alert(data.message || "Deposit failed.");
                }
            })
            .catch(err => {
                console.error('Deposit Error:', err);
                alert('An error occurred while depositing.');
            });
        });
    }

    // Helper function to format numbers
    function numberFormat(number, decimals) {
        return number.toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
});