# Audit Web/Inertia → API untuk Next.js

Tanggal: 10 September 2026. Versi terpasang: Laravel 13.30.1, Inertia Laravel 3.3.2, Sanctum 4.3.3, Fortify 1.39.0, Pest 5.1.3.

## Hasil dan batas kesetaraan

Seluruh kebutuhan data aplikasi dari `routes/web.php`, `routes/settings.php`, shared middleware, dan view callback Fortify dipetakan di bawah. Route web, controller web, model, schema, dan service bisnis tidak diubah. Perubahan frontend yang sudah ada pada `resources/js/pages/admin/pegawais/index.tsx` bukan bagian pekerjaan ini.

API aplikasi tetap menggunakan `Illuminate\Http\Resources\JsonApi\JsonApiResource`: `data.id`, `data.type`, `data.attributes`, `relationships`, `included`, `links`, dan `meta`. PDF tetap binary; operasi hapus/ganti password baru menggunakan 204. Endpoint auth Fortify menggunakan JSON bawaan Fortify dan endpoint API lama tetap mempertahankan kontraknya.

Kesetaraan bersifat fungsional setelah pemetaan representasi JSON:API, bukan persamaan struktur JSON mentah. Detail pegawai membutuhkan dua endpoint yang sudah ada: detail pegawai dan riwayat lembur. Tidak dibuat endpoint detail/riwayat duplikat.

## Inventaris route aplikasi

`{uuid}` di tabel berarti binding eksplisit `{lembur:uuid}` atau `{pegawai:uuid}`, bukan primary key numerik. `HEAD` mengikuti GET. Status UPDATED pada route admin juga mencakup penambahan middleware `verified` pada grup agar setara dengan web.

| Web route | API pengganti | Controller web → API / method | Status |
| --- | --- | --- | --- |
| GET `/dashboard` | GET `/api/admin/dashboard` | `Admin\DashboardController` → `Api\Admin\DashboardController::__invoke` | UPDATED |
| GET `/dashboard/lemburs` | GET `/api/admin/lemburs` | `Admin\LemburController` → `Api\Admin\LemburController::index` | UPDATED |
| GET `/dashboard/lemburs/export` | GET `/api/admin/lemburs/export` | Controller lembur admin web → API, `export` | UPDATED |
| POST `/dashboard/lemburs/lock` | POST `/api/admin/lemburs/bulk-lock` | Controller lembur admin web → API, `bulkLock` | UPDATED |
| GET `/dashboard/lemburs/{uuid}` | GET `/api/admin/lemburs/{uuid}` | Controller lembur admin web → API, `show` | UPDATED |
| DELETE `/dashboard/lemburs/{uuid}` | DELETE `/api/admin/lemburs/{uuid}` | Controller lembur admin web → API, `destroy` | CREATED |
| POST `/dashboard/lemburs/{uuid}/lock` | POST `/api/admin/lemburs/{uuid}/lock` | Controller lembur admin web → API, `lock` | UPDATED |
| GET `/dashboard/pegawai` | GET `/api/admin/pegawai` | `Admin\PegawaiController` → `Api\Admin\PegawaiController::index` | UPDATED |
| GET `/dashboard/pegawai/{uuid}` | GET `/api/admin/pegawai/{uuid}` **dan** GET `/api/admin/pegawai/{uuid}/lemburs` | Controller pegawai admin web `show` → API `show` + `lemburs` | UPDATED |
| PUT `/dashboard/pegawai/{uuid}` | PUT `/api/admin/pegawai/{uuid}` | Controller pegawai admin web → API, `update` | UPDATED |
| GET `/settings/profile` | GET `/api/settings/profile` | `Settings\ProfileController::edit` → `Api\Settings\ProfileController::show` | CREATED |
| PATCH `/settings/profile` | PATCH `/api/settings/profile` | Controller profil web → API, `update` | CREATED |
| DELETE `/settings/profile` | DELETE `/api/settings/profile` | Controller profil web → API, `destroy` | CREATED |
| GET `/settings/security` | GET `/api/settings/security` | `Settings\SecurityController::edit` → `Api\Settings\SecurityController::show` | CREATED |
| PUT `/settings/password` | PUT `/api/settings/password` | Controller keamanan web → API, `update` | CREATED |
| Shared props semua halaman | GET `/api/frontend-context` | `HandleInertiaRequests::share`, `HandleAppearance::handle`, callback Fortify → `Api\FrontendContextController::__invoke` | CREATED |

