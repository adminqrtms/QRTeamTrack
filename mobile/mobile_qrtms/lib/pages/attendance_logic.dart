// Example logic for the Attendance Screen
import 'package:geolocator/geolocator.dart';
import '../services/api_service.dart';

class AttendanceController {
  // Handles the checklist requirement: Time in/Time Out
  Future<String> handleToggle() async {
    try {
      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      final result = await ApiService.toggleAttendance(
        position.latitude,
        position.longitude,
      );

      if (result['status'] == 'success') {
        return result['message'];
      } else {
        return "Error: ${result['message']}";
      }
    } catch (e) {
      return "Connection error or GPS disabled.";
    }
  }

  // Handles the checklist requirement: Can view their salary based on duty hours
  Future<Map<String, dynamic>> loadSalaryData() async {
    return await ApiService.getMonthlySummary();
  }

  // Handles the checklist requirement: Can view their weekly schedule
  Future<List<dynamic>> loadSchedules() async {
    return await ApiService.getMySchedules();
  }
}
