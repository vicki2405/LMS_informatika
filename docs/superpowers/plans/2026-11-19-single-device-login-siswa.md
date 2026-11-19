# Single-Device Login & Device Locking for Siswa Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enforce a strict single-active-device policy for students (`murid` role) preventing concurrent logins across multiple devices/phones, with real-time session invalidation and dedicated teacher/admin device unlock capabilities.

**Architecture:** Database-level tracking of active session and device metadata on the `users` table, evaluated at login time in `LoginRequest`, synchronized in `AuthenticatedSessionController`, guarded continuously by a `CheckSingleDeviceSession` web middleware, and controllable via teacher/admin reset endpoints. Mobile responsive UI in CBT Monitoring and User Management provides a 1-click "Reset Login Perangkat" action.

**Tech Stack:** Laravel 12, Inertia.js v2, Vue 3, Tailwind CSS, Spatie Permission, PHPUnit / Pest.

**Spec:** Option A approved by user ("Single Active Device Lock + Reset oleh Guru/Admin").

## Global Constraints

- Applies exclusively to users with the role `murid` (or students linked via `siswa()`); Admin, Guru, and Wali Kelas roles remain unconstrained for multi-tab/multi-device operations.
- All new/modified UI components containing tabular listings MUST implement mobile-optimized card-list views (`md:hidden`) with touch targets >= 36-44px.
- Backend controllers must remain concise orchestrators with strict authorization checks.
- Compatible with all session drivers (file, database, redis).

## Review Focus

1. Student tries to log in on Device B while already active on Device A -> Must reject Device B with clear, friendly validation error; Device A remains active.
2. Student logs out normally from Device A -> `active_session_id` is cleared; Student can now immediately log in on Device B without assistance.
3. Teacher resets student device login from CBT Monitoring -> `active_session_id` is cleared; Device A is kicked upon its next request; Device B can now log in.
4. Student session naturally expires (> 120 minutes of inactivity) -> New login on another device is permitted automatically without needing manual reset.
5. Guru / Admin logs in from multiple devices -> No blocking or session collisions occur.

---

### Task 1: Database Migration for Active Device Tracking

**Files:**
- Create: `database/migrations/2026_11_19_000001_add_single_device_fields_to_users_table.php`
- Modify: `app/Models/User.php`

**Interfaces:**
- Consumes: Existing `users` table schema.
- Produces: Columns `active_session_id`, `active_device_token`, `active_device_ip`, `active_device_agent`, `last_activity_at` on `User` model.

- [ ] **Step 1: Create migration file**
  Define migration with nullable fields:
  - `active_session_id` (string, nullable, index)
  - `active_device_token` (string, nullable)
  - `active_device_ip` (string, 45, nullable)
  - `active_device_agent` (string, 255, nullable)
  - `last_activity_at` (timestamp, nullable)

- [ ] **Step 2: Update `User.php` model**
  Add the new fields to `$fillable` and add `last_activity_at` to `$casts` as `'datetime'`. Add helper methods `isStudent()`, `isDeviceSessionActive()`, and `resetDeviceSession()`.

- [ ] **Step 3: Verify migration syntax and run migration**
  Verify PHP syntax with `php -l` and run `php artisan migrate --force`.

---

### Task 2: Authentication Enforcement in Web & API Login

**Files:**
- Modify: `app/Http/Requests/Auth/LoginRequest.php`
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Modify: `app/Http/Controllers/Api/AuthApiController.php`

**Interfaces:**
- Consumes: `User::isStudent()`, `User::isDeviceSessionActive()`
- Produces: Single-device validation on login and session cleanup on logout.