## Rantai route → controller → query → props → JSON

### Dashboard

`GET /dashboard` → `Admin\DashboardController::__invoke` → `LemburQuery::filters(query)` → `AdminDashboardService::forMonth(bulan)` → Inertia `summary`.

`GET /api/admin/dashboard` → controller API yang sama domainnya → service dan input query yang sama → `AdminDashboardResource` → `data.attributes` berisi seluruh summary.

Query: `Lembur::complete()`, `whereBetween(tanggal_kegiatan, startOfMonth/endOfMonth)`, hitung hari kerja/libur melalui SQL sesuai driver; tarif dari `LemburService::totalUpahDariRingkasan`. Grafik memakai semua bulan tahun terpilih, hanya complete, `selectRaw`, `groupBy(month)`, lalu mengisi 12 bulan termasuk nilai nol. Draft dan locked tidak masuk statistik, sama seperti web.

### Daftar lembur dan dropdown

Web `index` → `LemburQuery::filters` → `forAdmin` → `paginate(15)->withQueryString()->through(AdminLemburPresenter::summary)` → props `lemburs`, `filters`, `pegawaiOptions`.

API `index` → service query yang sama → pagination default 15, `withQueryString` → `AdminLemburResource::collection` → `data`, `included`, `links`, `meta`, `meta.filters`, **`meta.pegawaiOptions`**.

Query bersama memuat `user:id,uuid,name,nip,jabatan,kode_biro,image`; filter rentang bulan/tahun dan status; filter pegawai dengan `whereHas(user)` berdasarkan UUID atau ID numerik; search terkelompok pada `nama_kegiatan OR lokasi_kegiatan`; filter weekday/weekend sesuai driver database. Sorting tetap `tanggal_kegiatan DESC, id DESC`. Dropdown memakai `PegawaiQuery::activeOptions`: hanya role outsourcing, `is_active=true`, urut nama, semua hasil tanpa pagination, hanya UUID/nama/NIP.

API sebelumnya menolak `jenis_hari=semua` yang digunakan frontend web. Form Request baca sekarang membiarkan normalisasi scalar oleh service yang sama: bulan tidak dikenal → bulan sekarang; status tidak dikenal → complete; jenis hari tidak dikenal → semua. Batas panjang search tambahan yang tidak ada di web dihapus. Input array untuk scalar dan pagination yang tidak valid tetap 422. `per_page=1..100` tetap didukung sebagai kemampuan API tambahan; jangan mengirimkannya bila ingin default web.

### Detail lembur

Web `show(Lembur)` → binding UUID → `loadMissing(['user','lockedBy'])` → `AdminLemburPresenter::detail` → prop `lembur`.

API `show(Lembur)` → binding dan eager loading identik → `AdminLemburResource` + `AdminPegawaiResource` untuk relasi user/lockedBy. Field permission `can_delete` yang hilang ditambahkan.

Pemetaan representasi yang mempertahankan kontrak API existing:

| Inertia | JSON:API | Keterangan |
| --- | --- | --- |
| `lembur.id` | `data.id` | ID string sesuai JSON:API; payload bulk-lock tetap integer ID database |
| UUID, tanggal, kegiatan, lokasi, waktu pulang, status, upah, can_lock, can_delete | `data.attributes.<field>` | Nilai sama |
| `jenis_hari=kerja/libur` | `jenis_hari=hari_kerja/hari_libur` | Enum API lama dipertahankan; frontend memetakan dua nilai ini |
| `pegawai.uuid/name/nip/jabatan` | `relationships.user.data` → objek user di `included` | Join menurut **type dan id**, jangan posisi array |
| `foto_kegiatan`, `foto_pulang` | `foto_kegiatan_url`, `foto_pulang_url` | Sumber sama: public storage URL |
| Timestamp foto dan lock `Y-m-d H:i` | Timestamp `Y-m-d H:i:s` | API mempertahankan presisi detik; waktu yang sama |
| `locked_by` berupa nama | `relationships.lockedBy.data` → `included.attributes.name` | Relasi null berarti belum ada pengunci |

### Lock, bulk-lock, dan hapus

