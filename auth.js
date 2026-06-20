const API_BASE = "api";

function showAuthError(elementId, message) {
  const errorEl = document.getElementById(elementId);
  if (errorEl) {
    errorEl.textContent = message;
    errorEl.style.display = "block";
  }
}

function clearAuthError(elementId) {
  const errorEl = document.getElementById(elementId);
  if (errorEl) {
    errorEl.textContent = "";
    errorEl.style.display = "none";
  }
}

async function handleLogin(event) {
  event.preventDefault();
  clearAuthError("login-error");

  const email = document.getElementById("login-email").value.trim();
  const password = document.getElementById("login-password").value;

  if (!email || !password) {
    showAuthError("login-error", "Please fill in all fields.");
    return;
  }

  try {
    const response = await fetch(`${API_BASE}/login.php`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ email, password }),
    });

    const data = await response.json();

    if (data.success) {
      // Redirect to dashboard
      window.location.href = "index.html";
    } else {
      showAuthError(
        "login-error",
        data.message || "Login failed. Please try again.",
      );
    }
  } catch (error) {
    console.error(error);
    showAuthError("login-error", "Connection error. Please try again.");
  }
}

async function handleRegister(event) {
  event.preventDefault();
  clearAuthError("register-error");

  const fullName = document.getElementById("register-fullname").value.trim();
  const email = document.getElementById("register-email").value.trim();
  const password = document.getElementById("register-password").value;
  const passwordConfirm = document.getElementById(
    "register-password-confirm",
  ).value;

  if (!fullName || !email || !password || !passwordConfirm) {
    showAuthError("register-error", "Please fill in all fields.");
    return;
  }

  if (password !== passwordConfirm) {
    showAuthError("register-error", "Passwords do not match.");
    return;
  }

  if (password.length < 6) {
    showAuthError("register-error", "Password must be at least 6 characters.");
    return;
  }

  try {
    const response = await fetch(`${API_BASE}/register.php`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ fullName, email, password }),
    });

    const data = await response.json();

    if (data.success) {
      // Redirect to dashboard
      window.location.href = "index.html";
    } else {
      showAuthError(
        "register-error",
        data.message || "Registration failed. Please try again.",
      );
    }
  } catch (error) {
    console.error(error);
    showAuthError("register-error", "Connection error. Please try again.");
  }
}

// Handle form submissions
const loginForm = document.getElementById("login-form");
if (loginForm) {
  loginForm.addEventListener("submit", handleLogin);
}

const registerForm = document.getElementById("register-form");
if (registerForm) {
  registerForm.addEventListener("submit", handleRegister);
}

// Handle password visibility toggle
document.querySelectorAll('.password-toggle').forEach(button => {
  button.addEventListener('click', function() {
    // Find the input field within the same wrapper
    const input = this.parentElement.querySelector('input');
    const iconEye = this.querySelector('.icon-eye');
    const iconEyeOff = this.querySelector('.icon-eye-off');
    
    if (input.type === 'password') {
      input.type = 'text';
      if (iconEye) iconEye.style.display = 'none';
      if (iconEyeOff) iconEyeOff.style.display = 'block';
      this.setAttribute('aria-label', 'Hide password');
    } else {
      input.type = 'password';
      if (iconEye) iconEye.style.display = 'block';
      if (iconEyeOff) iconEyeOff.style.display = 'none';
      this.setAttribute('aria-label', 'Show password');
    }
  });
});
