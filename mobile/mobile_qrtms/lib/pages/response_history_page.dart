import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/incident_service.dart';
import '../widgets/incident_widgets.dart';
import 'alarm_details_page.dart';
import 'report_details_page.dart';

/// Personnel: every report and SOS they responded to, newest first,
/// with the team, the status and whether the resident confirmed it.
class ResponseHistoryPage extends StatefulWidget {
  const ResponseHistoryPage({super.key});

  @override
  State<ResponseHistoryPage> createState() => _ResponseHistoryPageState();
}

class _ResponseHistoryPageState extends State<ResponseHistoryPage> {
  List<Map<String, dynamic>>? _items;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final items = await IncidentService.myResponseHistory();
    if (mounted) setState(() => _items = items);
  }

  String _date(dynamic value) {
    final parsed = DateTime.tryParse('${value ?? ''}');
    return parsed == null
        ? ''
        : DateFormat('MMM dd, yyyy - hh:mm a').format(parsed.toLocal());
  }

  @override
  Widget build(BuildContext context) {
    final items = _items;
    final solved = items
            ?.where((i) =>
                i['status'] == 'resolved' && i['resolution_method'] != null)
            .length ??
        0;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Responded Incidents'),
        backgroundColor: Colors.indigo,
        foregroundColor: Colors.white,
      ),
      body: items == null
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: items.isEmpty
                  ? ListView(children: const [
                      SizedBox(height: 120),
                      Icon(Icons.history, size: 60, color: Colors.grey),
                      SizedBox(height: 12),
                      Center(
                        child: Text(
                            "You haven't responded to any incident yet.",
                            style: TextStyle(color: Colors.grey)),
                      ),
                    ])
                  : ListView(
                      padding: const EdgeInsets.all(12),
                      children: [
                        Card(
                          color: Colors.indigo[50],
                          child: Padding(
                            padding: const EdgeInsets.all(14),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceAround,
                              children: [
                                _stat('${items.length}', 'Responded'),
                                _stat('$solved', 'Solved'),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 8),
                        ...items.map(_buildItem),
                      ],
                    ),
            ),
    );
  }

  Widget _stat(String value, String label) => Column(
        children: [
          Text(value,
              style:
                  const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
          Text(label, style: const TextStyle(color: Colors.black54)),
        ],
      );

  Widget _buildItem(Map<String, dynamic> item) {
    final isAlarm = item['_type'] == 'alarms';
    final status = '${item['status'] ?? ''}';
    final method = item['resolution_method'];
    final team = responderNames(item);
    final resident =
        (isAlarm ? item['user'] : item['resident'] ?? item['user'])?['name'] ??
            'Resident';

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: ListTile(
        contentPadding: const EdgeInsets.all(12),
        leading: CircleAvatar(
          backgroundColor: isAlarm ? Colors.red[100] : Colors.blue[100],
          child: Icon(isAlarm ? Icons.warning : Icons.report,
              color: isAlarm ? Colors.red : Colors.blue),
        ),
        title: Text(
          isAlarm ? 'SOS from $resident' : '${item['title'] ?? 'Report'}',
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            if (!isAlarm) Text('Resident: $resident'),
            Text(_date(item['created_at']),
                style: const TextStyle(fontSize: 12, color: Colors.black54)),
            if (team.isNotEmpty) Text('Team: ${team.join(', ')}'),
            const SizedBox(height: 4),
            Text(
              status == 'resolved' && method != null
                  ? (method == 'auto'
                      ? 'Resolved (auto, no reply in 24 h)'
                      : 'Resolved ✓ confirmed by the resident')
                  : IncidentStatus.label(status),
              style: TextStyle(
                color: IncidentStatus.color(status),
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
        onTap: () async {
          await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => isAlarm
                  ? AlarmDetailsPage(data: item)
                  : ReportDetailsPage(data: item),
            ),
          );
          _load();
        },
      ),
    );
  }
}
