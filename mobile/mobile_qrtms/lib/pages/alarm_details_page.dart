import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/incident_service.dart';
import '../widgets/incident_widgets.dart';
import 'chat_page.dart';

class AlarmDetailsPage extends StatefulWidget {
  final Map<String, dynamic> data;
  const AlarmDetailsPage({
    super.key,
    required this.data,
  });

  @override
  _AlarmDetailsPageState createState() => _AlarmDetailsPageState();
}

class _AlarmDetailsPageState extends State<AlarmDetailsPage> {
  final TextEditingController _actionController = TextEditingController();
  bool _isUpdating = false;
  bool _joining = false;
  late String _currentStatus;
  List<dynamic> _responders = [];

  @override
  void initState() {
    super.initState();
    _currentStatus = widget.data['status'] ?? 'triggered';
    _responders = (widget.data['responders'] as List?) ?? [];
    _actionController.text = widget.data['action_taken'] ?? '';
  }

  Future<void> _updateStatus(String newStatus) async {
    setState(() => _isUpdating = true);
    try {
      final res = await ApiService.updateAlarmStatus(
        widget.data['id'],
        newStatus,
        action_taken: _actionController.text,
      );

      if (mounted) {
        setState(() {
          _applyResult(res, newStatus);
          _isUpdating = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['message'] ?? "Status updated"),
          ),
        );
      }
    } catch (e) {
      if (mounted) setState(() => _isUpdating = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text("Failed to update status")));
    }
  }

  /// Keeps the screen in step with the server's answer (status, team).
  void _applyResult(Map<String, dynamic> res, String fallbackStatus) {
    final data = res['data'];
    if (data is Map) {
      _currentStatus = '${data['status'] ?? fallbackStatus}';
      if (data['responders'] is List) _responders = data['responders'];
    } else {
      _currentStatus = fallbackStatus;
    }
  }

  Future<void> _join() async {
    setState(() => _joining = true);
    final res = await IncidentService.join('alarms', widget.data['id']);
    if (!mounted) return;
    setState(() {
      _joining = false;
      if (res['data'] is Map) _applyResult(res, _currentStatus);
    });
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(res['message'] ?? 'Could not join the response')),
    );
  }

  Widget _teamCard() {
    final incident = {'responders': _responders};
    return ResponseTeamCard(
      names: responderNames(incident),
      canJoin:
          !isResponder(incident) && !IncidentStatus.isClosed(_currentStatus),
      joining: _joining,
      onJoin: _join,
    );
  }

  @override
  Widget build(BuildContext context) {
    // Alarms use 'user' relationship
    final user = widget.data['user'] ?? {};

    return Scaffold(
      appBar: AppBar(
        title: const Text("SOS Alarm Details"),
        backgroundColor: Colors.redAccent,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Card(
              elevation: 4,
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      "Resident Information",
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const Divider(),
                    ListTile(
                      leading: const Icon(Icons.person),
                      title: Text(user['name'] ?? "Unknown"),
                      subtitle: Text("Phone: ${user['phone_number'] ?? 'N/A'}"),
                      trailing: IconButton(
                        icon: const Icon(Icons.chat, color: Colors.blue),
                        tooltip: "Message resident",
                        onPressed: () =>
                            ChatPage.open(context, alarmId: widget.data['id']),
                      ),
                    ),
                    ListTile(
                      leading: const Icon(Icons.location_on),
                      title: const Text("Address"),
                      subtitle: Text(user['address'] ?? "No address provided"),
                    ),
                    ListTile(
                      leading: const Icon(Icons.map),
                      title: const Text("GPS Coordinates"),
                      subtitle: Text(
                          "${widget.data['latitude']}, ${widget.data['longitude']}"),
                      trailing: IconButton(
                        icon: const Icon(Icons.open_in_new, color: Colors.blue),
                        onPressed: () {
                          Navigator.pushNamed(
                            context,
                            '/personnel_tracker_screen',
                            arguments: widget.data,
                          );
                        },
                      ),
                    ),
                    ListTile(
                      leading: const Icon(Icons.access_time),
                      title: const Text("Triggered At"),
                      subtitle: Text(widget.data['created_at']
                              ?.toString()
                              .substring(0, 16) ??
                          ""),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),
            // Response team (several personnel can respond together)
            _teamCard(),
            const SizedBox(height: 16),
            Row(
              children: [
                const Text("Status: ",
                    style: TextStyle(fontWeight: FontWeight.bold)),
                Chip(
                  label: Text(IncidentStatus.label(_currentStatus),
                      style: const TextStyle(color: Colors.white)),
                  backgroundColor: IncidentStatus.color(_currentStatus),
                ),
              ],
            ),
            const SizedBox(height: 10),
            const Text(
              "Incident Action Report",
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _actionController,
              maxLines: 4,
              decoration: InputDecoration(
                hintText: "Describe the actions taken (Optional)...",
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
                filled: true,
                fillColor: Colors.grey[100],
              ),
            ),
            const SizedBox(height: 20),
            _isUpdating
                ? const Center(child: CircularProgressIndicator())
                : _currentStatus == IncidentStatus.awaiting
                    ? const AwaitingConfirmationNotice()
                    : IncidentStatus.isClosed(_currentStatus)
                        ? const SizedBox.shrink()
                        : Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              if (_currentStatus == 'triggered' ||
                                  _currentStatus == 'pending')
                                ElevatedButton.icon(
                                  onPressed: () => _updateStatus('responding'),
                                  icon: const Icon(Icons.directions_run),
                                  label: const Text("START RESPONDING"),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: Colors.orange,
                                    padding: const EdgeInsets.symmetric(
                                        vertical: 12),
                                  ),
                                ),
                              const SizedBox(height: 10),
                              ElevatedButton.icon(
                                onPressed: () => _updateStatus('resolved'),
                                icon: const Icon(Icons.check_circle),
                                label: const Text("MARK AS RESOLVED"),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.green,
                                  padding:
                                      const EdgeInsets.symmetric(vertical: 12),
                                ),
                              ),
                              const SizedBox(height: 10),
                              TextButton(
                                onPressed: () => _updateStatus('false_alarm'),
                                child: const Text(
                                  "False Alarm",
                                  style: TextStyle(color: Colors.red),
                                ),
                              ),
                            ],
                          ),
          ],
        ),
      ),
    );
  }
}
