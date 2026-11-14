import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/auto_refresh.dart';
import 'alarm_details_page.dart';
import 'report_details_page.dart';
import '../widgets/incident_widgets.dart';

class PersonnelAlarmsPage extends StatefulWidget {
  const PersonnelAlarmsPage({super.key});

  @override
  State<PersonnelAlarmsPage> createState() => _PersonnelAlarmsPageState();
}

class _PersonnelAlarmsPageState extends State<PersonnelAlarmsPage>
    with AutoRefreshMixin {
  bool _isLoading = true;
  List<dynamic> _alarms = [];
  List<dynamic> _reports = [];

  @override
  void initState() {
    super.initState();
    _loadData();
    startAutoRefresh();
  }

  @override
  Future<void> onAutoRefresh() => _loadData(silent: true);

  Future<void> _loadData({bool silent = false}) async {
    if (!silent) setState(() => _isLoading = true);
    try {
      final alarmRes = await ApiService.getAlarms();
      final reportRes = await ApiService.getReports();
      if (!mounted) return;

      setState(() {
        // Resolved ones stay in the list (shown under "Resolved"), as in the
        // resident's app.
        _alarms = List.of(alarmRes['data'] as List? ?? []);
        _reports = List.of(reportRes['data'] as List? ?? []);
        _isLoading = false;
      });
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
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
      case 'awaiting_confirmation':
        color = Colors.amber.shade800;
        displayStatus = "WAITING FOR RESIDENT";
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

    final active =
        items.where((i) => !IncidentStatus.isClosed('${i['status']}')).toList();
    final done =
        items.where((i) => IncidentStatus.isClosed('${i['status']}')).toList();

    return RefreshIndicator(
      onRefresh: _loadData,
      child: ListView(
        padding: const EdgeInsets.only(bottom: 16),
        children: [
          if (active.isEmpty)
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text("Nothing active right now.",
                  style: TextStyle(color: Colors.grey)),
            ),
          ...active.map((item) => _buildItem(item, isAlarm)),
          if (done.isNotEmpty) ...[
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
              child: Text("Resolved (${done.length})",
                  style: const TextStyle(
                      fontWeight: FontWeight.bold, color: Colors.green)),
            ),
            ...done.map((item) => _buildItem(item, isAlarm)),
          ],
        ],
      ),
    );
  }

  Widget _buildItem(dynamic item, bool isAlarm) {
    final created = item['created_at'] != null
        ? DateTime.parse(item['created_at'])
            .toLocal()
            .toString()
            .substring(0, 16)
        : "";
    final responders = responderNames(item);
    final method = item['resolution_method'];

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: isAlarm ? Colors.red[100] : Colors.blue[100],
          child: Icon(isAlarm ? Icons.warning : Icons.report,
              color: isAlarm ? Colors.red : Colors.blue),
        ),
        title: Text(isAlarm
            ? "SOS: ${item['user']?['name'] ?? 'Resident'}"
            : item['title'] ?? "Incident"),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(created),
            if (responders.isNotEmpty)
              Text("Responded by: ${responders.join(', ')}",
                  style: const TextStyle(fontWeight: FontWeight.w600)),
            if (item['status'] == 'resolved' && method != null)
              Text(
                method == 'auto'
                    ? "Auto-resolved (no reply in 24 h)"
                    : "Confirmed by the resident \u2713",
                style: const TextStyle(color: Colors.green),
              ),
          ],
        ),
        isThreeLine: responders.isNotEmpty,
        trailing: _buildStatusChip('${item['status']}'),
        onTap: () {
          _navigateToDetails(item, isAlarm);
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
