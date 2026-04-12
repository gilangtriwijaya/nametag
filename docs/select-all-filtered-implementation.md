# Implementation Summary: Select All Filtered Employees

## ✅ What's Been Implemented

### Backend Changes

**1. New Endpoint: `selectAllFiltered()` in EmployeeController**
- Route: `POST /employees/select-all-filtered`
- Accepts: Filter parameters (q, status, opd_id, opd_unit_id, unit_kerja_id)
- Action: Queries all employees matching filters
- Stores in Session: `employees_select_all_filtered` with filters + total count + IDs
- Returns JSON: `{ ok, message, total_count, ids[] }`

**2. New Endpoint: `clearSelectAllFiltered()` in EmployeeController**
- Route: `POST /employees/clear-select-all`
- Action: Removes session state
- Returns JSON: `{ ok, message }`

**3. Modified: `dispatch()` in NametagBatchController**
- NEW: Checks `use_filtered_session` flag in request
- If flag + session state exists:
  - Re-queries database with saved filters
  - Gets ALL employees matching those filters (not just provided IDs)
  - Validates (AKTIF + OPD scope) before processing
- Otherwise: Uses provided IDs (original behavior)
- Logs selection mode for debugging

**4. New Routes in routes/web.php**
```php
POST /employees/select-all-filtered  → selectAllFiltered()
POST /employees/clear-select-all     → clearSelectAllFiltered()
```

### Frontend Changes

**1. Global State Variable**
```javascript
let selectAllFilteredState = null;  // Stores { total, ids[], filters }
```

**2. Enhanced `chkAll` Checkbox Handler**
- When CHECKED:
  - POSTs to `/employees/select-all-filtered` with current filters
  - Stores response in `selectAllFilteredState`
  - Shows "✓ N pegawai dipilih (filter aktif)"
  
- When UNCHECKED:
  - POSTs to `/employees/clear-select-all`
  - Clears session state
  - Clears `selectAllFilteredState` variable
  - Unchecks visible checkboxes

**3. Updated `refreshBatchInfo()` Function**
- Shows different message if `selectAllFilteredState` is active:
  - Active: `"✓ N pegawai dipilih (filter aktif) untuk batch nametag."`
  - Manual: `"N pegawai dipilih untuk batch nametag."`
  - None: `"Tidak ada pegawai yang dipilih."`

**4. Enhanced `startEmployeesBatch()` Function**
- Detects selection source:
  - If `selectAllFilteredState` active → uses cached IDs
  - Otherwise → uses `.chkRow:checked` rows
- Adds `use_filtered_session=1` flag when using filtered mode
- Shows toast with selection mode: "(manual)" or "(filtered)"

---

## 🔄 How It Works (User Flow)

### Scenario: Select All 150 Employees with Active Filters

1. **User has filters active** (e.g., OpD="A", Status="AKTIF")
   - Table shows: 20/150 employees matching filter
   - UI shows pagination: "21-40 dari 150 data"

2. **User clicks "Pilih Semua" checkbox**
   - Frontend POSTs: `POST /employees/select-all-filtered?opd_id=1&status=AKTIF`
   - Backend queries: All 150 matching employees
   - Backend stores session: `{ filters, total: 150, ids: [array of 150] }`
   - Frontend stores: `selectAllFilteredState = { total: 150, ids: [...], filters: "..." }`
   - UI updates: "✓ 150 pegawai dipilih (filter aktif)"

3. **User navigates to different page** (optional)
   - Selection persists in session (not lost)
   - UI still shows "✓ 150 pegawai dipilih (filter aktif)"
   - Visible checkboxes still checked

4. **User clicks "Proses Massal"**
   - Frontend POSTs: `POST /nametag/batch/dispatch` with:
     - `ids[]=1&ids[]=2&...` (original 20 visible IDs, or cached 150 if available)
     - `use_filtered_session=1` (flag indicating filtered mode)
   - Backend dispatch() sees flag:
     - Checks session: `employees_select_all_filtered` exists ✓
     - Rebuilds query with saved filters (opd_id, status, etc.)
     - Queries database → gets all 150 employees again
     - Validates all 150 (AKTIF + OPD scope)
     - Dispatches batch job for all 150
   - UI shows: "Batch dikirim (150 item)."

