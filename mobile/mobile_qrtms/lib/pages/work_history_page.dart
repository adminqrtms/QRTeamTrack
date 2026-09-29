import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/auto_refresh.dart';
import 'package:intl/intl.dart';

class WorkHistoryPage extends StatefulWidget {
  const WorkHistoryPage({super.key});

  @override
  _WorkHistoryPageState createState() => _WorkHistoryPageState();
}

class _WorkHistoryPageState extends State<WorkHistoryPage>
    with AutoRefreshMixin {
  List<dynamic> _history = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchHistory();
    startAutoRefresh();
  }

  @override
  Future<void> onAutoRefresh() => _fetchHistory();

  Future<void> _fetchHistory() async {
    var res = await ApiService.getAttendanceHistory();
    if (mounted) {
      setState(() {
        if (res['status'] == 'success') {
          _history = res['data']['data'];
        }
        _isLoading = false;
      });
    }
  }

  String _formatTime(String? timeStr) {
    if (timeStr == null || timeStr.isEmpty) return "Active";
    try {
      // Handle H:i:s format from Laravel
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
        title: const Text("Work History"),
        backgroundColor: Colors.blueGrey,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _history.isEmpty
          ? const Center(child: Text("No attendance records found."))
          : ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: _history.length,
              itemBuilder: (context, index) {
                var log = _history[index];
                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: log['status'] == 'Late'
                          ? Colors.orange.withOpacity(0.2)
                          : Colors.green.withOpacity(0.2),
                      child: Icon(
                        log['status'] == 'Late'
                            ? Icons.timer_outlined
                            : Icons.check_circle_outline,
                        color: log['status'] == 'Late'
                            ? Colors.orange
                            : Colors.green,
                      ),
                    ),
                    title: Text(
                      DateFormat(
                        'MMM dd, yyyy',
                      ).format(DateTime.parse(log['date'])),
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          "In: ${_formatTime(log['time_in'])} - Out: ${_formatTime(log['time_out'])}",
                        ),
                        Text(
                          "Location: ${log['location']?['location_name'] ?? 'N/A'}",
                          style: const TextStyle(fontSize: 12),
                        ),
                      ],
                    ),
                    trailing: Text(
                      log['hours_worked'] != null
                          ? "${log['hours_worked']} hrs"
                          : "Ongoing",
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        color: Colors.blueGrey,
                      ),
                    ),
                  ),
                );
              },
            ),
    );
  }
}