Web/API `lock` → ID dari binding UUID → `LemburLockService::lock(actor, [id])`. API mengembalikan record yang di-refresh dan relasi user/lockedBy; web redirect dan toast.

Web/API `bulkLock` → **`BulkLockLembursRequest` yang sama**: array `ids` wajib, minimum 1, integer, distinct, exists → service lock sama. Service melakukan transaksi, `whereKey`, `lockForUpdate`, memastikan semua data ada dan `canBeLocked` (complete dan locked_at null); seluruh batch ditolak 422 jika salah satu tidak valid. Hasil API melalui `AdminBulkLockResultResource`: request UUID dan `locked_count`.

Web/API `destroy` → binding UUID → tolak `status=locked`; hapus kedua path foto nonkosong dari public storage; hapus model. Web menyampaikan penolakan lewat toast/redirect, API 403. Sukses API 204. Tidak ditambahkan syarat pemilik pada API admin karena web admin juga boleh menghapus data pegawai lain.

### Export

Web/API `export` → normalisasi filter → **`LemburQuery::forAdmin(filters)->get()`** tanpa pagination → **`LemburPdfExporter::render`** → stream PDF `lembur-YYYY-MM.pdf`. Query, urutan, relasi, foto lokal, header dan template PDF tetap sama. Header `Content-Disposition` diekspos melalui CORS untuk nama file di Next.js.

### Daftar pegawai

Web `index` → `PegawaiQuery::filters` → `forAdmin` → paginate 15 → transform UUID, nama, jabatan, NIP, kode biro, email, is_active, lemburs_count → props `pegawai`, `filters`.

API `index` → service sama → default 15 → `AdminPegawaiResource::collection` + `meta.filters` + pagination Laravel. Resource sekarang menyertakan **email** jika kolom dimuat.

Query tetap `User::outsourcing()`, `whereJsonContains(role, outsourcing)`, `withCount('lemburs')`, search terkelompok nama/NIP/jabatan/kode biro, filter active/inactive, sorting `name ASC`. Status kosong/null berarti tanpa filter; string lain termasuk all juga tidak membatasi status seperti web. Hitungan lembur meliputi semua status/tanggal, bukan hanya daftar bulan aktif.

### Detail dan riwayat pegawai

Web `show` → binding User UUID → `abort_unless(hasRole('outsourcing'),404)` → prop `pegawai` (termasuk image) → `LemburQuery::filters([...query, pegawai=UUID route])` → `forAdmin()->paginate(10)->withQueryString()->through(summary)` → props `history`, `filters`.

API `show` menjaga role/404 dan mengembalikan `AdminPegawaiResource`; **image** sekarang tersedia. Endpoint existing `lemburs` melakukan guard role yang sama dan menimpa filter pegawai dari query dengan UUID route, lalu query yang sama. Default pagination diperbaiki **15 → 10**, `per_page` eksplisit tetap dapat dipilih. Hasil berupa collection `AdminLemburResource`, `meta.filters`, pagination dan relasi included. Frontend harus memanggil kedua endpoint dengan parameter bulan/status/jenis hari/search/page pada endpoint riwayat.

### Update pegawai

Web/API `update` → binding UUID → **`AdminPegawaiUpdateRequest` yang sama** + guard outsourcing 404 → normalisasi boolean `is_active`; jika `status` ada, nilainya mengungguli `is_active`; hash password bila tidak kosong; whitelist `name,jabatan,nip,is_active,password`; save. API mengembalikan fresh `AdminPegawaiResource`; web redirect dengan session success. Email/kode biro/role tidak dapat diubah lewat payload ini.

### Profil dan password

Web `ProfileController::edit` → current user + `instanceof MustVerifyEmail` + session status; current user sebelumnya ada di shared props. API `show` → `UserResource(current user)` + `meta.mustVerifyEmail`, `meta.status`.

Web/API profile `update` → **`ProfileUpdateRequest` + `ProfileValidationRules` yang sama** → fill validated name/email → email_verified_at dikosongkan hanya bila email berubah → save. API resource user + `meta.message`; tidak menerima role/password tambahan.

Web/API profile `destroy` → **`ProfileDeleteRequest` + current_password yang sama** → hapus user dan keluar sesi. API juga membersihkan token Sanctum milik akun yang dihapus agar token yang tidak mempunyai foreign-key cascade tidak tertinggal. API 204; token pengguna lain tidak disentuh.

