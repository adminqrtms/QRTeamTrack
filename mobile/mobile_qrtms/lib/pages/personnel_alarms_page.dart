import 'package:flutter/material.dart';
import '../services/api_service.dart';
import 'alarm_details_page.dart';
import 'report_details_page.dart';

class PersonnelAlarmsPage extends StatefulWidget {
  const PersonnelAlarmsPage({super.key});

  @override
  State<PersonnelAlarmsPage> createState() => _PersonnelAlarmsPageState();
}

class _PersonnelAlarmsPageState extends State<PersonnelAlarmsPage> {
  bool _isLoading = true;
  List<dynamic> _alarms = [];
  List<dynamic> _reports = [];

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    try {
      final alarmRes = await ApiService.getAlarms();
      final reportRes = await ApiService.getReports();

      setState(() {
        _alarms = (alarmRes['data'] as List? ?? [])
            .where((item) =>
                item['status']?.toString().toLowerCase() != 'resolved')
            .toList();
        _reports = (reportRes['data'] as List? ?? [])
            .where((item) =>
                item['status']?.toString().toLowerCase() != 'resolved')
            .toList();
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  Widget _buildStatusChip(String status) {
    Color color;
    String displayStatus = status.replaceAll('_', ' ').toUpperCase();

    switch (status.toLowerCase()) {
      case 'triggered':
      case 'pending':
        color = Colors.red;
        break;
      case 'responding':
      case 'ongoing':
        color = Colors.orange;
        break;
      case 'resolved':
        color = Colors.green;
        break;
      case 'false_alarm':
        color = Colors.grey;
        displayStatus = "False Alarm";
        break;
      default:
        color = Colors.grey;
    }
    return Chip(
      label: Text(displayStatus,
          style: const TextStyle(color: Colors.white, fontSize: 10)),
      backgroundColor: color,
    );
  }

  Widget _buildList(List<dynamic> items, bool isAlarm) {
    if (items.isEmpty) {
      return Center(child: Text("No ${isAlarm ? 'Alarms' : 'Reports'} found."));
    }

    return RefreshIndicator(
      onRefresh: _loadData,
      child: ListView.builder(
        itemCount: items.length,
        itemBuilder: (context, index) {
          final item = items[index];
          return Card(
            margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            child: ListTile(
              leading: CircleAvatar(
                backgroundColor: isAlarm ? Colors.red[100] : Colors.blue[100],
                child: Icon(isAlarm ? Icons.warning : Icons.report,
                    color: isAlarm ? Colors.red : Colors.blue),
              ),
              title: Text(isAlarm
                  ? "SOS: ${item['user']['name']}"
                  : item['title'] ?? "Incident"),
              subtitle: Text(item['created_at'] != null
                  ? DateTime.parse(item['created_at'])
                      .toLocal()
                      .toString()
                      .substring(0, 16)
                  : ""),
              trailing: _buildStatusChip(item['status']),
              onTap: () {
                _navigateToDetails(item, isAlarm);
              },
            ),
          );
        },
      ),
    );
  }

  void _navigateToDetails(Map<String, dynamic> item, bool isAlarm) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => isAlarm
            ? AlarmDetailsPage(data: item)
            : ReportDetailsPage(data: item),
      ),
    ).then((_) {
      // Refresh the list when returning to see status updates
      _loadData();
    });
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text("Emergency & Incidents"),
          backgroundColor: Colors.orange,
          bottom: const TabBar(
            tabs: [
              Tab(icon: Icon(Icons.notifications_active), text: "SOS Alarms"),
              Tab(icon: Icon(Icons.assignment), text: "Reports"),
            ],
            indicatorColor: Colors.white,
          ),
        ),
        body: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : TabBarView(
                children: [
                  _buildList(_alarms, true),
                  _buildList(_reports, false),
                ],
              ),
      ),
    );
  }
}