- [ ] **Step 1: Update `LoginRequest.php`**
  In `authenticate()`: After `Auth::attempt()`, retrieve `$user = Auth::user()`. If `$user` is a student and has an active session on another device (within session lifetime `config('session.lifetime', 120)` minutes and session ID doesn't match), call `Auth::guard('web')->logout()` and throw `ValidationException::withMessages(['email' => 'Akun Anda sedang aktif di perangkat lain. Harap logout dari perangkat sebelumnya atau hubungi pengawas/guru untuk reset login.'])`.

- [ ] **Step 2: Update `AuthenticatedSessionController.php`**
  In `store()`: After `$request->session()->regenerate()`, update student's `active_session_id`, `active_device_ip`, `active_device_agent`, and `last_activity_at`.
  In `destroy()`: When logging out, clear `active_session_id`, `active_device_token`, `active_device_ip`, `active_device_agent`, and `last_activity_at`.

- [ ] **Step 3: Update `AuthApiController.php`**
  In `login()`: Check if student has active session, reject with 422 if active on another device. On success, record active token/session.
  In `logout()`: Clear active session metadata.

- [ ] **Step 4: Verify PHP syntax**
  Run `php -l` on modified files.

---

### Task 3: Middleware for Continuous Session Guarding

**Files:**
- Create: `app/Http/Middleware/CheckSingleDeviceSession.php`
- Modify: `bootstrap/app.php`

**Interfaces:**
- Consumes: Web requests with authenticated user.
- Produces: Immediate kickout of obsolete / reset sessions.

- [ ] **Step 1: Create `CheckSingleDeviceSession.php` middleware**
  If authenticated user is a student:
  - If `active_session_id` is null (teacher reset) or differs from current session ID: logout web guard, invalidate session, and redirect to login with notification message.
  - If matches: update `last_activity_at` if more than 1 minute has elapsed (throttled write).

- [ ] **Step 2: Register middleware in `bootstrap/app.php`**
  Append `\App\Http\Middleware\CheckSingleDeviceSession::class` to the `web` middleware group.

- [ ] **Step 3: Verify PHP syntax**
  Run `php -l app/Http/Middleware/CheckSingleDeviceSession.php` and `php -l bootstrap/app.php`.

---

### Task 4: Teacher & Admin Reset Login Endpoints

**Files:**
- Modify: `app/Http/Controllers/Guru/GuruCbtController.php`
- Modify: `app/Http/Controllers/Api/GuruApiController.php`
- Modify: `app/Http/Controllers/Admin/AdminDashboardController.php` (or relevant controller)
- Modify: `routes/web.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: Student ID (`siswa_id` or `user_id`).
- Produces: JSON and Inertia responses confirming device session reset.

- [ ] **Step 1: Enhance `GuruCbtController::resetSesiSiswa`**
  Automatically clear student's `active_session_id` and `last_activity_at` when teacher resets a CBT exam session.

- [ ] **Step 2: Add `resetLoginSiswa` method in `GuruCbtController.php`**
  Add standalone method `resetLoginSiswa(Request $request, int $id)` that resets only the device session without altering CBT scores/answers.

- [ ] **Step 3: Register web routes in `routes/web.php`**
  Add `POST /guru/cbt/siswa/{id}/reset-login` -> `guru.cbt.siswa.reset-login`.
  Add `POST /admin/users/{id}/reset-login` -> `admin.users.reset-login`.

- [ ] **Step 4: Register API route in `routes/api.php`**
  Add `POST /api/v1/guru/siswa/{id}/reset-login`.

- [ ] **Step 5: Verify PHP syntax**
  Run `php -l` on modified controllers and route files.

---

### Task 5: UI Integration in Guru CBT Monitoring & User Management

**Files:**
- Modify: `resources/js/Pages/Guru/CbtMonitoringPage.vue`
- Modify: `resources/js/Components/Guru/MenuSiswa.vue`

**Interfaces:**
- Consumes: Routes `guru.cbt.siswa.reset-login`, student session metadata.
- Produces: Interactive buttons with SweetAlert confirmation and mobile responsiveness.

- [ ] **Step 1: Add "🔓 Reset Login Perangkat" in `CbtMonitoringPage.vue`**
  In desktop table and mobile card-view:
  - Add button "🔓 Reset Login" for each student.
  - Implement `resetLogin(siswaId, namaSiswa)` with SweetAlert confirmation.
  - Call `route('guru.cbt.siswa.reset-login', siswaId)` via axios or router.post.
  - Show success toast upon reset.

- [ ] **Step 2: Add Reset Login action in `MenuSiswa.vue`**
  Provide a quick device unlock button in the student data table and mobile view.

- [ ] **Step 3: Validate Vue SFC syntax**
  Run node script with `@vue/compiler-sfc` to ensure zero template/script errors.

---

### Task 6: Comprehensive Automated Feature Tests

**Files:**
- Create: `tests/Feature/SingleDeviceLoginTest.php`

**Interfaces:**
- Consumes: PHPUnit / Laravel test suite.
- Produces: 100% passing tests for all 5 review focus items.

- [ ] **Step 1: Write feature tests**
  - Test student login registers active session.
  - Test second device login attempt is rejected with expected validation error.
  - Test normal logout clears session and allows login on another device.
  - Test teacher reset allows immediate login on new device.
  - Test obsolete session is invalidated on next request.
  - Test non-student accounts can login concurrently without restriction.

- [ ] **Step 2: Run test suite and verify**
  Run `php artisan test tests/Feature/SingleDeviceLoginTest.php`.