Web/API security `update` → **`PasswordUpdateRequest` + `PasswordValidationRules` yang sama** → update password dengan cast hashed pada User. Keduanya menggunakan `verified` dan throttle 6 per menit. API 204.

### Pengaturan keamanan

Web `SecurityController::edit` → `TwoFactorAuthenticationRequest` → flags Fortify → query `user->passkeys()->select(id,name,credential,created_at,last_used_at)->latest()->get()` → map `id,name,authenticator,created_at_diff,last_used_at_diff` → passwordRules; bila 2FA aktif, `ensureStateIsValid`, twoFactorEnabled, requiresConfirmation.

API `SecurityController::show` memakai request, query, sorting, transformasi dan state transition yang sama → `SecurityResource.data.attributes`. Credential mentah tidak dikirim. Daftar passkey hanya milik current user, tanpa pagination seperti web. Fitur nonaktif menghasilkan flags false dan passkeys kosong; field 2FA opsional tidak dipaksakan.

Endpoint ini membutuhkan **sesi web Sanctum**, verified dan password.confirm; bearer saja mendapat 401. Sesi tanpa konfirmasi mendapat 423 JSON. Ini disengaja: Fortify menyimpan konfirmasi password, challenge passkey, dan transisi 2FA dalam sesi. Gunakan POST `/user/confirm-password` atau konfirmasi passkey existing, bukan endpoint konfirmasi token baru.

## Shared Inertia dan halaman auth

`GET /api/frontend-context` tersedia untuk guest maupun pengguna melalui resolusi guard Sanctum. Resource type `frontend-contexts`, id `current`.

| Sumber web | Data API |
| --- | --- |
| `HandleInertiaRequests::share.name` | `data.attributes.name` |
| `auth.user` | `data.attributes.auth.user` berisi objek `{id,type,attributes}` dari UserResource; guest null |
| `sidebarOpen` dari cookie sidebar_state | Field sama pada attributes, default true |
| `HandleAppearance` / cookie appearance | `data.attributes.appearance`, default system |
| Login `canResetPassword` | Field sama pada attributes |
| Login/forgot-password/verify-email/profile session status | `data.attributes.status`, atau profile `meta.status`; null untuk stateless |
| Register/reset-password `passwordRules` | `data.attributes.passwordRules` |
| Gate admin | `meta.canAccessAdminPanel` |
| Validasi/session errors | Response API 422 `errors`, bukan shared error bag Inertia |
| Toast/redirect sukses | HTTP status, resource hasil dan/atau meta.message; Next.js menampilkan notifikasi sendiri |

UserResource memakai allowlist semua atribut pengguna nonsecret yang dikirim shared Inertia: name/email/role/uuid/image/jabatan/NIP/kode biro/is_active/email_verified_at/two_factor_confirmed_at/created_at/updated_at; ID menjadi data.id. Password, remember_token, secret 2FA dan recovery codes tidak terekspos di resource ini.

## Route browser yang tidak membutuhkan endpoint duplikat

| Web route | Alasan / pengganti data | Status |
| --- | --- | --- |
| `/` | Redirect ke `/dashboard`; navigasi di Next.js | OK |
| `/settings` | Redirect ke `/settings/profile`; navigasi di Next.js | OK |
| GET `/settings/appearance` | Inertia tanpa props domain/query; preferensi berasal dari cookie/local state, context tersedia | OK |
| GET `/.well-known/passkey-endpoints` | Sudah JSON berisi URL enroll/manage browser, tidak ada query bisnis; tetap browser discovery | OK |
| GET `/login` | Render halaman; canResetPassword/status ada di context | OK |
| GET `/register` | Render halaman; passwordRules ada di context | OK |
| GET `/forgot-password` | Render halaman; status ada di context dan respons POST Fortify | OK |
| GET `/reset-password/{token}` | Token dari URL route, email dari query URL, bukan query DB tersembunyi; rules dari context | OK |
| GET `/email/verify` | Notice/status; data pengguna/context tersedia; signed verification callback dipertahankan | OK |
| GET `/two-factor-challenge` | Render form; tidak ada props domain; state challenge tetap di sesi Fortify | OK |
| GET `/user/confirm-password` | Render form tanpa props domain; POST existing melakukan validasi | OK |
| GET `/email/verify/{id}/{hash}` | Callback browser bertanda tangan; jangan membuat ulang signature atau menghapus route | OK |

