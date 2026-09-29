import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'realtime_service.dart';

class ApiService {
  static const String baseUrl = "http://192.168.0.22:8000/api";
  // ⚠️ replace with your PC IP
  static String? token;
  static String? role;
  static String? userId;

  // Load token and role from storage on app start
  static Future<void> loadAuth() async {
    final prefs = await SharedPreferences.getInstance();
    token = prefs.getString('token');
    role = prefs.getString('role');
    userId = prefs.getString('userId');
  }

  // Logout: Clear storage and static variables
  static Future<void> logout() async {
    await RealtimeService.instance.disconnect();
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
    token = null;
    role = null;
    userId = null;
  }

  static Future<Map<String, dynamic>> login(
    String email,
    String password,
  ) async {
    final response = await http.post(
      Uri.parse('$baseUrl/login'),
      headers: {'Accept': 'application/json'},
      body: {'email': email, 'password': password},
    );

    try {
      // Decode the response (handles 200 OK and 422 Validation Errors)
      var result = jsonDecode(response.body);

      // Save token if present
      if (result is Map<String, dynamic>) {
        if (result.containsKey('token')) {
          token = result['token'];
        } else if (result['data'] != null && result['data']['token'] != null)
          token = result['data']['token'];

        // Extract and Save Role
        var user = result['user'];
        if (user == null && result['data'] != null) {
          if (result['data'] is Map && result['data'].containsKey('user')) {
            user = result['data']['user'];
          } else if (result['data'] is Map) user = result['data'];
        }
        if (user != null && user['role'] != null) role = user['role'];

        // Extract User ID
        if (user != null && user['id'] != null) userId = user['id'].toString();

        // Save to Shared Preferences
        if (token != null) {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('token', token!);
          if (role != null) await prefs.setString('role', role!);
          if (userId != null) await prefs.setString('userId', userId!);
        }
      }
      return result is Map<String, dynamic> ? result : {};
    } catch (e) {
      print("Error decoding login response: ${response.body}");
      // Return a manual error if the server crashed (HTML response)
      return {"message": "Server Error or invalid format"};
    }
  }

