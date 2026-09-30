import 'dart:convert';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:http/http.dart' as http;
import '../pages/alarm_details_page.dart';
import '../pages/chat_page.dart';
import 'api_service.dart';
import 'chat_service.dart';
import 'realtime_service.dart';

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
            openFromData(Map<String, dynamic>.from(jsonDecode(payload)));
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
      await android?.createNotificationChannel(
        const AndroidNotificationChannel(
          'emergency_channel',
          'Emergency Alarms',
          importance: Importance.max,
        ),
      );
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
    } catch (e) {
      debugPrint('PUSH REGISTER ERROR: $e');
    }
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
        await openFromData(Map<String, dynamic>.from(jsonDecode(payload)));
      }
    } catch (e) {
      debugPrint('LAUNCH NOTIFICATION ERROR: $e');
    }
  }

  /// Opens the chat or alarm a notification is about.
  Future<void> openFromData(Map<String, dynamic> data) async {
    final navigator = navigatorKey.currentState;
    if (navigator == null || ApiService.token == null) return;

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
      final alarm = await _getAlarm(data['alarm_id']);
      if (alarm != null) {
        navigator.push(
          MaterialPageRoute(builder: (_) => AlarmDetailsPage(data: alarm)),
        );
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
    // SOS alarms are already shown by the home screens while the app is open.
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

  Future<Map<String, dynamic>?> _getAlarm(dynamic id) async {
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
