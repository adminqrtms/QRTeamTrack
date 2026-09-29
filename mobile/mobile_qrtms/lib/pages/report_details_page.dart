import 'package:flutter/material.dart';
import '../services/api_service.dart';
import 'chat_page.dart';

class ReportDetailsPage extends StatefulWidget {
  final Map<String, dynamic> data;
  const ReportDetailsPage({super.key, required this.data});

  @override
  _ReportDetailsPageState createState() => _ReportDetailsPageState();
}

class _ReportDetailsPageState extends State<ReportDetailsPage> {
  final TextEditingController _actionController = TextEditingController();
  bool _isUpdating = false;
  late String _currentStatus;

  @override
  void initState() {
    super.initState();
    _currentStatus = widget.data['status'] ?? 'pending';
    _actionController.text = widget.data['action_taken'] ?? '';
  }

  Future<void> _updateStatus(String newStatus) async {
    setState(() => _isUpdating = true);
    try {
      final res = await ApiService.updateReportStatus(
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
          SnackBar(content: Text(res['message'] ?? "Status updated")),
        );
        if (newStatus == 'resolved') Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) setState(() => _isUpdating = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Failed to update status")),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    // Laravel index returns 'resident' via the user_id relationship
    final resident = widget.data['resident'] ?? widget.data['user'] ?? {};
    final location = widget.data['location'] ?? {};

    final String? imagePath = widget.data['image'];
    String? imageUrl;

    if (imagePath != null && imagePath.isNotEmpty) {
      if (imagePath.startsWith('http')) {
        imageUrl = imagePath;
      } else {
        // Cleanly replace /api with /storage/ and handle leading slashes in path
        final String cleanPath =
            imagePath.startsWith('/') ? imagePath.substring(1) : imagePath;
        imageUrl =
            "${ApiService.baseUrl.replaceAll('/api', '/storage/')}$cleanPath";
      }
    }

    debugPrint("Final Generated Image URL: $imageUrl");

    return Scaffold(
      appBar: AppBar(
        title: const Text("Incident Details"),
        backgroundColor: Colors.blueAccent,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // 1. Incident Photo Header
            if (imageUrl != null)
              Card(
                clipBehavior: Clip.antiAlias,
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
                child: Image.network(
                  imageUrl,
                  height: 250,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  errorBuilder: (context, error, stackTrace) => Container(
                    height: 100,
                    color: Colors.grey[300],
                    child: const Icon(Icons.broken_image, size: 50),
                  ),
                ),
              ),
            const SizedBox(height: 16),

            // 2. Report Header Info
            Text(
              widget.data['title'] ?? widget.data['concern'] ?? "No Title",
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Chip(
                    backgroundColor: Colors.blue[50],
                    label: Text(
                        (widget.data['type'] ?? 'INCIDENT').toUpperCase(),
                        style: const TextStyle(fontWeight: FontWeight.bold))),
                const SizedBox(width: 10),
                Text(
                  widget.data['created_at'] != null
                      ? "Reported: ${widget.data['created_at'].toString().substring(0, 16)}"
                      : "",
                  style: TextStyle(color: Colors.grey[600]),
                ),
              ],
            ),
            const Divider(height: 40),

            // 3. Incident Location Data
            const Text("Location Details",
                style: TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.location_on, color: Colors.blue),
              title: Text(location['location_name'] ?? "Unknown Station"),
              subtitle: Text(
                  "GPS: ${widget.data['latitude']}, ${widget.data['longitude']}"),
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
            const SizedBox(height: 20),

            // 4. Description
            const Text("Description",
                style: TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.grey[100],
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                widget.data['description'] ?? "No description provided.",
                style: const TextStyle(fontSize: 15),
              ),
            ),
            const SizedBox(height: 24),

            // 5. Resident Information
            const Text("Resident Contact",
                style: TextStyle(fontWeight: FontWeight.bold)),
            Card(
              child: ListTile(
                leading: const CircleAvatar(
                    backgroundColor: Colors.blue,
                    child: Icon(Icons.person, color: Colors.white)),
                title: Text(resident['name'] ?? "Unknown Resident"),
                subtitle: Text("Phone: ${resident['phone_number'] ?? 'N/A'}"),
                trailing: IconButton(
                  icon: const Icon(Icons.chat, color: Colors.blue),
                  tooltip: "Message resident",
                  onPressed: () =>
                      ChatPage.open(context, reportId: widget.data['id']),
                ),
              ),
            ),
            const SizedBox(height: 24),

            // 6. Action and Status Update
            const Text("Responder Action Report",
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            const SizedBox(height: 10),
            TextField(
              controller: _actionController,
              maxLines: 3,
              decoration: InputDecoration(
                hintText: "Describe the resolution or actions taken...",
                fillColor: Colors.grey[50],
                filled: true,
                border: const OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 20),
            if (_isUpdating)
              const Center(child: CircularProgressIndicator())
            else
              Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (_currentStatus == 'pending')
                    ElevatedButton(
                      onPressed: () => _updateStatus('ongoing'),
                      style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.orange),
                      child: const Text("MARK AS ONGOING",
                          style: TextStyle(color: Colors.white)),
                    ),
                  const SizedBox(height: 8),
                  ElevatedButton(
                    onPressed: () => _updateStatus('resolved'),
                    style:
                        ElevatedButton.styleFrom(backgroundColor: Colors.green),
                    child: const Text("MARK AS RESOLVED",
                        style: TextStyle(color: Colors.white)),
                  ),
                  TextButton(
                    onPressed: () => _updateStatus('false_alarm'),
                    child: const Text("Invalid Report / False Alarm",
                        style: TextStyle(color: Colors.red)),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}
