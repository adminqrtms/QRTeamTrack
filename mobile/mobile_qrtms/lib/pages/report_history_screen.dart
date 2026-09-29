import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/auto_refresh.dart';
import 'package:intl/intl.dart';

class ReportHistoryScreen extends StatefulWidget {
  const ReportHistoryScreen({super.key});

  @override
  State<ReportHistoryScreen> createState() => _ReportHistoryScreenState();
}

class _ReportHistoryScreenState extends State<ReportHistoryScreen>
    with AutoRefreshMixin {
  late Future<List<dynamic>> _reportsFuture;

  @override
  void initState() {
    super.initState();
    _reportsFuture = _fetchReports();
    startAutoRefresh();
  }

  @override
  Future<void> onAutoRefresh() async {
    final reports = await _fetchReports();
    if (mounted) setState(() => _reportsFuture = Future.value(reports));
  }

  Future<List<dynamic>> _fetchReports() async {
    // This assumes your ApiService has a getReports method mapping to GET /api/reports
    var response = await ApiService.getReports();
    return response['data'] ?? [];
  }

  Color _getStatusColor(String status) {
    switch (status.toLowerCase()) {
      case 'pending':
        return Colors.orange;
      case 'ongoing':
        return Colors.blue;
      case 'resolved':
        return Colors.green;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Incident Reports'),
        centerTitle: true,
      ),
      body: FutureBuilder<List<dynamic>>(
        future: _reportsFuture,
        builder: (context, snapshot) {
          // Keep showing the previous list while a background refresh completes.
          if (snapshot.connectionState == ConnectionState.waiting &&
              !snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          } else if (snapshot.hasError) {
            return Center(child: Text('Error: ${snapshot.error}'));
          } else if (!snapshot.hasData || snapshot.data!.isEmpty) {
            return const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.history_edu, size: 60, color: Colors.grey),
                  SizedBox(height: 16),
                  Text('No reports found.',
                      style: TextStyle(color: Colors.grey)),
                ],
              ),
            );
          }

          final reports = snapshot.data!;
          return RefreshIndicator(
            onRefresh: () async {
              setState(() {
                _reportsFuture = _fetchReports();
              });
            },
            child: ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: reports.length,
              itemBuilder: (context, index) {
                final report = reports[index];
                final date = DateTime.parse(report['created_at']).toLocal();

                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                  elevation: 2,
                  child: ListTile(
                    contentPadding: const EdgeInsets.all(16),
                    title: Text(
                      report['title'] ?? 'No Title',
                      style: const TextStyle(
                          fontWeight: FontWeight.bold, fontSize: 16),
                    ),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const SizedBox(height: 4),
                        Text(report['description'] ?? '',
                            maxLines: 2, overflow: TextOverflow.ellipsis),
                        const SizedBox(height: 8),
                        Text(
                          DateFormat('MMM dd, yyyy - hh:mm a').format(date),
                          style:
                              TextStyle(fontSize: 12, color: Colors.grey[600]),
                        ),
                      ],
                    ),
                    trailing: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: _getStatusColor(report['status'] ?? '')
                            .withOpacity(0.1),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                            color: _getStatusColor(report['status'] ?? '')),
                      ),
                      child: Text(
                        (report['status'] ?? 'UNKNOWN').toUpperCase(),
                        style: TextStyle(
                          color: _getStatusColor(report['status'] ?? ''),
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
