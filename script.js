const togglePassword = document.getElementById("togglePassword");
const loginMessage   = document.getElementById("loginMessage");

// ── Show/hide password ────────────────────────────────────────────────────────
if (togglePassword) {
    togglePassword.addEventListener("click", function () {
        const passwordInput = document.getElementById("password");
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            togglePassword.classList.remove("fa-eye");
            togglePassword.classList.add("fa-eye-slash");
        } else {
            passwordInput.type = "password";
            togglePassword.classList.remove("fa-eye-slash");
            togglePassword.classList.add("fa-eye");
        }
    });
}

// ── Show PHP error / success messages from URL query params ──────────────────
(function () {
    const params   = new URLSearchParams(window.location.search);
    const errorDiv = document.getElementById("loginError");
    if (!errorDiv) return;

    const errMsg = params.get("error");
    const regOk  = params.get("registered");

    if (errMsg) {
        errorDiv.style.background = "rgba(248,113,113,0.15)";
        errorDiv.style.border     = "1px solid rgba(248,113,113,0.3)";
        errorDiv.style.color      = "#f87171";
        errorDiv.textContent      = decodeURIComponent(errMsg.replace(/\+/g, " "));
        errorDiv.style.display    = "block";
    }

    if (regOk === "1") {
        errorDiv.style.background = "rgba(74,222,128,0.15)";
        errorDiv.style.border     = "1px solid rgba(74,222,128,0.3)";
        errorDiv.style.color      = "#4ade80";
        errorDiv.textContent      = "✅ Registration successful! Please login.";
        errorDiv.style.display    = "block";
    }
})();