---

## 🧪 Test Cases

### Test 1: Basic Select All
1. Navigate to Employees page
2. Apply filter: OpD = "Kabupaten A" (shows 45 employees)
3. Click "Pilih Semua" checkbox
4. Verify UI shows: "✓ 45 pegawai dipilih (filter aktif)"
5. Navigate to next page (if exists)
6. Verify UI still shows: "✓ 45 pegawai dipilih (filter aktif)"
7. Click "Proses Massal"
8. Verify batch processes all 45 (not just 20 visible)

### Test 2: Deselect All
1. From Test 1, after selecting all (45)
2. Uncheck "Pilih Semua" checkbox
3. Verify UI shows: "Tidak ada pegawai yang dipilih."
4. Verify visible rows are unchecked
5. Verify session cleared (no "filter aktif" message)

### Test 3: Mixed Selection
1. Select some visible rows manually (e.g., 5 rows)
2. Verify UI shows: "5 pegawai dipilih untuk batch nametag."
3. Click "Pilih Semua"
4. Verify UI shows: "✓ 45 pegawai dipilih (filter aktif)"
5. Apply different filter
6. Verify new filter result is used

### Test 4: No Filters
1. Clear all filters (show all 350 employees)
2. Click "Pilih Semua"
3. Verify UI shows: "✓ 350 pegawai dipilih (filter aktif)"
4. Click "Proses Massal"
5. Verify batch processes all 350

### Test 5: OPD Scope (Limited User)
1. Login as user with limited OPD scope (can only see OpD "A")
2. Click "Pilih Semua"
3. Verify UI shows only employees from OpD "A"
4. Verify backend doesn't process employees from other OPDs (filtered)

---

## 📋 Configuration & Limits

**Current Limits in `selectAllFiltered()` endpoint:**
- Maximum IDs cached: 10,000
- If more than 10,000 employees match filters:
  - total_count returned correctly
  - IDs array empty (would be too large to cache)
  - Backend re-queries when batch processing

**Session Storage:**
- Key: `employees_select_all_filtered`
- TTL: Cleared when checkbox unchecked OR on user logout
- Survives: Page navigation, F5 refresh, going to other pages

---

## 🔧 Debugging

1. **Check Session State:**
   - Backend: `\Log::info(session('employees_select_all_filtered'));`
   - Frontend: `console.log(selectAllFilteredState);`

2. **Check Batch Processing:**
   - Backend logs when using filtered session:
     ```
     [nametag: dispatch using filtered session] filters_count: 2, result_ids_count: 150
     ```

3. **Manual Test Queries:**
   ```php
   // In tinker:
   $user = User::find(1);
   $request = Request::create('?opd_id=1&status=AKTIF', 'GET');
   $query = app(EmployeeQueryService::class)->queryIndex($request, $user);
   $query->count();  // Should show 150
   ```

---

## ⚠️ Important Notes

1. **IDs sent vs IDs used in batch:**
   - Frontend sends visible row IDs (or cached filtered IDs)
   - Backend sees `use_filtered_session=1` flag
   - Backend ignores sent IDs and re-queries with filters
   - This ensures data consistency (no stale ID list)

2. **Permission Validation:**
   - Backend still validates:
     - User has `create` permission (can start batch)
     - All employees are AKTIF
     - User OPD scope is respected (for non-superadmin)

3. **Session Cleanup:**
   - Session cleared when user unchecks "Pilih Semua"
   - Session cleared on user logout (Laravel default)
   - Session cleared if user navigates without selecting

---

## 🎯 Benefits Over Alternative Approaches

**vs. Option A (Frontend-only):**
- ✅ Better scalability (10k employees won't slow down UI)
- ✅ Clean separation of concerns

**vs. Option C (Hybrid with separate button):**
- ✅ More intuitive (standard checkbox behavior)
- ✅ Less UI clutter

---