Tidak ada create/store/edit pegawai atau create/store/update lembur **admin** pada web yang perlu diciptakan. Operasi lembur milik pegawai sudah ada di API terpisah; tidak disamakan dengan akses admin.

## Endpoint Fortify existing yang dipakai kembali

Kirim `Accept: application/json`, cookies, dan CSRF untuk seluruh operasi ini. Response native Fortify dipertahankan; tidak diduplikasi di `api.php` karena sudah dapat dikonsumsi sebagai endpoint JSON.

| Endpoint | Controller / behavior | Status |
| --- | --- | --- |
| GET `/sanctum/csrf-cookie` | Sanctum CsrfCookieController, inisialisasi cookie CSRF | OK |
| POST `/login` | Fortify AuthenticatedSessionController::store, pipeline sesi + 2FA + throttle login | OK |
| POST `/logout` | AuthenticatedSessionController::destroy, logout sesi | OK |
| POST `/register` | RegisteredUserController::store → **CreateNewUser**, validasi existing, role pegawai/default image/jabatan tetap | OK |
| POST `/forgot-password` | PasswordResetLinkController::store → broker password existing | OK |
| POST `/reset-password` | NewPasswordController::store → broker → **ResetUserPassword** | OK |
| POST `/email/verification-notification` | EmailVerificationNotificationController::store; auth:web, throttle 6/menit | OK |
| POST `/user/confirm-password` | ConfirmablePasswordController::store; mengonfirmasi sesi | OK |
| GET `/user/confirmed-password-status` | ConfirmedPasswordStatusController::show | OK |
| POST `/two-factor-challenge` | TwoFactorAuthenticatedSessionController::store, TOTP/recovery code + throttle | OK |
| POST/DELETE `/user/two-factor-authentication` | TwoFactorAuthenticationController::store/destroy, password.confirm | OK |
| POST `/user/confirmed-two-factor-authentication` | ConfirmedTwoFactorAuthenticationController::store | OK |
| GET `/user/two-factor-qr-code` | TwoFactorQrCodeController::show | OK |
| GET `/user/two-factor-secret-key` | TwoFactorSecretKeyController::show; endpoint khusus dengan konfirmasi password | OK |
| GET/POST `/user/two-factor-recovery-codes` | RecoveryCodeController::index/store; baca/regenerasi dengan konfirmasi password | OK |
| GET `/passkeys/login/options`, POST `/passkeys/login` | PasskeyLoginController::index/store; challenge sesi, guest, throttle | OK |
| GET `/passkeys/confirm/options`, POST `/passkeys/confirm` | PasskeyConfirmationController::index/store; auth, throttle | OK |
| GET `/user/passkeys/options`, POST `/user/passkeys` | PasskeyRegistrationController::index/store; auth, password.confirm, throttle | OK |
| DELETE `/user/passkeys/{passkey}` | PasskeyRegistrationController::destroy; auth, password.confirm, ownership | OK |

## API existing tanpa padanan web

| Endpoint API | Audit | Status |
| --- | --- | --- |
| GET `/api` | Pesan health/welcome, tetap | OK |
| POST `/api/auth/login` | Resource sekarang memuat identitas lengkap; tetap token login lama. Tidak ekuivalen dengan pipeline Fortify 2FA, lihat review | REVIEW |
| DELETE `/api/auth/logout` | Revoke current bearer token; sekarang juga aman untuk sesi Sanctum dan invalidasi session | UPDATED |
| GET `/api/lemburs` | Hanya pemilik, foto kegiatan/pulang nonnull, whereMonth, urut tanggal desc, ringkasan upah; bukan query admin | REVIEW |
| POST `/api/lemburs` | StoreLemburRequest; unik user+tanggal, upload, LemburService::store, status berdasarkan kelengkapan bukti; tetap | OK |
| GET `/api/lemburs/detail/{uuid}` | Binding UUID, ownership 404, LemburResource | OK |
| PUT `/api/lemburs/{uuid}` | UpdateLemburRequest dan service existing; aturan unik/lock perlu review di bawah | REVIEW |
| DELETE `/api/lemburs/delete/{uuid}` | Ownership 404, locked 403, hapus foto + model | OK |
| GET `/api/lemburs/draft` | Pengelompokan OR diperbaiki: user_id AND (foto_kegiatan IS NULL OR foto_pulang IS NULL) | UPDATED |
| GET `/api/lemburs/kalender` | Semua tanggal milik pemilik dengan kedua foto, tanggal/uuid/lembur_id, urut desc | OK |
| GET `/api/lemburs/total-upah` | Pemilik, kedua foto, tahun sekarang, ringkasanUpah | OK |
| GET `/api/lemburs/export` | Pemilik, complete, kedua foto, tahun+bulan YYYY-MM, exporter bersama | REVIEW |
| GET `/api/lemburs/{uuid}/upah` | Method sebelumnya tidak ada; sekarang ownership 404 + hitungUpah existing → `data.upah` | UPDATED |

