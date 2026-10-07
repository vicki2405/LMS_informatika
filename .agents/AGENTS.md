# Project Coding & Architectural Standards

## 1. Modular Component Structure
- Every new menu, view, or complex feature MUST be created as an independent, modular Vue component under `resources/js/Components/` (e.g. `resources/js/Components/Guru/`).
- Page files (like `resources/js/Pages/Guru/Dashboard.vue`) must remain clean, concise orchestrator pages (< 200 lines).
- Modal dialogs, form overlays, and menu-specific logic must be encapsulated directly within their corresponding menu component file.

## 2. Synchronous UI & Layout Implementation
- Whenever a new menu, route, or backend module is created, its corresponding interactive UI layout, responsive view structure, and component template MUST be created immediately alongside it.
- **Mobile Responsive Design**: Every component containing a tabular data listing (`table`) or data list MUST implement a mobile-optimized card-list view (`md:hidden`) alongside the desktop table view (`hidden md:block`). Do not rely solely on horizontal scrolling.
- **Touch targets**: All interactive elements (buttons, inputs) in the mobile view must have comfortable touch targets (minimum height `36px` to `44px`).

## 3. Wajib Konfirmasi Sebelum Perbaikan (Ask Before Fixing)
- Sebelum melakukan perbaikan atau modifikasi kode apa pun, selalu jelaskan analisis masalah/rencana perbaikan dan tanyakan/minta konfirmasi persetujuan dari user terlebih dahulu sebelum melakukan perubahan kode.

## 4. MVC & Separation of Concerns (Maintainability)
- Structure all code cleanly according to MVC principles for maximum maintainability.
- Backend controllers must remain concise orchestrators (< 150-200 lines), extracting large data mappings into dedicated helper methods.
- Every menu across all 4 role views (Admin, Guru, Wali Kelas, Siswa) MUST be backed by a Controller method and secured by Spatie Role Middleware.
- Data sent to views (such as Teachers and their assigned Subjects) MUST be backed by relational database fields and dynamically synchronized across all dashboards.
- **Full-Stack MVC & REST API Standards**: When creating a new menu or business entity, the following five layers must be created/synchronized concurrently:
  1. **Database Schema**: A Laravel migration defining the tables, indexes, and constraints.
  2. **Eloquent Model**: A Model file documenting table name, `$fillable` fields, and relationships.
  3. **Web Route & Controller**: An Inertia web controller method passing records to the Vue views, registered in `routes/web.php`.
  4. **REST API Endpoint**: A Sanctum-authenticated API endpoint in the corresponding API controller under `app/Http/Controllers/Api/` and registered in `routes/api.php` (e.g. `/api/v1/...`) for external integrations.
  5. **Frontend CRUD UI**: A modular Vue 3 component integrating all CRUD operations using Inertia requests.
