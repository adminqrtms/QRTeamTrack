import 'dart:convert';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'api_service.dart';

/// REST calls for messaging between residents and personnel.
class ChatService {
  static Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Authorization': 'Bearer ${ApiService.token}',
      };

  static Map<String, dynamic> _decode(http.Response response) {
    final result = jsonDecode(response.body);
    return result is Map<String, dynamic> ? result : {};
  }

  /// Full URL of an image stored on the server (e.g. chat photos, avatars).
  static String storageUrl(String path) {
    final cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return "${ApiService.baseUrl.replaceAll('/api', '/storage/')}$cleanPath";
  }

  /// Chat photos are loaded through the API with the login token, so they
  /// work without `storage:link` and in the Chrome build. Cached per session.
  static final Map<String, Future<Uint8List?>> _imageCache = {};

  static Future<Uint8List?> loadMessageImage(
      int conversationId, int messageId) {
    final key = '$conversationId/$messageId';
    return _imageCache.putIfAbsent(key, () async {
      try {
        final response = await http
            .get(
              Uri.parse(
                  '${ApiService.baseUrl}/conversations/$conversationId/messages/$messageId/image'),
              headers: {'Authorization': 'Bearer ${ApiService.token}'},
            )
            .timeout(const Duration(seconds: 30));
        if (response.statusCode == 200) return response.bodyBytes;
      } catch (_) {}
      _imageCache.remove(key); // let a later rebuild try again
      return null;
    });
  }

  static Future<List<dynamic>> getConversations() async {
    try {
      final response = await http.get(
        Uri.parse('${ApiService.baseUrl}/conversations'),
        headers: _headers,
      );
      return _decode(response)['data'] ?? [];
    } catch (e) {
      return [];
    }
  }

  /// A single conversation, or null if it can't be loaded.
  static Future<Map<String, dynamic>?> getConversation(int id) async {
    try {
      final response = await http.get(
        Uri.parse('${ApiService.baseUrl}/conversations/$id'),
        headers: _headers,
      );
      final data = _decode(response)['data'];
      return data is Map<String, dynamic> ? data : null;
    } catch (e) {
      return null;
    }
  }

  static Future<int> getUnreadCount() async {
    try {
      final response = await http.get(
        Uri.parse('${ApiService.baseUrl}/conversations/unread-count'),
        headers: _headers,
      );
      return _decode(response)['unread_count'] ?? 0;
    } catch (e) {
      return 0;
    }
  }

  /// Opens (finds or creates) a conversation. Pass exactly one of the ids.
  /// On success the result contains 'data'; on failure it contains 'message'.
  static Future<Map<String, dynamic>> openConversation({
    int? reportId,
    int? alarmId,
    int? personnelId,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('${ApiService.baseUrl}/conversations'),
        headers: _headers,
        body: {
          if (reportId != null) 'report_id': reportId.toString(),
          if (alarmId != null) 'alarm_id': alarmId.toString(),
          if (personnelId != null) 'personnel_id': personnelId.toString(),
        },
      );
      return _decode(response);
    } catch (e) {
      return {'message': 'Connection failed'};
    }
  }

  /// Latest messages, or only the ones after [afterId] / before [beforeId].
  /// Returns null when the request fails.
  static Future<List<dynamic>?> getMessages(
    int conversationId, {
    int? afterId,
    int? beforeId,
  }) async {
    try {
      final query = <String, String>{
        if (afterId != null) 'after_id': afterId.toString(),
        if (beforeId != null) 'before_id': beforeId.toString(),
      };
      final response = await http.get(
        Uri.parse(
                '${ApiService.baseUrl}/conversations/$conversationId/messages')
            .replace(queryParameters: query.isEmpty ? null : query),
        headers: _headers,
      );
      if (response.statusCode != 200) return null;
      return _decode(response)['data'] ?? [];
    } catch (e) {
      return null;
    }
  }

  static Future<Map<String, dynamic>> sendMessage(
    int conversationId, {
    String? body,
    XFile? image,
  }) async {
    try {
      final request = http.MultipartRequest(
        'POST',
        Uri.parse(
            '${ApiService.baseUrl}/conversations/$conversationId/messages'),
      );
      request.headers.addAll(_headers);
      if (body != null && body.isNotEmpty) request.fields['body'] = body;
      if (image != null) {
        // Bytes (not a file path) so this also works in the Chrome build
        request.files.add(http.MultipartFile.fromBytes(
          'image',
          await image.readAsBytes(),
          filename: image.name.isNotEmpty ? image.name : 'photo.jpg',
        ));
      }

      final streamed = await request.send();
      final response = await http.Response.fromStream(streamed);
      return _decode(response);
    } catch (e) {
      return {'message': 'Failed to send message'};
    }
  }

  static Future<void> markRead(int conversationId) async {
    try {
      await http.post(
        Uri.parse('${ApiService.baseUrl}/conversations/$conversationId/read'),
        headers: _headers,
      );
    } catch (e) {
      // Not critical: read receipts will sync on the next open.
    }
  }
}
