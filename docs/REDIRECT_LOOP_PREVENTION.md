# Solusi Permanant: Prevent Redirect Loop (ERR_TOO_MANY_REDIRECTS)

## Root Cause Analysis

**Redirect loop terjadi karena:**
1. File-based session corruption atau stale
2. Cache config yang tidak tersync dengan actual user state
3. Middleware redirect logic yang bisa trap user dalam loop
4. Tidak ada detection mechanism untuk catch redirect loops

---

## Solutions Implemented

### 1. **Improved DashboardController** ✅
**File:** `app/Http/Controllers/DashboardController.php`

**Changes:**
- Wrap `index()` method dalam try-catch untuk catch unhandled exceptions
- Add detailed logging untuk track error flow
- Add fallback: jika dashboard breaks → auto logout + redirect ke login
- Memastikan user valid sebelum render dashboard

**Effect:** Dashboard tidak akan infinite redirect jika ada error, tapi properly fallback ke login page.

---

### 2. **DetectRedirectLoop Middleware** ✅
**File:** `app/Http/Middleware/DetectRedirectLoop.php`

**How it works:**
- Tracks request count per user per URL dalam 30 detik
- Jika same URL diminta 5+ kali dalam 30 detik = redirect loop detected
- Auto logout user + clear session
- User diminta login ulang

**Effect:** Capture redirect loop sebelum browser show error. User get clean recovery path.

---

### 3. **ValidateSsoSession Middleware** ✅
**File:** `app/Http/Middleware/ValidateSsoSession.php`

**Validates:**
- User record masih exist di database
- User still active (`is_active = 1`)
- SSO user ID di session match dengan database
- Prevents stale/mismatched sessions causing loops

**Effect:** Session integrity validated sebelum render page. Stale sessions rejected early.

---

### 4. **Automatic Session Cleanup Command** ✅
**File:** `app/Console/Commands/CleanupStaleSessionsCommand.php`

**What it does:**
- Remove session files older than 7 days
- Remove cache files older than 7 days
- Prevent file corruption dari stale sessions accumulating

**Scheduled:** Daily 3:00 AM (jangan overlap dengan business hours)

**Can run manually:**
```bash
php artisan session:cleanup --days=7
```

---

### 5. **Middleware Registration** ✅
**Files:**
- `bootstrap/app.php` - alias middleware
- `routes/web.php` - apply to protected routes

**Protected routes now have:**
```php
Route::middleware(['sso.auth', 'validate.sso_session', 'detect.redirect_loop'])->group(...)
```

---

### 6. **Scheduler Setup** ✅
**File:** `app/Console/Kernel.php`

**Scheduled tasks:**
```
03:00 AM - session:cleanup --days=7 (Daily)
```

Ensure Laravel scheduler running:
```bash
# Add to crontab (run every minute)
* * * * * cd /root && php /home/deploy/apps/nametag/artisan schedule:run >> /dev/null 2>&1
```

---

## Monitoring & Debugging

### View Redirect Loop Detection in Logs:
```bash
tail -f storage/logs/laravel.log | grep "Redirect Loop"
```

### Check Session Cleanup Logs:
```bash
tail -f storage/logs/laravel.log | grep "session:cleanup"
```

### Manual Session Cleanup (Emergency):
```bash
# Remove ALL sessions
rm -rf storage/framework/sessions/*

# Remove cache
rm -rf storage/framework/cache/*

# Run cleanup command
php artisan session:cleanup --days=0
```

---

## Prevention Checklist

- ✅ DashboardController error handling added
- ✅ DetectRedirectLoop middleware added
- ✅ ValidateSsoSession middleware added  
- ✅ Session cleanup command created
- ✅ Middleware registered in bootstrap/app.php
- ✅ Middleware applied to protected routes
- ✅ Scheduler task configured in Console/Kernel.php
- ✅ All syntax validated

---

## Testing

**Test redirect loop detection:**
1. User login
2. Manually force same request 5+ times in quick succession
3. Should see "Sesi Anda mengalami gangguan" message
4. Check logs for `[Redirect Loop Detected]`

**Test session validation:**
1. User login
2. Manually delete user record from DB (or set is_active=0)
3. Load page → should logout + redirect to login
4. Check logs for `[ValidateSsoSession]` warning

**Test session cleanup:**
```bash
php artisan session:cleanup --days=7
# Should show: "Cleaned up X stale session files"
```

---

## What If Issue Happens Again?

**Troubleshooting steps:**

1. **Check logs first:**
   ```bash
   tail -100 storage/logs/laravel.log | grep -i "redirect\|loop\|sso"
   ```

2. **Manual cache clear:**
   ```bash
   php artisan cache:clear
   php artisan route:clear
   php artisan config:clear
   ```

3. **Clean session files:**
   ```bash
   rm -rf storage/framework/sessions/*
   php artisan session:cleanup --days=0
   ```

4. **Restart queue (if needed):**
   ```bash
   php artisan queue:restart
   ```

5. **Check Laravel scheduler running:**
   ```bash
   php artisan schedule:test
   ```

---

## Key Metrics

- **Redirect loop detection:** Within 30 seconds of first occurrence
- **Session validation:** On every protected route request
- **Automatic cleanup:** Daily 3:00 AM
- **User recovery:** Auto logout + redirect to login (not stuck in error)

---

## Future Improvements

- Switch to Redis for sessions (more reliable than file-based)
- Implement session encryption
- Add alerting/notification when redirect loops detected
- Add admin dashboard to monitor session health
