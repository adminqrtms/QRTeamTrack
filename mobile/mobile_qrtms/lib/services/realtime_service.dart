import 'dart:async';
import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'api_service.dart';

/// Live connection to the Laravel Reverb WebSocket server.
///
/// Delivers new chat messages instantly. When the connection is down, the
/// chat screens fall back to fetching, so messaging keeps working either way.
class RealtimeService {
  RealtimeService._();
  static final RealtimeService instance = RealtimeService._();

  // Must match REVERB_APP_KEY and REVERB_PORT in the Laravel .env file.
  static const String reverbKey = 'qrt-reverb-key';
  static const int reverbPort = 8080;

  PusherChannelsClient? _client;
  PrivateChannel? _userChannel;
  StreamSubscription? _connectionSubscription;
  StreamSubscription? _lifecycleSubscription;
  StreamSubscription? _userMessageSubscription;
  bool _isConnected = false;
  bool _notificationsReady = false;
  final FlutterLocalNotificationsPlugin _notifications =
      FlutterLocalNotificationsPlugin();

  final StreamController<Map<String, dynamic>> _incomingController =
      StreamController<Map<String, dynamic>>.broadcast();

  /// The conversation currently open on screen (no notification for it).
  int? activeConversationId;

  bool get isConnected => _isConnected;

  /// Fires for every message sent to the logged-in user, in any conversation.
  /// Each event is `{'message': {...}, 'sender_name': '...'}`.
  Stream<Map<String, dynamic>> get incomingMessages =>
      _incomingController.stream;

  Map<String, String> get _authHeaders => {
        'Accept': 'application/json',
        'Authorization': 'Bearer ${ApiService.token}',
      };

  Uri get _authEndpoint => Uri.parse('${ApiService.baseUrl}/broadcasting/auth');

  PusherChannelsOptions get _options {
    final api = Uri.parse(ApiService.baseUrl);
    return PusherChannelsOptions.fromHost(
      scheme: api.scheme == 'https' ? 'wss' : 'ws',
      host: api.host,
      key: reverbKey,
      port: reverbPort,
      shouldSupplyMetadataQueries: true,
      metadata: PusherChannelsOptionsMetadata.byDefault(),
    );
  }

  /// Connects for the logged-in user. Safe to call more than once.
  Future<void> connect() async {
    if (_client != null || ApiService.token == null) return;
    final userId = ApiService.userId;
    if (userId == null) return;

    await _initNotifications();

    final client = PusherChannelsClient.websocket(
      options: _options,
      connectionErrorHandler: (exception, trace, refresh) {
        debugPrint('REALTIME CONNECTION ERROR: $exception');
        refresh();
      },
      minimumReconnectDelayDuration: const Duration(seconds: 3),
    );
    _client = client;

    _lifecycleSubscription = client.lifecycleStream.listen((state) {
      _isConnected =
          state == PusherChannelsClientLifeCycleState.establishedConnection;
    });

    _userChannel = client.privateChannel(
      'private-user.$userId',
      authorizationDelegate:
          EndpointAuthorizableChannelTokenAuthorizationDelegate
              .forPrivateChannel(
        authorizationEndpoint: _authEndpoint,
        headers: _authHeaders,
      ),
    );

    _userMessageSubscription =
        _userChannel!.bind('message.sent').listen(_handleIncomingMessage);

    _connectionSubscription = client.onConnectionEstablished.listen((_) {
      _userChannel?.subscribeIfNotUnsubscribed();
    });

    unawaited(client.connect());
  }

  /// Disconnects (e.g. on logout).
  Future<void> disconnect() async {
    await _userMessageSubscription?.cancel();
    await _connectionSubscription?.cancel();
    await _lifecycleSubscription?.cancel();
    _client?.dispose();
    _client = null;
    _userChannel = null;
    _isConnected = false;
    activeConversationId = null;
  }

  /// Listens to one conversation's events while its chat screen is open.
  ConversationSubscription subscribeConversation(
    int conversationId, {
    required void Function(Map<String, dynamic> message) onMessage,
    required void Function(Map<String, dynamic> data) onRead,
  }) {
    final client = _client;
    if (client == null) return ConversationSubscription._([], null, null);

    final channel = client.privateChannel(
      'private-conversation.$conversationId',
      authorizationDelegate:
          EndpointAuthorizableChannelTokenAuthorizationDelegate
              .forPrivateChannel(
        authorizationEndpoint: _authEndpoint,
        headers: _authHeaders,
      ),
    );

    final subscriptions = <StreamSubscription>[
      channel.bind('message.sent').listen((event) {
        final message = event.tryGetDataAsMap()?['message'];
        if (message is Map) onMessage(Map<String, dynamic>.from(message));
      }),
      channel.bind('messages.read').listen((event) {
        final data = event.tryGetDataAsMap();
        if (data != null) onRead(data);
      }),
    ];

    final connectionSubscription = client.onConnectionEstablished.listen((_) {
      channel.subscribeIfNotUnsubscribed();
    });
    channel.subscribe();

    return ConversationSubscription._(
      subscriptions,
      connectionSubscription,
      channel,
    );
  }

  void _handleIncomingMessage(ChannelReadEvent event) {
    final data = event.tryGetDataAsMap();
    if (data == null || data['message'] is! Map) return;

    _incomingController.add(data);

    final message = data['message'] as Map;
    if (message['conversation_id'] != activeConversationId) {
      _showNotification(
        data['sender_name']?.toString() ?? 'New message',
        message['body']?.toString() ??
            (message['image'] != null ? '📷 Photo' : ''),
        message['conversation_id'] is int ? message['conversation_id'] : 0,
      );
    }
  }

  Future<void> _initNotifications() async {
    if (_notificationsReady) return;
    const androidSettings = AndroidInitializationSettings(
      '@mipmap/ic_launcher',
    );
    const iosSettings = DarwinInitializationSettings();
    try {
      await _notifications.initialize(
        const InitializationSettings(
          android: androidSettings,
          iOS: iosSettings,
        ),
      );
      _notificationsReady = true;
    } catch (e) {
      // Chat still works without system notifications.
      debugPrint('CHAT NOTIFICATIONS UNAVAILABLE: $e');
    }
  }

  Future<void> _showNotification(
    String title,
    String body,
    int conversationId,
  ) async {
    if (!_notificationsReady) return;
    const androidDetails = AndroidNotificationDetails(
      'chat_channel',
      'Messages',
      channelDescription: 'Chat messages from residents and personnel',
      importance: Importance.high,
      priority: Priority.high,
    );
    try {
      await _notifications.show(
        // Offset so chat notifications never replace SOS ones (ids 0 and 1).
        1000 + conversationId,
        title,
        body,
        const NotificationDetails(
          android: androidDetails,
          iOS: DarwinNotificationDetails(),
        ),
      );
    } catch (e) {
      debugPrint('CHAT NOTIFICATION ERROR: $e');
    }
  }
}

/// Handle returned by [RealtimeService.subscribeConversation].
class ConversationSubscription {
  final List<StreamSubscription> _subscriptions;
  final StreamSubscription? _connectionSubscription;
  final PrivateChannel? _channel;

  ConversationSubscription._(
    this._subscriptions,
    this._connectionSubscription,
    this._channel,
  );

  void cancel() {
    for (final subscription in _subscriptions) {
      subscription.cancel();
    }
    _connectionSubscription?.cancel();
    _channel?.unsubscribe();
  }
}