  static Future<Map<String, dynamic>> register({
    required String name,
    required String email,
    required String password,
    required String phoneNumber,
    required String locationId,
    String? address,
    File? avatar,
  }) async {
    try {
      var request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/register'),
      );
      request.headers.addAll({'Accept': 'application/json'});

      request.fields['name'] = name;
      request.fields['email'] = email;
      request.fields['password'] = password;
      request.fields['phone_number'] = phoneNumber;
      request.fields['location_id'] = locationId;
      request.fields['address'] = address ?? '';

      if (avatar != null) {
        request.files.add(
          await http.MultipartFile.fromPath('avatar', avatar.path),
        );
      }

      var streamedResponse = await request.send();
      var response = await http.Response.fromStream(streamedResponse);

      print("REG STATUS: ${response.statusCode}");
      var result = jsonDecode(response.body);

      if (result is Map<String, dynamic>) {
        String? newToken;
        if (result.containsKey('token')) {
          newToken = result['token'];
        } else if (result['data'] != null && result['data']['token'] != null) {
          newToken = result['data']['token'];
        }

        if (newToken != null) {
          token = newToken;
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('token', newToken);

          // Registration returns the new user in 'data'; keep its role and id
          // (needed by chat to tell which messages are ours).
          final user = result['data'];
          if (user is Map) {
            role = user['role']?.toString() ?? 'resident';
            await prefs.setString('role', role!);
            if (user['id'] != null) {
              userId = user['id'].toString();
              await prefs.setString('userId', userId!);
            }
          }
        }
      }
      return result;
    } catch (e) {
      print("API ERROR: $e");
      return {"message": "Connection failed"};
    }
  }

  // Get Locations for dropdown
  static Future<List<dynamic>> getLocations() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/locations'),
        headers: {'Accept': 'application/json'},
      );
      final data = jsonDecode(response.body);
      return data['data'] ?? [];
    } catch (e) {
      print("LOCATIONS ERROR: $e");
      return [];
    }
  }

  static Future<Map<String, dynamic>> triggerAlarm({
    double latitude = 0.0,
    double longitude = 0.0,
    int? locationId,
  }) async {
    try {
      // Endpoint must be plural (/alarms) to match apiResource in Laravel
      final response = await http.post(
        Uri.parse('$baseUrl/alarms'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: {
          'latitude': latitude.toString(),
          'longitude': longitude.toString(),
          if (locationId != null) 'location_id': locationId.toString(),
        },
      );

      var result = jsonDecode(response.body);
      return result is Map<String, dynamic> ? result : {};
    } catch (e) {
      print("ALARM ERROR: $e");
      return {"message": "Failed to trigger alarm"};
    }
  }

  // Fetch locations/stations that have on-duty personnel
  static Future<Map<String, dynamic>> getAvailableStations() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/stations/available'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
      var result = jsonDecode(response.body);
      return result is Map<String, dynamic> ? result : {};
    } catch (e) {
      print("AVAILABLE STATIONS ERROR: $e");
      return {"message": "Failed to fetch available stations"};
    }
  }

  static Future<Map<String, dynamic>> submitReport(
    String title,
    String description, {
    String type = 'incident',
    double? latitude,
    double? longitude,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/reports'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: {
          'title': title,
          'description': description,
          'type': type,
          if (latitude != null) 'latitude': latitude.toString(),
          if (longitude != null) 'longitude': longitude.toString(),
        },
      );

      var result = jsonDecode(response.body);
      return result is Map<String, dynamic> ? result : {};
    } catch (e) {
      return {"message": "Failed to submit report"};
    }
  }

  static Future<Map<String, dynamic>> getAlarms() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/alarms'), // Assuming endpoint
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );

      var result = jsonDecode(response.body);
      return result is Map<String, dynamic> ? result : {};
    } catch (e) {
      return {"message": "Failed to fetch alarms"};
    }
  }

  static Future<Map<String, dynamic>> getReports() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/reports'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
      return jsonDecode(response.body);
    } catch (e) {
      return {"message": "Failed to fetch reports"};
    }
  }

  static Future<Map<String, dynamic>> updateReportStatus(
    int reportId,
    String status, {
    String? action_taken,
  }) async {
    try {
      final response = await http.put(
        Uri.parse('$baseUrl/reports/$reportId'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: jsonEncode({'status': status, 'action_taken': action_taken}),
      );
      return jsonDecode(response.body);
    } catch (e) {
      return {"message": "Failed to update report status"};
    }
  }

  static Future<Map<String, dynamic>> updateAlarmStatus(
    int alarmId,
    String status, {
    String? action_taken,
  }) async {
    try {
      final response = await http.put(
        Uri.parse('$baseUrl/alarms/$alarmId'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: jsonEncode({'status': status, 'action_taken': action_taken}),
      );
      return jsonDecode(response.body);
    } catch (e) {
      print("UPDATE ALARM ERROR: $e");
      return {"message": "Failed to update alarm status"};
    }
  }

  static Future<Map<String, dynamic>> getUser() async {
    try {
      if (token == null) await loadAuth();
      if (token == null) return {};

      final response = await http.get(
        Uri.parse('$baseUrl/user'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );

      if (response.statusCode == 200) {
        var result = jsonDecode(response.body);
        return result is Map<String, dynamic> ? result : {};
      }
      return {};
    } catch (e) {
      print("GET USER ERROR: $e");
      return {};
    }
  }

  static Future<Map<String, dynamic>> updateProfile({
    required String name,
    required String email,
    String? password,
    File? avatar,
    String? phoneNumber,
    String? address,
    String? locationId,
  }) async {
    try {
      var request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/update-profile'),
      );
      request.headers.addAll({
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });

      request.fields['name'] = name;
      request.fields['email'] = email;
      if (password != null && password.isNotEmpty) {
        request.fields['password'] = password;
      }
      if (phoneNumber != null) request.fields['phone_number'] = phoneNumber;
      if (address != null) request.fields['address'] = address;
      if (locationId != null) request.fields['location_id'] = locationId;

      if (avatar != null) {
        request.files.add(
          await http.MultipartFile.fromPath('avatar', avatar.path),
        );
      }

      var streamedResponse = await request.send();
      var response = await http.Response.fromStream(streamedResponse);
      return jsonDecode(response.body);
    } catch (e) {
      return {"message": "Update failed"};
    }
  }

  static Future<Map<String, dynamic>> updateLocation(
    double latitude,
    double longitude,
  ) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/personnel/update-location'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: {
          'latitude': latitude.toString(),
          'longitude': longitude.toString(),
        },
      );
      return jsonDecode(response.body);
    } catch (e) {
      print("GPS UPDATE ERROR: $e");
      return {"message": "Failed to update location"};
    }
  }

  static Future<Map<String, dynamic>> toggleAttendance(
    double latitude,
    double longitude,
  ) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/personnel/attendance/toggle'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: {
          'latitude': latitude.toString(),
          'longitude': longitude.toString(),
        },
      );
      return jsonDecode(response.body);
    } catch (e) {
      print("ATTENDANCE ERROR: $e");
      return {"status": "error", "message": "Connection failed"};
    }
  }

  static Future<Map<String, dynamic>> getMonthlySummary() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/personnel/monthly-summary'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );

      var result = jsonDecode(response.body);
      return result is Map<String, dynamic> ? result : {};
    } catch (e) {
      print("MONTHLY SUMMARY ERROR: $e");
      return {'status': 'error', 'message': 'Failed to fetch salary data'};
    }
  }

  static Future<Map<String, dynamic>> getAttendanceHistory() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/personnel/attendance/history'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
      var result = jsonDecode(response.body);
      return result is Map<String, dynamic> ? result : {};
    } catch (e) {
      print("HISTORY ERROR: $e");
      return {'status': 'error', 'message': 'Failed to fetch history'};
    }
  }

  static Future<Map<String, dynamic>> getAttendanceStatus() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/personnel/attendance/status'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
      var result = jsonDecode(response.body);
      return result is Map<String, dynamic> ? result : {};
    } catch (e) {
      return {};
    }
  }

  // PERSONNEL: Get Weekly Schedule
  static Future<List<dynamic>> getMySchedules() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/personnel/schedules'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
      final data = jsonDecode(response.body);
      return data['data'] ?? [];
    } catch (e) {
      return [];
    }
  }

  // SHARED: Tracker (View QRT/Personnel Location)
  static Future<List<dynamic>> getActivePersonnel() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/tracker/personnel'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
      final data = jsonDecode(response.body);
      return data['data'] ?? [];
    } catch (e) {
      return [];
    }
  }

  // PERSONNEL: Get List of Colleagues and their schedules
  static Future<List<dynamic>> getColleagues() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/personnel/colleagues'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
      final data = jsonDecode(response.body);
      return data['data'] ?? [];
    } catch (e) {
      return [];
    }
  }
}
