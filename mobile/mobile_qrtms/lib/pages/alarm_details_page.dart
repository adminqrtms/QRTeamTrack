import 'package:flutter/material.dart';
import '../services/api_service.dart';

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
  late String _currentStatus;

  @override
  void initState() {
    super.initState();
    _currentStatus = widget.data['status'];
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
          _currentStatus = newStatus;
          _isUpdating = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['message'] ?? "Status updated to $newStatus"),
          ),
        );
        if (newStatus == 'resolved') {
          Navigator.pop(context);
        }
      }
    } catch (e) {
      if (mounted) setState(() => _isUpdating = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text("Failed to update status")));
    }
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
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                        ),
                      const SizedBox(height: 10),
                      ElevatedButton.icon(
                        onPressed: () => _updateStatus('resolved'),
                        icon: const Icon(Icons.check_circle),
                        label: const Text("MARK AS RESOLVED"),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.green,
                          padding: const EdgeInsets.symmetric(vertical: 12),
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
