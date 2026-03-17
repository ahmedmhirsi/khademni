/* ========================================================
   KHADEMNI — Auth & Profile JavaScript
   API Client, Form Handlers, Token Management
   ======================================================== */

const API_BASE = '/khadelni/api/index.php?route=';

// ==================== API CLIENT ====================
class ApiClient {
    static getToken() {
        return localStorage.getItem('khademni_token');
    }

    static setToken(token) {
        localStorage.setItem('khademni_token', token);
    }

    static removeToken() {
        localStorage.removeItem('khademni_token');
    }

    static getUser() {
        const data = localStorage.getItem('khademni_user');
        return data ? JSON.parse(data) : null;
    }

    static setUser(user) {
        localStorage.setItem('khademni_user', JSON.stringify(user));
    }

    static removeUser() {
        localStorage.removeItem('khademni_user');
    }

    static isLoggedIn() {
        return !!this.getToken();
    }

    static async request(endpoint, options = {}) {
        const url = `${API_BASE}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        const token = this.getToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            const response = await fetch(url, {
                ...options,
                headers
            });

            const data = await response.json();
            return { ok: response.ok, status: response.status, data };
        } catch (error) {
            console.error('API Request Error:', error);
            return {
                ok: false,
                status: 0,
                data: { success: false, message: 'Network error. Please check your connection or server logs.' }
            };
        }
    }

    static async post(endpoint, body) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(body)
        });
    }

    static async get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    }

    static async put(endpoint, body) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(body)
        });
    }

    static async upload(endpoint, formData) {
        const url = `${API_BASE}${endpoint}`;
        const headers = {};
        const token = this.getToken();
        if (token) headers['Authorization'] = `Bearer ${token}`;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers,
                body: formData
            });
            const data = await response.json();
            return { ok: response.ok, status: response.status, data };
        } catch {
            return { ok: false, status: 0, data: { success: false, message: 'Upload failed.' } };
        }
    }

    static logout() {
        this.removeToken();
        this.removeUser();
        window.location.href = 'login.html';
    }
}

// ==================== TOAST NOTIFICATIONS ====================
class Toast {
    static container = null;

    static init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    }

    static show(message, type = 'info', duration = 4000) {
        this.init();

        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };

        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.innerHTML = `
      <i class="toast__icon ${icons[type]}"></i>
      <span>${message}</span>
    `;

        this.container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    static success(msg) { this.show(msg, 'success'); }
    static error(msg) { this.show(msg, 'error'); }
    static warning(msg) { this.show(msg, 'warning'); }
    static info(msg) { this.show(msg, 'info'); }
}

// ==================== FORM UTILITIES ====================
function setLoading(btn, loading) {
    if (loading) {
        btn.dataset.originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span> Please wait...';
        btn.classList.add('loading');
    } else {
        btn.innerHTML = btn.dataset.originalText || btn.innerHTML;
        btn.classList.remove('loading');
    }
}

function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const error = document.getElementById(`${fieldId}-error`);
    if (field) field.classList.add('error');
    if (error) {
        error.textContent = message;
        error.classList.add('visible');
    }
}

function clearFieldErrors() {
    document.querySelectorAll('.form-input.error').forEach(el => el.classList.remove('error'));
    document.querySelectorAll('.form-error').forEach(el => {
        el.textContent = '';
        el.classList.remove('visible');
    });
}

function showAlert(id, message, type = 'error') {
    const alert = document.getElementById(id);
    if (alert) {
        alert.className = `alert alert--${type} visible`;
        alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    }
}

function hideAlert(id) {
    const alert = document.getElementById(id);
    if (alert) alert.classList.remove('visible');
}

function displayErrors(errors) {
    if (typeof errors === 'object') {
        Object.entries(errors).forEach(([field, msg]) => showFieldError(field, msg));
    }
}

// ==================== AUTH GUARDS ====================
function requireGuest() {
    if (ApiClient.isLoggedIn()) {
        window.location.href = 'profile.html';
    }
}

function requireAuth() {
    if (!ApiClient.isLoggedIn()) {
        window.location.href = 'login.html';
    }
}

// ==================== LOGIN HANDLER ====================
function initLogin() {
    requireGuest();

    const form = document.getElementById('loginForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearFieldErrors();
        hideAlert('loginAlert');

        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;

        // Client-side validation
        let valid = true;
        if (!email) { showFieldError('email', 'Email is required.'); valid = false; }
        if (!password) { showFieldError('password', 'Password is required.'); valid = false; }
        if (!valid) return;

        const btn = form.querySelector('button[type="submit"]');
        setLoading(btn, true);

        const { ok, data } = await ApiClient.post('/login', { email, password });

        setLoading(btn, false);

        if (ok && data.success) {
            ApiClient.setToken(data.token);
            ApiClient.setUser(data.user);
            Toast.success('Welcome back! Redirecting...');
            setTimeout(() => window.location.href = 'profile.html', 800);
        } else {
            if (data.errors) {
                displayErrors(data.errors);
            } else {
                showAlert('loginAlert', data.message || 'Login failed.');
            }
        }
    });
}

// ==================== REGISTER HANDLER ====================
function initRegister() {
    requireGuest();

    // Role tab switching
    const roleTabs = document.querySelectorAll('.role-tab');
    const candidateFields = document.getElementById('candidateFields');
    const companyFields = document.getElementById('companyFields');
    let selectedRole = 'candidate';

    roleTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            roleTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            selectedRole = tab.dataset.role;

            if (selectedRole === 'candidate') {
                candidateFields && (candidateFields.style.display = 'block');
                companyFields && (companyFields.style.display = 'none');
            } else {
                candidateFields && (candidateFields.style.display = 'none');
                companyFields && (companyFields.style.display = 'block');
            }
        });
    });

    const form = document.getElementById('registerForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearFieldErrors();
        hideAlert('registerAlert');

        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const passwordConfirm = document.getElementById('password_confirm').value;
        const terms = document.getElementById('terms')?.checked;

        let valid = true;
        if (!name) { showFieldError('name', 'Name is required.'); valid = false; }
        if (!email) { showFieldError('email', 'Email is required.'); valid = false; }
        if (!password || password.length < 8) { showFieldError('password', 'Password must be at least 8 characters.'); valid = false; }
        if (password !== passwordConfirm) { showFieldError('password_confirm', 'Passwords do not match.'); valid = false; }
        if (!terms) { Toast.warning('Please accept the Terms of Service.'); valid = false; }

        const body = { name, email, password, password_confirm: passwordConfirm, role: selectedRole };

        if (selectedRole === 'company') {
            const companyName = document.getElementById('company_name')?.value.trim();
            if (!companyName) { showFieldError('company_name', 'Company name is required.'); valid = false; }
            body.company_name = companyName;
        }

        if (!valid) return;

        const btn = form.querySelector('button[type="submit"]');
        setLoading(btn, true);

        const { ok, data } = await ApiClient.post('/register', body);
        setLoading(btn, false);

        if (ok && data.success) {
            Toast.success('Account created! Please verify your email.');
            // For local dev testing, we automatically redirect with the dev_token
            const verifyUrl = data.dev_token ? `verify.html?token=${data.dev_token}` : 'login.html';
            setTimeout(() => window.location.href = verifyUrl, 1500);
        } else {
            if (data.errors) {
                displayErrors(data.errors);
            } else {
                showAlert('registerAlert', data.message || 'Registration failed.');
            }
        }
    });
}

// ==================== FORGOT PASSWORD HANDLER ====================
function initForgotPassword() {
    requireGuest();

    const form = document.getElementById('forgotForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearFieldErrors();
        hideAlert('forgotAlert');

        const email = document.getElementById('email').value.trim();
        if (!email) { showFieldError('email', 'Email is required.'); return; }

        const btn = form.querySelector('button[type="submit"]');
        setLoading(btn, true);

        const { ok, data } = await ApiClient.post('/password-reset', { email });
        setLoading(btn, false);

        if (data.success) {
            showAlert('forgotAlert', data.message, 'success');
            form.reset();
        } else {
            showAlert('forgotAlert', data.message || 'Something went wrong.');
        }
    });
}

// ==================== PROFILE PAGE ====================
function initProfile() {
    requireAuth();

    const user = ApiClient.getUser();
    if (!user) return;

    // Set user info in header
    const avatarEl = document.getElementById('profileAvatar');
    const navAvatarEl = document.getElementById('navAvatar');
    const nameEl = document.getElementById('profileName');
    const emailEl = document.getElementById('profileEmail');
    const roleEl = document.getElementById('profileRole');
    const navUserName = document.getElementById('navUserName');

    const initial = (user.name || 'U').charAt(0).toUpperCase();
    if (avatarEl) avatarEl.textContent = initial;
    if (navAvatarEl) navAvatarEl.textContent = initial;
    if (navUserName) navUserName.textContent = user.name;

    // Show correct role sections
    const candidateSection = document.getElementById('candidateSection');
    const companySection = document.getElementById('companySection');
    if (user.role === 'candidate') {
        candidateSection && (candidateSection.style.display = 'block');
        companySection && (companySection.style.display = 'none');
    } else {
        candidateSection && (candidateSection.style.display = 'none');
        companySection && (companySection.style.display = 'block');
    }

    // Load profile data
    loadProfileData();

    // Init skill tags (candidate only)
    if (user.role === 'candidate') initSkillTags();

    // Profile form
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveProfile();
        });
    }

    // Password form
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await changePassword();
        });
    }

    // File uploads
    initFileUpload('cvUpload', 'cv');
    initFileUpload('logoUpload', 'logo');

    // Logout
    document.getElementById('logoutBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        ApiClient.logout();
    });
}

async function loadProfileData() {
    const { ok, status, data } = await ApiClient.get('/profile');
    if (!ok || !data.success) {
        if (status === 401 || (data && data.message === 'Unauthorized')) {
            ApiClient.logout();
        } else {
            Toast.error(data?.message || 'Failed to open profile.');
        }
        return;
    }

    const { user, profile } = data;

    // Fill header
    const nameEl = document.getElementById('profileName');
    const emailEl = document.getElementById('profileEmail');
    const roleEl = document.getElementById('profileRole');

    if (nameEl) nameEl.textContent = user.name;
    if (emailEl) emailEl.textContent = user.email;
    if (roleEl) {
        roleEl.textContent = user.role;
        roleEl.className = `badge badge--${user.role}`;
    }

    // Fill form fields
    setVal('profileNameInput', user.name);

    if (user.role === 'candidate' && profile) {
        setVal('location', profile.location);
        setVal('bio', profile.bio);
        setVal('experience_years', profile.experience_years);

        // Load skills as tags
        if (profile.skills && Array.isArray(profile.skills)) {
            window._skillTags = profile.skills;
            renderSkillTags();
        }

        // Show current CV
        if (profile.cv_path) {
            const el = document.getElementById('currentCv');
            if (el) el.innerHTML = `<i class="fas fa-file-pdf"></i> <a href="${profile.cv_path}" target="_blank">View current CV</a>`;
        }
    } else if (user.role === 'company' && profile) {
        setVal('company_name', profile.company_name);
        setVal('description', profile.description);
        setVal('website', profile.website);
        setVal('companyLocation', profile.location);

        if (profile.logo_path) {
            const el = document.getElementById('currentLogo');
            if (el) el.innerHTML = `<img src="${profile.logo_path}" alt="Logo" style="max-height:60px;border-radius:8px;">`;
        }
    }
}

function setVal(id, value) {
    const el = document.getElementById(id);
    if (el && value !== null && value !== undefined) el.value = value;
}

async function saveProfile() {
    const user = ApiClient.getUser();
    const body = {
        name: document.getElementById('profileNameInput')?.value.trim()
    };

    if (user.role === 'candidate') {
        body.location = document.getElementById('location')?.value.trim();
        body.bio = document.getElementById('bio')?.value.trim();
        body.experience_years = parseInt(document.getElementById('experience_years')?.value) || 0;
        body.skills = window._skillTags || [];
    } else {
        body.company_name = document.getElementById('company_name')?.value.trim();
        body.description = document.getElementById('description')?.value.trim();
        body.website = document.getElementById('website')?.value.trim();
        body.location = document.getElementById('companyLocation')?.value.trim();
    }

    const btn = document.querySelector('#profileForm button[type="submit"]');
    setLoading(btn, true);

    const { ok, data } = await ApiClient.put('/profile', body);
    setLoading(btn, false);

    if (ok && data.success) {
        Toast.success('Profile updated successfully!');
        // Update stored user name
        const u = ApiClient.getUser();
        if (u && body.name) {
            u.name = body.name;
            ApiClient.setUser(u);
        }
    } else {
        Toast.error(data.message || 'Failed to update profile.');
    }
}

async function changePassword() {
    clearFieldErrors();
    const current = document.getElementById('current_password')?.value;
    const newPass = document.getElementById('new_password')?.value;
    const confirm = document.getElementById('confirm_password')?.value;

    let valid = true;
    if (!current) { showFieldError('current_password', 'Current password is required.'); valid = false; }
    if (!newPass || newPass.length < 8) { showFieldError('new_password', 'Minimum 8 characters.'); valid = false; }
    if (newPass !== confirm) { showFieldError('confirm_password', 'Passwords do not match.'); valid = false; }
    if (!valid) return;

    const btn = document.querySelector('#passwordForm button[type="submit"]');
    setLoading(btn, true);

    const { ok, data } = await ApiClient.post('/profile/password', {
        current_password: current,
        new_password: newPass,
        password_confirm: confirm
    });

    setLoading(btn, false);

    if (ok && data.success) {
        Toast.success('Password changed successfully!');
        document.getElementById('passwordForm').reset();
    } else {
        if (data.errors) displayErrors(data.errors);
        else Toast.error(data.message || 'Failed to change password.');
    }
}

// ==================== SKILL TAGS ====================
window._skillTags = [];

function initSkillTags() {
    const input = document.getElementById('skillInput');
    if (!input) return;

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const value = input.value.trim().replace(/,/g, '');
            if (value && !window._skillTags.includes(value)) {
                window._skillTags.push(value);
                renderSkillTags();
            }
            input.value = '';
        }
        if (e.key === 'Backspace' && !input.value && window._skillTags.length) {
            window._skillTags.pop();
            renderSkillTags();
        }
    });
}

function renderSkillTags() {
    const container = document.getElementById('skillTagsContainer');
    if (!container) return;

    // Remove existing tags
    container.querySelectorAll('.skill-tag').forEach(t => t.remove());

    const input = document.getElementById('skillInput');
    window._skillTags.forEach((tag, i) => {
        const el = document.createElement('span');
        el.className = 'skill-tag';
        el.innerHTML = `${escapeHtml(tag)} <button type="button" class="skill-tag__remove" onclick="removeSkillTag(${i})"><i class="fas fa-times"></i></button>`;
        container.insertBefore(el, input);
    });
}

function removeSkillTag(index) {
    window._skillTags.splice(index, 1);
    renderSkillTags();
}

// ==================== FILE UPLOAD ====================
function initFileUpload(inputId, type) {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.addEventListener('change', async () => {
        const file = input.files[0];
        if (!file) return;

        const wrapper = input.closest('.file-upload');
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', type);

        wrapper?.classList.add('has-file');
        const textEl = wrapper?.querySelector('.file-upload__text');
        if (textEl) textEl.innerHTML = `<strong>${escapeHtml(file.name)}</strong> — Uploading...`;

        const { ok, data } = await ApiClient.upload('/profile/upload', formData);

        if (ok && data.success) {
            Toast.success(data.message);
            if (textEl) textEl.innerHTML = `<strong>${escapeHtml(file.name)}</strong> — Uploaded ✓`;
        } else {
            Toast.error(data.message || 'Upload failed.');
            wrapper?.classList.remove('has-file');
            if (textEl) textEl.innerHTML = `<strong>Click to upload</strong> or drag and drop`;
        }
    });
}

// ==================== PASSWORD VISIBILITY TOGGLE ====================
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const btn = input?.parentElement.querySelector('.password-toggle i');
    if (!input) return;

    if (input.type === 'password') {
        input.type = 'text';
        btn?.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        btn?.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// ==================== HELPERS ====================
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ==================== GOOGLE SIGN-IN ====================
function initGoogleSignIn() {
    const container = document.getElementById('googleBtnContainer');
    // Set a slight timeout to ensure Google library sets up globally
    setTimeout(() => {
        if (!container || typeof google === 'undefined' || !google.accounts) return;

        google.accounts.id.initialize({
            // REMPLACE PAR TON PROPRE CLIENT_ID GOOGLE
            client_id: '386528214116-k49d4leiq56u6u3dfp2k3j8ko19ndk2r.apps.googleusercontent.com',
            callback: handleGoogleResponse
        });

        google.accounts.id.renderButton(
            container,
            { theme: 'filled_black', size: 'large', shape: 'pill', text: 'continue_with' }
        );
    }, 100);
}

async function handleGoogleResponse(response) {
    const token = response.credential;

    // Determine role if on register page
    let role = 'candidate';
    const activeTab = document.querySelector('.role-tab.active');
    if (activeTab) {
        role = activeTab.dataset.role;
    }

    const container = document.getElementById('googleBtnContainer');
    if (container) container.style.opacity = '0.5';
    Toast.info('Authenticating via Google...');

    const { ok, data } = await ApiClient.post('/auth/google', { token, role });

    if (container) container.style.opacity = '1';

    if (ok && data.success) {
        ApiClient.setToken(data.token);
        ApiClient.setUser(data.user);
        Toast.success('Google authentication successful! Redirecting...');
        setTimeout(() => window.location.href = 'profile.html', 800);
    } else {
        Toast.error(data.message || 'Google authentication failed.');
    }
}

// ==================== NAVBAR AUTH STATE ====================
function updateNavbarAuth() {
    const loginBtn = document.getElementById('navLoginBtn');
    const signupBtn = document.getElementById('navSignupBtn');
    const profileBtn = document.getElementById('navProfileBtn');

    if (ApiClient.isLoggedIn()) {
        if (loginBtn) loginBtn.style.display = 'none';
        if (signupBtn) signupBtn.style.display = 'none';
        if (profileBtn) profileBtn.style.display = '';
    } else {
        if (loginBtn) loginBtn.style.display = '';
        if (signupBtn) signupBtn.style.display = '';
        if (profileBtn) profileBtn.style.display = 'none';
    }
}

// ==================== AUTO-INIT ====================
document.addEventListener('DOMContentLoaded', () => {
    // Detect which page we're on and init accordingly
    if (document.getElementById('loginForm')) initLogin();
    if (document.getElementById('registerForm')) initRegister();
    if (document.getElementById('forgotForm')) initForgotPassword();
    if (document.getElementById('profileForm')) initProfile();

    // Init Google Login
    initGoogleSignIn();

    // Update navbar auth state on homepage
    updateNavbarAuth();
});
