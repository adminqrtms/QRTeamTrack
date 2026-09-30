import 'dart:async';
import 'dart:convert';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../pages/alarm_details_page.dart';
import '../pages/chat_page.dart';
import 'api_service.dart';
import 'call_service.dart';
import 'chat_service.dart';
import 'realtime_service.dart';

/// Runs when a push arrives while the app is in the background or closed.
/// SOS alarms come as data messages, so the app shows them itself as a
/// full-screen alert (like an incoming call), even over the lock screen.
@pragma('vm:entry-point')
Future<void> firebaseBackgroundMessageHandler(RemoteMessage message) async {
  final data = message.data;
  if (data['type'] == 'call') {
    await PushService.showIncomingCallAlert(data);
  } else if (data['type'] == 'call_ended') {
    await PushService.cancelCallAlert(int.tryParse('${data['call_id']}') ?? 0);
  } else if (data['full_screen'] == '1') {
    await PushService.showFullScreenAlert(data);
  }
}

/// Push notifications through Firebase Cloud Messaging, plus the app's
/// notification channels and what happens when a notification is tapped.
///
/// Firebase needs android/app/google-services.json. Without it (or on the
/// web build) everything here quietly does nothing and the app still works.
class PushService {
  PushService._();
  static final PushService instance = PushService._();

  /// Lets notification taps open screens from anywhere.
  static final GlobalKey<NavigatorState> navigatorKey =
      GlobalKey<NavigatorState>();

  final FlutterLocalNotificationsPlugin localNotifications =
      FlutterLocalNotificationsPlugin();

  static const String _emergencyChannelId = 'emergency_channel';

  /// Buttons on the SOS notification.
  static const String actionRespond = 'respond';
  static const String actionView = 'view';

  /// Buttons on the incoming call notification.
  static const String actionAccept = 'accept_call';
  static const String actionDecline = 'decline_call';

  static const String _callChannelId = 'call_channel';

  /// Incoming call notifications use ids from here up (plus the call id).
  static const int _callNotificationBase = 3000;

  /// Android's FLAG_INSISTENT: the alarm sound repeats until the
  /// notification is opened or dismissed.
  static const int _flagInsistent = 4;

  /// Notification id shared with the home screen's SOS notification, so a
  /// full-screen alert and the in-app one replace each other.
  static const int _sosNotificationId = 0;

  final StreamController<int> _alarmAlertController =
      StreamController<int>.broadcast();

  /// Alarm ids to show as the in-app SOS pop-up (the personnel home screen
  /// listens and shows its EMERGENCY ALARM dialog).
  Stream<int> get alarmAlerts => _alarmAlertController.stream;

  bool _firebaseReady = false;
  bool _localNotificationsReady = false;
  bool _tokenRefreshListening = false;
  String? _registeredToken;

  /// Whether Firebase push notifications are available on this install.
  bool get isAvailable => _firebaseReady;

