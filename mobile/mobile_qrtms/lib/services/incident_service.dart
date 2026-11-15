import 'dart:convert';
import 'package:http/http.dart' as http;
import 'api_service.dart';

/// Team response and mutual resolution for reports and SOS alarms.
/// [type] is 'reports' or 'alarms'.
class IncidentService {
  static Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ${ApiService.token}',
      };

  static Future<Map<String, dynamic>> _post(String path, [Object? body]) async {
    try {
      final response = await http
          .post(Uri.parse('${ApiService.baseUrl}$path'),
              headers: _headers, body: body == null ? null : jsonEncode(body))
          .timeout(const Duration(seconds: 20));
      final result = jsonDecode(response.body);
      return result is Map<String, dynamic> ? result : {};
    } catch (_) {
      return {'message': "Can't reach the server. Please try again."};
    }
  }

  /// Personnel joins the response team.
  static Future<Map<String, dynamic>> join(String type, int id) =>
      _post('/$type/$id/join');

  /// Resident's answer to the responder's "resolved".
  static Future<Map<String, dynamic>> answer(
          String type, int id, bool resolved) =>
      _post('/$type/$id/resolution', {'resolved': resolved});

  /// Personnel: every report and SOS they responded to, newest first.
  /// Each item gets '_type' ('reports' / 'alarms').
  static Future<List<Map<String, dynamic>>> myResponseHistory() async {
    final results = <Map<String, dynamic>>[];
    for (final type in ['reports', 'alarms']) {
      try {
        final response = await http
            .get(Uri.parse('${ApiService.baseUrl}/$type?responded=1'),
                headers: _headers)
            .timeout(const Duration(seconds: 20));
        final data = jsonDecode(response.body)['data'];
        if (data is List) {
          for (final item in data) {
            if (item is Map<String, dynamic>) {
              results.add({...item, '_type': type});
            }
          }
        }
      } catch (_) {}
    }
    results.sort((a, b) =>
        '${b['created_at'] ?? ''}'.compareTo('${a['created_at'] ?? ''}'));
    return results;
  }

  /// The resident's reports and SOS waiting for their confirmation.
  static Future<List<Map<String, dynamic>>> awaitingMyConfirmation() async {
    final results = <Map<String, dynamic>>[];
    for (final type in ['reports', 'alarms']) {
      try {
        final response = await http
            .get(
                Uri.parse(
                    '${ApiService.baseUrl}/$type?status=awaiting_confirmation'),
                headers: _headers)
            .timeout(const Duration(seconds: 20));
        final data = jsonDecode(response.body)['data'];
        if (data is List) {
          for (final item in data) {
            if (item is Map<String, dynamic>) {
              results.add({...item, '_type': type});
            }
          }
        }
      } catch (_) {}
    }
    return results;
  }
}
