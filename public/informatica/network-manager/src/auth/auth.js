/**
 * Auth — session-based authentication middleware.
 *
 * No external dependencies: uses Node's built-in crypto for tokens.
 *
 * Session store: in-memory Map (survives restarts via cookie, token
 * is re-issued on login — acceptable for a single-admin internal tool).
 *
 * Credentials: stored in settings table under keys
 *   auth_username  (default: 'admin')
 *   auth_password  (hashed with SHA-256 + salt — never plaintext)
 *
 * On first run with no credentials set, falls back to env vars:
 *   NM_USER  NM_PASS
 * Or hard-coded defaults 'admin' / 'netmonitor' that MUST be changed.
 *
 * Protected: all routes except GET /auth/login (page) and POST /auth/login.
 */

const crypto = require('crypto');
const path   = require('path');
const { getDb } = require('../db/database');

// ── Session store ──────────────────────────────────────────────────────────────
const SESSION_TTL_MS = 8 * 60 * 60 * 1000; // 8 hours
const COOKIE_NAME    = 'nm_sid';
const _sessions      = new Map(); // token → { expiresAt }

function createSession() {
    const token     = crypto.randomBytes(32).toString('hex');
    const expiresAt = Date.now() + SESSION_TTL_MS;
    _sessions.set(token, { expiresAt });
    return token;
}

function validateSession(token) {
    if (!token) return false;
    const session = _sessions.get(token);
    if (!session) return false;
    if (Date.now() > session.expiresAt) {
        _sessions.delete(token);
        return false;
    }
    // Sliding window — extend on activity
    session.expiresAt = Date.now() + SESSION_TTL_MS;
    return true;
}

function destroySession(token) {
    _sessions.delete(token);
}

// Cleanup expired sessions every hour
setInterval(() => {
    const now = Date.now();
    for (const [token, s] of _sessions) {
        if (now > s.expiresAt) _sessions.delete(token);
    }
}, 60 * 60 * 1000);

// ── Password hashing ───────────────────────────────────────────────────────────

function hashPassword(password, salt) {
    return crypto.createHmac('sha256', salt).update(password).digest('hex');
}

function getSalt() {
    let salt = '';
    try {
        const row = getDb().prepare("SELECT value FROM settings WHERE key = 'auth_salt'").get();
        if (row?.value) return row.value;
    } catch { /* first run */ }
    // Generate and persist salt
    salt = crypto.randomBytes(16).toString('hex');
    try {
        getDb().prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('auth_salt', ?)").run(salt);
    } catch { /* ignore */ }
    return salt;
}

// ── Credential getters ─────────────────────────────────────────────────────────

function getStoredCredentials() {
    try {
        const db       = getDb();
        const userRow  = db.prepare("SELECT value FROM settings WHERE key = 'auth_username'").get();
        const hashRow  = db.prepare("SELECT value FROM settings WHERE key = 'auth_password_hash'").get();
        return {
            username: userRow?.value || process.env.NM_USER || 'admin',
            hash:     hashRow?.value || null,
        };
    } catch {
        return { username: 'admin', hash: null };
    }
}

/**
 * Called on first boot or via settings if no hash stored yet.
 * Stores username + hashed password in settings table.
 */
function setCredentials(username, plainPassword) {
    const salt = getSalt();
    const hash = hashPassword(plainPassword, salt);
    const db   = getDb();
    const up   = db.prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
    db.transaction(() => {
        up.run('auth_username',      username);
        up.run('auth_password_hash', hash);
    })();
    console.log(`[Auth] Credenciales actualizadas para usuario: ${username}`);
}

function verifyPassword(plainPassword) {
    const { hash } = getStoredCredentials();
    if (!hash) {
        // No password set yet — accept env var or default, then persist it
        const defaultPass = process.env.NM_PASS || 'netmonitor';
        if (plainPassword === defaultPass) {
            const { username } = getStoredCredentials();
            setCredentials(username, plainPassword);
            return true;
        }
        return false;
    }
    const salt = getSalt();
    return hashPassword(plainPassword, salt) === hash;
}

// ── Cookie helper ──────────────────────────────────────────────────────────────

function getTokenFromRequest(req) {
    const raw = req.headers.cookie ?? '';
    for (const part of raw.split(';')) {
        const [k, v] = part.trim().split('=');
        if (k === COOKIE_NAME) return v;
    }
    return null;
}

function setSessionCookie(res, token) {
    res.setHeader('Set-Cookie',
        `${COOKIE_NAME}=${token}; HttpOnly; SameSite=Strict; Max-Age=${SESSION_TTL_MS / 1000}; Path=/`
    );
}

function clearSessionCookie(res) {
    res.setHeader('Set-Cookie',
        `${COOKIE_NAME}=; HttpOnly; SameSite=Strict; Max-Age=0; Path=/`
    );
}

// ── Express middleware ─────────────────────────────────────────────────────────

/**
 * Middleware: blocks unauthenticated requests.
 * API calls get 401 JSON; page requests get redirect to /auth/login.
 */
// Paths that are always public (login page assets)
const PUBLIC_PATHS = ['/css/', '/fonts/'];

function requireAuth(req, res, next) {
    // Always allow static assets needed by login page
    if (PUBLIC_PATHS.some(p => req.path.startsWith(p))) return next();

    const token = getTokenFromRequest(req);
    if (validateSession(token)) return next();

    if (req.path.startsWith('/api/') || req.path.startsWith('/socket.io/')) {
        return res.status(401).json({ success: false, error: 'No autenticado' });
    }
    res.redirect('/auth/login');
}

// ── Auth router ────────────────────────────────────────────────────────────────

const express = require('express');
const router  = express.Router();

// GET /auth/login — serve login page
router.get('/login', (req, res) => {
    const token = getTokenFromRequest(req);
    if (validateSession(token)) return res.redirect('/');
    res.sendFile(path.join(__dirname, '../../public/login.html'));
});

// POST /auth/login — validate credentials
router.post('/login', express.urlencoded({ extended: false }), (req, res) => {
    const { username, password } = req.body;
    const { username: stored }   = getStoredCredentials();

    if (username === stored && verifyPassword(password)) {
        const token = createSession();
        setSessionCookie(res, token);
        return res.redirect('/');
    }
    res.redirect('/auth/login?error=1');
});

// POST /auth/logout
router.post('/logout', (req, res) => {
    const token = getTokenFromRequest(req);
    if (token) destroySession(token);
    clearSessionCookie(res);
    res.redirect('/auth/login');
});

// POST /api/auth/change-password — cambiar credenciales desde settings
router.post('/change-password', express.json(), (req, res) => {
    const token = getTokenFromRequest(req);
    if (!validateSession(token)) return res.status(401).json({ success: false, error: 'No autenticado' });

    const { username, currentPassword, newPassword } = req.body;
    if (!currentPassword || !newPassword) {
        return res.status(400).json({ success: false, error: 'Faltan campos' });
    }
    if (!verifyPassword(currentPassword)) {
        return res.status(403).json({ success: false, error: 'Contraseña actual incorrecta' });
    }
    setCredentials(username || getStoredCredentials().username, newPassword);
    res.json({ success: true });
});

module.exports = { router, requireAuth, setCredentials, getStoredCredentials };
