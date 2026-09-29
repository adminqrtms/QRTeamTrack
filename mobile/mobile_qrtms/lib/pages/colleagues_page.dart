import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/auto_refresh.dart';
import 'package:intl/intl.dart';

class ColleaguesPage extends StatefulWidget {
  const ColleaguesPage({super.key});

  @override
  _ColleaguesPageState createState() => _ColleaguesPageState();
}

class _ColleaguesPageState extends State<ColleaguesPage>
    with AutoRefreshMixin {
  List<dynamic> _colleagues = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchColleagues();
    startAutoRefresh();
  }

  @override
  Future<void> onAutoRefresh() => _fetchColleagues();

  Future<void> _fetchColleagues() async {
    var data = await ApiService.getColleagues();
    if (mounted) {
      setState(() {
        _colleagues = data;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Team Members"),
        backgroundColor: Colors.teal,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _colleagues.isEmpty
          ? const Center(child: Text("No team members found."))
          : ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: _colleagues.length,
              itemBuilder: (context, index) {
                var person = _colleagues[index];
                var schedules = person['personnel']?['schedules'] as List?;
                var currentSched = (schedules != null && schedules.isNotEmpty)
                    ? schedules[0]
                    : null;

                return Card(
                  child: ListTile(
                    leading: const CircleAvatar(
                      backgroundColor: Colors.teal,
                      child: Icon(Icons.person, color: Colors.white),
                    ),
                    title: Text(
                      person['name'],
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text("Phone: ${person['phone_number'] ?? 'N/A'}"),
                        Text(
                          currentSched != null
                              ? currentSched != null
                                    ? "Duty: ${currentSched['location']?['location_name'] ?? 'Unknown'} "
                                          "(${DateFormat('MMM dd').format(DateTime.parse(currentSched['schedule_date_start']))} - "
                                          "${DateFormat('MMM dd').format(DateTime.parse(currentSched['schedule_date_end']))})"
                                    : "No duty today"
                              : "No duty today",
                          style: TextStyle(
                            color: currentSched != null
                                ? Colors.green
                                : Colors.grey,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                    trailing: const Icon(Icons.chevron_right),
                  ),
                );
              },
            ),
    );
  }
}