## Review dan hal yang belum dapat diklaim setara

Tidak ditemukan kebutuhan data halaman aplikasi web yang belum mempunyai endpoint. Batas berikut tetap perlu diperhatikan:

1. **Login bearer lama bukan pengganti login web.** AuthController mengecek email/password dan menerbitkan token tanpa challenge 2FA maupun limiter login Fortify. Migrasi ini tidak mengubah protokol client token lama. Next.js harus menggunakan POST `/login` dan `/two-factor-challenge` dengan sesi. Jika token login harus mendukung admin dengan jaminan MFA yang sama, diperlukan keputusan perubahan kontrak token; jangan mengklaim jalur token lama setara.
2. **Verified belum menolak user unverified dalam model sekarang.** User tidak implement `MustVerifyEmail`; kondisi ini sudah berlaku di web. Middleware API disamakan, interface/aturan bisnis tidak diubah. Mengaktifkan kewajiban verifikasi email merupakan perubahan terpisah.
3. **Domain deployment belum diketahui.** Sesuaikan `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, cookie HTTPS, serta APP_URL. Sanctum sesi membutuhkan frontend/backend pada top-level domain yang sama atau reverse proxy yang sesuai. Port development Next.js default localhost:3000 sudah ada pada konfigurasi.
4. **Passkey lintas host perlu konfigurasi eksplisit.** `PASSKEYS_ALLOWED_ORIGINS` menambahkan origin Next.js tanpa menghapus APP_URL. `PASSKEYS_RELYING_PARTY_ID` dapat diatur untuk domain RP yang benar. RP id default tetap hostname APP_URL agar kredensial web existing tidak rusak. Kredensial pada RP id lama tidak otomatis bermigrasi ke RP id baru. Uji WebAuthn nyata di browser/perangkat masih diperlukan.
5. **URL email/discovery tetap browser existing.** Link reset password, signed verification dan `.well-known/passkey-endpoints` masih memakai route Laravel. Saat cutover UI penuh, Next.js/proxy perlu menangani URL browser tersebut; token/email tersedia dari URL dan endpoint POST tidak membutuhkan HTML Laravel. Signed callback tetap harus diverifikasi backend.
6. **API lembur pegawai memiliki kontrak lama berbeda.** Index hanya whereMonth (tanpa tahun), sedangkan export memakai YYYY-MM; export belum memakai Form Request untuk parameter bulan. Update memakai unique tanggal lintas seluruh user (store unik per user), dan otorisasi update mengecek locked_at, sedangkan destroy mengecek status locked. Tidak diseragamkan karena bukan endpoint pengganti web dan akan mengubah business logic existing.
7. **Controller registrasi API yang tidak terdaftar** masih merujuk RegisterService dan domain PMB yang tidak tersedia. Tidak dipasang menjadi route baru; registrasi web/Next memakai Fortify CreateNewUser yang valid.
8. **Input pagination malformed** masih divalidasi API dengan 422, sementara web memakai fallback paginator. Kesetaraan diuji untuk pagination valid/default dan normalisasi filter scalar web; tidak meniru penerimaan input array/error web.

## Integrasi Next.js

1. GET `/sanctum/csrf-cookie` dengan `credentials: 'include'`.
2. Baca/decode cookie XSRF-TOKEN, kirim sebagai X-XSRF-TOKEN pada mutasi bersama credentials dan `Accept: application/json`.
3. POST `/login`; bila `two_factor=true`, selesaikan POST `/two-factor-challenge`. Jangan mengganti alur ini dengan token login lama untuk parity keamanan.
4. GET `/api/frontend-context` dan endpoint halaman terkait. Untuk data JSON:API juga dapat menggunakan `Accept: application/vnd.api+json`.
5. Untuk keamanan, POST `/user/confirm-password`, kemudian GET `/api/settings/security`. Tangani 401/419 sebagai sesi tidak valid, 423 sebagai perlu konfirmasi, 403 sebagai akses ditolak, 422 sebagai validasi.
6. Join relasi JSON:API menurut type+id; petakan jenis_hari seperti tabel. Untuk dropdown pakai meta.pegawaiOptions, untuk filter pakai meta.filters. Jangan mengambil hanya data dan membuang included/meta.

## Resource dan Request

Reused: `AdminDashboardResource`, `AdminLemburResource`, `AdminPegawaiResource`, `AdminBulkLockResultResource`, `Api\UserResource`, `Api\LemburResource`. Diubah: field can_delete pada AdminLemburResource, email/image pada AdminPegawaiResource, allowlist identitas lengkap pada UserResource. Dibuat: `Api\FrontendContextResource`, `Api\SecurityResource`, keduanya untuk payload halaman non-model; tidak ada resource model duplikat.

Reused untuk mutasi: `BulkLockLembursRequest`, `AdminPegawaiUpdateRequest`, `ProfileUpdateRequest`, `ProfileDeleteRequest`, `PasswordUpdateRequest`, `TwoFactorAuthenticationRequest` beserta concerns validasi. Read Request `AdminLemburIndexRequest` dan `AdminPegawaiIndexRequest` disesuaikan untuk menerima normalisasi web; dashboard memakai Request biasa seperti web.

## Verifikasi

- Audit kedua: semua route web/settings, seluruh Inertia::render/Route::inertia/Fortify view callbacks, middleware shared, service query/presenter/export/lock, models/relasi, Form Request, resource dan gate diperiksa. Tidak ada policy tambahan atau slug binding; binding yang dipakai adalah UUID, dengan ID numerik hanya untuk filter/bulk-lock seperti existing.
- `php artisan route:list --json`: 87 route terdaftar, 30 route API; tidak ditemukan pasangan method+URI duplikat pada inventaris. `route:list --except-vendor` juga berhasil. `routes/web.php` dan `routes/settings.php` tidak berubah.
- Pengujian terfokus: **80 test / 527 assertion lulus** untuk seluruh `tests/Feature/Api`, `tests/Feature/Settings`, LemburResourceTest, feature/unit LemburServiceTest. Termasuk perbandingan props web dan respons API dari dataset yang sama, pagination/filter/default/fallback, foto/lockedBy, semua field pegawai, ownership draft/upah, hapus locked, auth, token/session logout, CSRF, CORS, password/profile validation, passkey isolation/order, flags fitur, serta login 2FA recovery code.
- Test ekspor existing diperbaiki untuk memeriksa teks UTF-16BE yang benar dalam PDF; tidak mengubah exporter atau menghilangkan assertion hasil filter.
- `vendor/bin/pint --dirty --format agent` dijalankan; `git diff --check` bersih.
- Full suite pernah dijalankan: **107/117 lulus, 10 gagal/error** pada test web existing. Delapan merujuk nama route `dashboard` yang tidak ada (nama actual `admin.dashboard`), satu memanggil factory state `locked()` yang belum ada, satu mengharapkan `/` 200 meski route redirect 302. Test/route web tersebut tidak diubah untuk menyembunyikan kegagalan.
- PHPStan global tidak hijau: masalah pada kode legacy seperti missing return/generic types, trait JsonResponseTrait, RegisterService tidak ada, serta konfigurasi/seeder. Hasil global bukan bukti semua backend sudah bebas masalah. Detail kode baru dan controller API admin diperiksa; keterbatasan ini terpisah dari 80 test behavior yang lulus.
- Pengujian menggunakan SQLite in-memory. SQL service yang sama digunakan kedua jalur; belum ada pengujian database production, browser Next.js aktual, pengiriman email nyata, atau authenticator WebAuthn fisik.