  /// Call once from main(), before runApp.
  Future<void> init() async {
    await ensureLocalNotifications();
    if (kIsWeb) return;

    try {
      await Firebase.initializeApp();
      _firebaseReady = true;
    } catch (e) {
      debugPrint('PUSH NOTIFICATIONS DISABLED (no Firebase config): $e');
      return;
    }

    FirebaseMessaging.onBackgroundMessage(firebaseBackgroundMessageHandler);

    // App was in the background and the user tapped a notification
    FirebaseMessaging.onMessageOpenedApp.listen((message) {
      openFromData(message.data);
    });

    // App is open: Android doesn't show the notification by itself
    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);
  }

  /// Sets up local notifications and the Android channels. Safe to call
  /// more than once; every part of the app should use this instead of
  /// initializing the plugin itself (the last initialize wins on Android).
  Future<void> ensureLocalNotifications() async {
    if (_localNotificationsReady || kIsWeb) return;
    try {
      await localNotifications.initialize(
        const InitializationSettings(
          android: AndroidInitializationSettings('@mipmap/ic_launcher'),
          iOS: DarwinInitializationSettings(),
        ),
        onDidReceiveNotificationResponse: (response) {
          final payload = response.payload;
          if (payload != null && payload.isNotEmpty) {
            openFromData(
              Map<String, dynamic>.from(jsonDecode(payload)),
              actionId: response.actionId,
            );
          }
        },
      );

      final android = localNotifications.resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin>();
      await android?.createNotificationChannel(
        const AndroidNotificationChannel(
          'chat_channel',
          'Messages',
          description: 'Chat messages from residents and personnel',
          importance: Importance.high,
        ),
      );
      await _createEmergencyChannel(localNotifications);
      _localNotificationsReady = true;
    } catch (e) {
      debugPrint('LOCAL NOTIFICATIONS UNAVAILABLE: $e');
    }
  }

  /// Registers this phone for the logged-in user. Call after login
  /// (the home pages do this). Asks for notification permission if needed.
  Future<void> registerDevice() async {
    if (!_firebaseReady || ApiService.token == null) return;
    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();

      final token = await messaging.getToken();
      if (token != null) await _sendToken(token);

      if (!_tokenRefreshListening) {
        _tokenRefreshListening = true;
        messaging.onTokenRefresh.listen(_sendToken);
      }

      if (ApiService.role == 'personnel') {
        await _askForFullScreenAlertsOnce();
      }
    } catch (e) {
      debugPrint('PUSH REGISTER ERROR: $e');
    }
  }

  /// Android 14+ asks the user to allow full-screen alerts for apps that
  /// aren't phone or alarm-clock apps. Explain why, then open that setting
  /// (only once; on older Android versions nothing is shown).
  Future<void> _askForFullScreenAlertsOnce() async {
    final prefs = await SharedPreferences.getInstance();
    if (prefs.getBool('asked_full_screen_alerts') == true) return;
    await prefs.setBool('asked_full_screen_alerts', true);

    final android = localNotifications.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    if (android == null) return;

    final context = navigatorKey.currentContext;
    if (context != null && context.mounted) {
      await showDialog(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Full-screen SOS alerts'),
          content: const Text(
            'To see SOS emergencies instantly, even when your phone is locked, '
            'please allow full-screen notifications for this app on the next '
            'screen (if your phone asks).',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('OK'),
            ),
          ],
        ),
      );
    }
    await android.requestFullScreenIntentPermission();
  }

  /// Shows an SOS as a full-screen alert. Works from the background
  /// message handler, where the rest of the app isn't running.
  static Future<void> showFullScreenAlert(Map<String, dynamic> data) async {
    final plugin = FlutterLocalNotificationsPlugin();
    await plugin.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      ),
    );
    await _createEmergencyChannel(plugin);

    final title = data['title'] ?? 'EMERGENCY SOS!';
    final body = data['body'] ?? 'A resident needs help.';

    await plugin.show(
      _sosNotificationId,
      title,
      body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          _emergencyChannelId,
          'Emergency Alarms',
          importance: Importance.max,
          priority: Priority.max,
          category: AndroidNotificationCategory.alarm,
          fullScreenIntent: true,
          visibility: NotificationVisibility.public,
          ticker: 'EMERGENCY SOS',
          // Bigger, more noticeable banner
          styleInformation: BigTextStyleInformation(
            '$body\nTap to open, or respond right away.',
            contentTitle: '🚨 $title',
            summaryText: 'Emergency',
          ),
          largeIcon: const DrawableResourceAndroidBitmap('@mipmap/ic_launcher'),
          color: const Color(0xFFD32F2F),
          additionalFlags: Int32List.fromList(<int>[_flagInsistent]),
          actions: const <AndroidNotificationAction>[
            AndroidNotificationAction(
              actionRespond,
              "I'M COMING",
              showsUserInterface: true,
              titleColor: Color(0xFF2E7D32),
            ),
            AndroidNotificationAction(
              actionView,
              'VIEW DETAILS',
              showsUserInterface: true,
              titleColor: Color(0xFFD32F2F),
            ),
          ],
        ),
      ),
      payload: jsonEncode({
        'type': data['type'],
        'alarm_id': data['alarm_id'],
      }),
    );
  }

  /// Shows an incoming call like a phone call: full screen when locked,
  /// ringtone repeating until answered, Accept / Decline buttons.
  /// Also works from the background message handler.
  static Future<void> showIncomingCallAlert(Map<String, dynamic> data) async {
    final callId = int.tryParse('${data['call_id']}');
    if (callId == null) return;

    final plugin = FlutterLocalNotificationsPlugin();
    await plugin.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      ),
    );
    await plugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(
          const AndroidNotificationChannel(
            _callChannelId,
            'Calls',
            description: 'Incoming voice and video calls',
            importance: Importance.max,
            sound: UriAndroidNotificationSound(
              'content://settings/system/ringtone',
            ),
            audioAttributesUsage: AudioAttributesUsage.notificationRingtone,
          ),
        );

    final isVideo = data['call_type'] == 'video';
    await plugin.show(
      _callNotificationBase + callId,
      data['caller_name'] ?? 'Incoming call',
      isVideo ? 'Incoming video call' : 'Incoming voice call',
      NotificationDetails(
        android: AndroidNotificationDetails(
          _callChannelId,
          'Calls',
          importance: Importance.max,
          priority: Priority.max,
          category: AndroidNotificationCategory.call,
          fullScreenIntent: true,
          visibility: NotificationVisibility.public,
          ongoing: true,
          autoCancel: false,
          // Stop ringing after the caller's ring time, even if nothing else does
          timeoutAfter: CallService.ringTimeout.inMilliseconds + 5000,
          largeIcon: const DrawableResourceAndroidBitmap('@mipmap/ic_launcher'),
          additionalFlags: Int32List.fromList(<int>[_flagInsistent]),
          actions: const <AndroidNotificationAction>[
            AndroidNotificationAction(
              actionDecline,
              'DECLINE',
              showsUserInterface: true,
              titleColor: Color(0xFFD32F2F),
            ),
            AndroidNotificationAction(
              actionAccept,
              'ACCEPT',
              showsUserInterface: true,
              titleColor: Color(0xFF2E7D32),
            ),
          ],
        ),
      ),
      payload: jsonEncode({'type': 'call', 'call_id': '$callId'}),
    );
  }

  static Future<void> cancelCallAlert(int callId) async {
    if (kIsWeb) return;
    try {
      await FlutterLocalNotificationsPlugin()
          .cancel(_callNotificationBase + callId);
    } catch (e) {
      debugPrint('CANCEL CALL ALERT ERROR: $e');
    }
  }

  static Future<void> _createEmergencyChannel(
    FlutterLocalNotificationsPlugin plugin,
  ) async {
    await plugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(
          const AndroidNotificationChannel(
            _emergencyChannelId,
            'Emergency Alarms',
            importance: Importance.max,
          ),
        );
  }

  /// Stops notifications to this phone. Call before clearing the login.
  Future<void> unregisterDevice() async {
    if (!_firebaseReady) return;
    try {
      final token =
          _registeredToken ?? await FirebaseMessaging.instance.getToken();
      if (token != null && ApiService.token != null) {
        await http.delete(
          Uri.parse('${ApiService.baseUrl}/device-tokens'),
          headers: _headers,
          body: {'token': token},
        );
      }
      // A fresh token next time, so the old account can't receive anything.
      await FirebaseMessaging.instance.deleteToken();
      _registeredToken = null;
    } catch (e) {
      debugPrint('PUSH UNREGISTER ERROR: $e');
    }
  }

  /// If the app was started by tapping a notification, open that screen.
  /// Call once the home page is showing.
  Future<void> handleLaunchNotification() async {
    try {
      if (_firebaseReady) {
        final message = await FirebaseMessaging.instance.getInitialMessage();
        if (message != null) {
          await openFromData(message.data);
          return;
        }
      }
      final details =
          await localNotifications.getNotificationAppLaunchDetails();
      final payload = details?.notificationResponse?.payload;
      if (details?.didNotificationLaunchApp == true &&
          payload != null &&
          payload.isNotEmpty) {
        await openFromData(
          Map<String, dynamic>.from(jsonDecode(payload)),
          actionId: details?.notificationResponse?.actionId,
        );
      }
    } catch (e) {
      debugPrint('LAUNCH NOTIFICATION ERROR: $e');
    }
  }

  /// Opens the chat or alarm a notification is about.
  Future<void> openFromData(
    Map<String, dynamic> data, {
    String? actionId,
  }) async {
    final navigator = navigatorKey.currentState;
    if (navigator == null || ApiService.token == null) return;

    if (data['type'] == 'call') {
      final id = int.tryParse('${data['call_id']}');
      if (id != null) {
        await CallService.instance.openFromNotification(id, actionId);
      }
      return;
    }

    if (data['type'] == 'chat') {
      final id = int.tryParse('${data['conversation_id']}');
      if (id == null || RealtimeService.instance.activeConversationId == id) {
        return;
      }
      final conversation = await ChatService.getConversation(id);
      if (conversation != null) {
        navigator.push(
          MaterialPageRoute(
            builder: (_) => ChatPage(conversation: conversation),
          ),
        );
      }
    } else if (data['type'] == 'alarm' && ApiService.role == 'personnel') {
      final id = int.tryParse('${data['alarm_id']}');
      if (id == null) return;

      // Stop the repeating alarm sound
      await localNotifications.cancel(_sosNotificationId);

      if (actionId == actionRespond) {
        // "I'M COMING" pressed on the notification
        await ApiService.updateAlarmStatus(id, 'responding');
        final alarm = await getAlarm(id);
        if (alarm != null) {
          navigator.push(
            MaterialPageRoute(builder: (_) => AlarmDetailsPage(data: alarm)),
          );
        }
        return;
      }

      if (actionId == actionView) {
        final alarm = await getAlarm(id);
        if (alarm != null) {
          navigator.push(
            MaterialPageRoute(builder: (_) => AlarmDetailsPage(data: alarm)),
          );
        }
        return;
      }

      if (_alarmAlertController.hasListener) {
        // The home screen shows its EMERGENCY ALARM pop-up.
        _alarmAlertController.add(id);
      } else {
        final alarm = await getAlarm(id);
        if (alarm != null) {
          navigator.push(
            MaterialPageRoute(builder: (_) => AlarmDetailsPage(data: alarm)),
          );
        }
      }
    }
    // Residents: the home screen already shows the SOS status.
  }

  /// Shows a local notification (used for messages that arrive while the app is open).
  Future<void> showLocal({
    required int id,
    required String title,
    required String body,
    required String channelId,
    required Map<String, dynamic> data,
  }) async {
    if (!_localNotificationsReady) return;
    try {
      await localNotifications.show(
        id,
        title,
        body,
        NotificationDetails(
          android: AndroidNotificationDetails(
            channelId,
            channelId == 'emergency_channel' ? 'Emergency Alarms' : 'Messages',
            importance: Importance.high,
            priority: Priority.high,
          ),
          iOS: const DarwinNotificationDetails(),
        ),
        payload: jsonEncode(data),
      );
    } catch (e) {
      debugPrint('LOCAL NOTIFICATION ERROR: $e');
    }
  }

  void _handleForegroundMessage(RemoteMessage message) {
    final data = message.data;

    // Calls while the app is open (the live connection usually gets there first)
    if (data['type'] == 'call') {
      final id = int.tryParse('${data['call_id']}');
      if (id != null) CallService.instance.showIncomingById(id);
      return;
    }
    if (data['type'] == 'call_ended') {
      final id = int.tryParse('${data['call_id']}');
      if (id != null) CallService.instance.markEnded(id);
      return;
    }

    // SOS while the app is open: show the in-app pop-up right away.
    if (data['type'] == 'alarm') {
      final id = int.tryParse('${data['alarm_id']}');
      if (id != null && ApiService.role == 'personnel') {
        _alarmAlertController.add(id);
      }
      return;
    }
    if (data['type'] != 'chat') return;

    // The live connection already shows chat notifications while it's up.
    if (RealtimeService.instance.isConnected) return;

    final conversationId = int.tryParse('${data['conversation_id']}') ?? 0;
    if (conversationId == RealtimeService.instance.activeConversationId) return;

    showLocal(
      id: 1000 + conversationId,
      title: message.notification?.title ?? 'New message',
      body: message.notification?.body ?? '',
      channelId: 'chat_channel',
      data: data,
    );
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Authorization': 'Bearer ${ApiService.token}',
      };

  Future<void> _sendToken(String token) async {
    if (ApiService.token == null) return;
    try {
      final response = await http.post(
        Uri.parse('${ApiService.baseUrl}/device-tokens'),
        headers: _headers,
        body: {'token': token, 'platform': defaultTargetPlatform.name},
      );
      if (response.statusCode == 200) _registeredToken = token;
    } catch (e) {
      debugPrint('PUSH TOKEN UPLOAD ERROR: $e');
    }
  }

  /// Loads an alarm with its resident details, or null if it can't be loaded.
  Future<Map<String, dynamic>?> getAlarm(int id) async {
    try {
      final response = await http.get(
        Uri.parse('${ApiService.baseUrl}/alarms/$id'),
        headers: _headers,
      );
      final data = jsonDecode(response.body)['data'];
      return data is Map<String, dynamic> ? data : null;
    } catch (e) {
      return null;
    }
  }
}
