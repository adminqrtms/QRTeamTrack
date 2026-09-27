import 'package:flutter/material.dart';
import '../services/api_service.dart';
import 'package:intl/intl.dart';

class WeeklySchedulePage extends StatefulWidget {
  const WeeklySchedulePage({super.key});

  @override
  _WeeklySchedulePageState createState() => _WeeklySchedulePageState();
}

class _WeeklySchedulePageState extends State<WeeklySchedulePage> {
  List<dynamic> _schedules = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchSchedules();
  }

  Future<void> _fetchSchedules() async {
    var data = await ApiService.getMySchedules();
    if (mounted) {
      setState(() {
        _schedules = data;
        _isLoading = false;
      });
    }
  }

  String _formatTime(String? timeStr) {
    if (timeStr == null) return "N/A";
    try {
      // Handle H:i:s format from Laravel by prepending a dummy date
      String parseableTime = timeStr.length <= 8
          ? "2000-01-01 $timeStr"
          : timeStr;
      DateTime dt = DateTime.parse(parseableTime);
      return DateFormat('hh:mm a').format(dt);
    } catch (e) {
      return timeStr;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Weekly Schedule"),
        backgroundColor: Colors.orange,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _schedules.isEmpty
          ? const Center(child: Text("No upcoming schedules found."))
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _schedules.length,
              itemBuilder: (context, index) {
                var sched = _schedules[index];
                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: ListTile(
                    leading: const Icon(
                      Icons.calendar_month,
                      color: Colors.orange,
                    ),
                    title: Text(
                      "${DateFormat('MMM dd').format(DateTime.parse(sched['schedule_date_start']))} - ${DateFormat('MMM dd, yyyy').format(DateTime.parse(sched['schedule_date_end']))}",
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          "Location: ${sched['location']?['location_name'] ?? 'Multiple'}",
                        ),
                        Text(
                          "Shift: ${_formatTime(sched['start_time'])} to ${_formatTime(sched['end_time'])}",
                          style: const TextStyle(fontSize: 12),
                        ),
                      ],
                    ),
                    trailing: Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: Colors.green[100],
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: const Text(
                        "Active",
                        style: TextStyle(color: Colors.green, fontSize: 10),
                      ),
                    ),
                  ),
                );
              },
            ),
    );
  }
}
