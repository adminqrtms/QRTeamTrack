# QRTeamTrack – Progress Notes

**Working branch:** `claude/vigilant-noether-tn3lmd` (contains everything below; not yet merged into `main`).
Start new work from this branch.

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

Back-end tests: `cd web/qrt && php artisan test` (24 passing).

## How to run locally

One-time setup:
1. `web/qrt/.env`: `BROADCAST_CONNECTION=reverb` plus the `REVERB_*` values from `.env.example`.
2. In `web/qrt`: `php artisan migrate` and `php artisan storage:link`.
3. Windows firewall: allow inbound TCP ports 8000 and 8080.

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
8. Xiaomi / Redmi phones (personnel), so alerts arrive with the app closed:
   - App info: **Autostart** on, **Battery saver → No restrictions**, lock the app in recent apps.
   - **Other permissions**: Show on Lock screen, Display pop-up windows while running in the background, Display pop-up window.
   - **Notifications → Emergency Alarms**: Floating notifications, Lock screen notifications, Sound.
   - Android 14+: Special app access → **Full screen notifications** → allow.
   Full-screen alarms only appear when the phone is locked; when it's in use Android shows the banner.

## Next (planned)

1. **Audio and video calls** – call buttons in the chat, full-screen ringing, using LiveKit
   (run locally on the PC first; LiveKit Cloud or a hosted server later).
2. **Smaller items** – Terms and Conditions in the resident menu; photo sending from the Chrome build.

## Known notes

- Photo uploads don't work in the Chrome (web) build.
- iOS needs camera/photo-library permission texts in `Info.plist` before photos can be used on iPhone.
- For real deployment, the Laravel, Reverb (and later LiveKit) servers must be hosted online.
