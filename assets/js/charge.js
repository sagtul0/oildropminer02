document.getElementById("charge-btn").addEventListener("click", function() {
    fetch("charge.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: new URLSearchParams({
            csrf_token: document.querySelector('meta[name="csrf_token"]').content // CSRF token
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById("oil-count").textContent = data.oil_drops;
            alert("Oil charge successful!");
        } else {
            alert(data.message);
        }
    })
    .catch(err => {
        console.error("Error:", err);
        alert("Something went wrong. Please try again.");
    });
});
