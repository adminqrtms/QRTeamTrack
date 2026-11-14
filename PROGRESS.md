# QRTeamTrack – Progress Notes

**Branches:** everything below is on `main` (and `claude/vigilant-noether-tn3lmd`, kept in sync).

## Done

| Feature | Where |
|---|---|
| Audit logs for personnel time in / time out | Web admin → **Audit Logs** |
| Live refresh (no reload) for audit logs, admin pages and mobile screens | `public/js/live-refresh.js`, `lib/services/auto_refresh.dart` |
| Revised Terms and Conditions, required to register | Mobile → Register screen |
| Resident ↔ personnel chat: text, photos, read ticks, unread badges | Mobile → **Messages**; chat buttons on reports, alarms, SOS |
| Real-time delivery with Laravel Reverb (falls back to polling if offline) | `routes/channels.php`, `lib/services/realtime_service.dart` |
| Fresh-database fixes: report column names, SQLite-safe alarm sorting | migration `2026_09_29_090000_restore_report_column_names` |
| Firebase push notifications: new messages, SOS to on-duty personnel, "help is on the way" to the resident; tap opens the chat/alarm | `app/Services/PushNotificationService.php`, `lib/services/push_service.dart` |
| SOS alert for personnel even when the app is closed: full-screen alarm when locked, large banner with **I'M COMING / VIEW DETAILS** buttons, repeating sound | `showFullScreenAlert` in `lib/services/push_service.dart` |
| One-click launchers: `start-server.bat`, `run-app.bat` (detects the PC's IP) | project root, `scripts/` |
| Security: admin-only web panel, report/SOS permissions, deactivated accounts can't log in | `EnsureUserIsAdmin`, `tests/Feature/AccessControlTest.php` |
| Chat photos served through the API (work in Chrome and without `storage:link`) | `ConversationController::messageImage` |
| Personnel must TIME OUT before logging out; admin **Force Time Out** | `personnel_home.dart`, Web admin → Personnel |
| `reset-data.bat`: delete all personnel/resident data (keeps admins and stations) | `php artisan qrt:reset-data` |
| Registration validation (name, email, 09 mobile number, address, password + confirm) | `lib/utils/validators.dart`, `AuthController::register` |
| ngrok: fixed internet address so phones work on any Wi-Fi / mobile data | `setup-ngrok.bat`, `php artisan qrt:gateway` |
| **Team response + mutual resolution**: several personnel join an incident; "Resolved" waits for the resident's YES (NOT YET = back to ongoing; no reply in 24 h = auto-resolved) | `IncidentWorkflow`, `incident_widgets.dart`, `POST /api/{reports,alarms}/{id}/join` and `/resolution` |
| **Personnel log** (click a personnel): assigned shift vs. timed in/out, paid hours only inside the shift, salary, responses, solved cases; month or date range; printable for payroll | `DutyLog`, `PersonnelLog`, Web admin → Personnel |
| No time in after the day's shift ended; "Audit Logs" renamed "Personnel Logs"; resident page shows who responded | `PersonnelAttendanceController`, Web admin |
| **In-app updates**: "Update available" with what's new, **Update now** downloads and installs; optional "required" updates | `build-apk.bat`, Web admin → **App Updates**, `lib/services/update_service.dart` |
| Audio & video calls (LiveKit): call buttons in the chat, ringing screen + ringtone notification with Accept/Decline (also when the app is closed), mute / speaker / camera / flip, call records in the chat ("📞 Voice call · 02:05", "Missed video call") | `CallController.php`, `LiveKitService.php`, `lib/services/call_service.dart`, `lib/pages/call_page.dart`, `lib/pages/incoming_call_page.dart` |

Back-end tests: `cd web/qrt && php artisan test` (87 passing). App tests: `flutter test test/validators_test.dart test/update_dialog_test.dart`.

## How to run locally

One-time setup:
1. `web/qrt/.env`: `BROADCAST_CONNECTION=reverb` plus the `REVERB_*` values from `.env.example`.
2. In `web/qrt`: `php artisan migrate` and `php artisan storage:link`.
3. Windows firewall: double-click **`setup-firewall.bat`** (opens 8000, 8080, 7880, 7881, UDP 7882 and the LiveKit program).

After pulling new code: double-click **`update.bat`** (pull, composer install, migrate, flutter pub get).

Every time (Windows, double-click in the project folder):
4. **`start-server.bat`** – opens the Laravel API and Reverb in two windows and shows the URLs.
5. **`run-app.bat`** – runs the app using this PC's current IP (no editing `api_service.dart`).
   Pick a device from the list, or pass one: `run-app.bat chrome`, `run-app.bat 22101316G`,
   or `run-app.bat emulator` (starts the Android emulator first if it isn't running).
   Resident on the phone (Xiaomi needs "Install via USB" on); personnel in Chrome
   (fake GPS via DevTools → Sensors) or an Android emulator.
6. Personnel can only TIME IN with a schedule for today and within 200 m of the station.
7. Push notifications (Firebase project `qrteamtrack-002`, Android app `com.example.mobile_qrtms`).
   Both files stay out of git:
   - `mobile/mobile_qrtms/android/app/google-services.json`
   - `web/qrt/storage/app/firebase-credentials.json` (service account private key)
   Without them, everything still works, just without push notifications.
   Delivery is logged in `web/qrt/storage/logs/laravel.log` ("SOS #…", "Push … to N phone(s)", "FCM accepted…").
8. Calls now use **LiveKit Cloud** (`LIVEKIT_URL`/`LIVEKIT_API_KEY`/`LIVEKIT_API_SECRET` in `web/qrt/.env`),
   which works on any network. Local alternative (same Wi-Fi only; some school/office networks block it):
   put `livekit-server.exe` (Windows zip from github.com/livekit/livekit/releases) in
   `tools/livekit/`; `start-server.bat` then also starts it (dev mode: key `devkey`, secret `secret`).
   Firewall: TCP 7880, 7881 and UDP 7882. For LiveKit Cloud / a hosted server set `LIVEKIT_URL`,
   `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET` in `web/qrt/.env`, then `php artisan config:clear`.
   Check the settings with `php artisan livekit:check` (in `web/qrt`).
9. Xiaomi / Redmi phones (personnel), so alerts arrive with the app closed:
   - App info: **Autostart** on, **Battery saver → No restrictions**, lock the app in recent apps.
   - **Other permissions**: Show on Lock screen, Display pop-up windows while running in the background, Display pop-up window.
   - **Notifications → Emergency Alarms**: Floating notifications, Lock screen notifications, Sound.
   - Android 14+: Special app access → **Full screen notifications** → allow.
   Full-screen alarms only appear when the phone is locked; when it's in use Android shows the banner.
10. Internet address (any Wi-Fi / mobile data): **`setup-ngrok.bat`** once (authtoken + free domain,
    `ngrok.exe` in `tools/ngrok/`); `start-server.bat` then also starts the gateway and ngrok.
11. Shareable app + in-app updates: **`build-apk.bat`** builds `QRTeamTrack.apk` (version 1.0.N).
    Answer `y` to publish it as an update and type what's new; phones show "Update available" when
    they open the app. Manage versions in Web admin → **App Updates**.
    Updates must be signed with the same key: always build on the same PC (Flutter uses
    `C:\Users\<you>\.android\debug.keystore`; back it up).

## Next (planned)

1. **Smaller items** – Terms and Conditions in the resident menu.

## Known notes

- iOS needs camera/photo-library permission texts in `Info.plist` before photos can be used on iPhone.
- For real deployment, the Laravel, Reverb and LiveKit servers must be hosted online.
- Calls keep working only while the app is on screen; leaving the app mid-call can cut the microphone
  (a foreground service would be needed for background calls).
