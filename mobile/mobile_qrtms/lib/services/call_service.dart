import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../pages/call_page.dart';
import '../pages/incoming_call_page.dart';
import 'api_service.dart';
import 'push_service.dart';
import 'realtime_service.dart';

/// Audio/video calls: talks to the call API and decides which call screen
/// to show (incoming ring, or the call itself).
class CallService {
  CallService._();
  static final CallService instance = CallService._();

  /// How long an outgoing call rings before it counts as missed.
  static const Duration ringTimeout = Duration(seconds: 45);

  final StreamController<CallSignalEvent> _signalController =
      StreamController<CallSignalEvent>.broadcast();
  StreamSubscription? _realtimeSubscription;

  /// The call currently on screen (ringing or connected), if any.
  int? activeCallId;

  /// call.accepted / call.ended events, from the live connection or from
  /// push notifications, for the call screens to react to.
  Stream<CallSignalEvent> get signals => _signalController.stream;

  /// Call once after login (the home pages do this).
  void start() {
    _realtimeSubscription ??=
        RealtimeService.instance.callSignals.listen(_handleSignal);
  }

  void _handleSignal(CallSignalEvent event) {
    if (event.signal == 'call.incoming') {
      showIncoming(event.call);
      return;
    }
    if (event.signal == 'call.ended' && event.callId != null) {
      PushService.cancelCallAlert(event.callId!);
    }
    _signalController.add(event);
  }

  /// The other side ended the call (learned from a push notification).
  void markEnded(int callId) {
    PushService.cancelCallAlert(callId);
    _signalController.add(
      CallSignalEvent('call.ended', {'id': callId, 'status': 'ended'}),
    );
  }

  // ---------------------------------------------------------------- API

  static Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Authorization': 'Bearer ${ApiService.token}',
      };

  static Future<Map<String, dynamic>> _post(String path,
      [Map<String, String>? body]) async {
    try {
      final response = await http
          .post(
            Uri.parse('${ApiService.baseUrl}$path'),
            headers: _headers,
            body: body,
          )
          .timeout(const Duration(seconds: 15));
      final result = jsonDecode(response.body);
      return result is Map<String, dynamic> ? result : {};
    } catch (e) {
      return {'message': "Can't reach the server."};
    }
  }

  static Future<Map<String, dynamic>?> getCall(int id) async {
    try {
      final response = await http
          .get(Uri.parse('${ApiService.baseUrl}/calls/$id'), headers: _headers)
          .timeout(const Duration(seconds: 15));
      final data = jsonDecode(response.body)['data'];
      return data is Map<String, dynamic> ? data : null;
    } catch (e) {
      return null;
    }
  }

  static Future<void> end(int callId, {bool timedOut = false}) async {
    await _post('/calls/$callId/end', timedOut ? {'reason': 'timeout'} : null);
  }

  static Future<void> decline(int callId) async {
    await _post('/calls/$callId/decline');
  }

  // ---------------------------------------------------------------- Flows

  /// Calls the other person in a conversation. [type] is 'audio' or 'video'.
  Future<void> startCall(
    BuildContext context,
    Map<String, dynamic> conversation,
    String type,
  ) async {
    if (activeCallId != null) return;

    final res = await _post(
      '/conversations/${conversation['id']}/calls',
      {'type': type},
    );
    if (!context.mounted) return;

    if (res['data'] is! Map || res['livekit'] is! Map) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message'] ?? 'Unable to start the call')),
      );
      return;
    }

    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => CallPage(
          call: Map<String, dynamic>.from(res['data']),
          livekit: Map<String, dynamic>.from(res['livekit']),
          isCaller: true,
          otherPerson: Map<String, dynamic>.from(
            conversation['other_user'] ?? {},
          ),
        ),
      ),
    );
  }

  /// Shows the ringing screen for an incoming call.
  Future<void> showIncoming(Map<String, dynamic> call) async {
    final id =
        call['id'] is int ? call['id'] as int : int.tryParse('${call['id']}');
    if (id == null || call['status'] != 'ringing') return;
    if (activeCallId == id) return; // already on screen
    if (activeCallId != null) return; // busy in another call

    final navigator = PushService.navigatorKey.currentState;
    if (navigator == null) return;

    // Ringtone (and the full-screen alert when the phone is locked)
    if (!kIsWeb) {
      PushService.showIncomingCallAlert({
        'type': 'call',
        'call_id': '$id',
        'call_type': call['type'],
        'caller_name': call['caller']?['name'] ?? 'Someone',
        'title': 'Incoming ${call['type'] == 'video' ? 'video' : 'voice'} call',
        'body': '${call['caller']?['name'] ?? 'Someone'} is calling you',
      });
    }

    activeCallId = id;
    // The ringing screen closes with 'accept', 'decline' or 'ended'.
    final result = await navigator.push<String>(
      MaterialPageRoute(builder: (_) => IncomingCallPage(call: call)),
    );
    PushService.cancelCallAlert(id);
    if (activeCallId == id) activeCallId = null;

    if (result == 'accept') {
      final error = await accept(call);
      final context = PushService.navigatorKey.currentContext;
      if (error != null && context != null && context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error)),
        );
      }
    }
  }

  /// Push notification about a call, while the app is open.
  Future<void> showIncomingById(int callId) async {
    if (activeCallId == callId) return;
    final call = await getCall(callId);
    if (call != null) await showIncoming(call);
  }

  /// The user tapped the call notification (or one of its buttons).
  Future<void> openFromNotification(int callId, String? actionId) async {
    final call = await getCall(callId);
    PushService.cancelCallAlert(callId);
    if (call == null || call['status'] != 'ringing') return;

    if (actionId == PushService.actionDecline) {
      await decline(callId);
      return;
    }
    if (actionId == PushService.actionAccept) {
      await accept(call);
      return;
    }
    await showIncoming(call);
  }

  /// Answers a ringing call and opens the call screen.
  /// Returns an error message, or null on success.
  Future<String?> accept(Map<String, dynamic> call) async {
    final id = call['id'];
    PushService.cancelCallAlert(id is int ? id : int.tryParse('$id') ?? 0);

    final res = await _post('/calls/$id/accept');
    if (res['data'] is! Map || res['livekit'] is! Map) {
      return res['message'] ?? 'This call has already ended.';
    }

    final navigator = PushService.navigatorKey.currentState;
    if (navigator == null) return null;

    final accepted = Map<String, dynamic>.from(res['data']);
    activeCallId = accepted['id'];
    await navigator.push(
      MaterialPageRoute(
        builder: (_) => CallPage(
          call: accepted,
          livekit: Map<String, dynamic>.from(res['livekit']),
          isCaller: false,
          otherPerson: Map<String, dynamic>.from(accepted['caller'] ?? {}),
        ),
      ),
    );
    if (activeCallId == accepted['id']) activeCallId = null;
    return null;
  }
}
