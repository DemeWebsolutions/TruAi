window.submitButton = document.getElementById('loginSubmitButton') || document.querySelector('button[type="submit"], .submit-button');
if (!window.submitButton) console.warn('submitButton not found');
window.usernameInput = window.usernameInput || document.getElementById('username') || document.querySelector('input[name="username"], input[type="email"]');
window.passwordInput = window.passwordInput || document.getElementById('password') || document.querySelector('input[type="password"]');
