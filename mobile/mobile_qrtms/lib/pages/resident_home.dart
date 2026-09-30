import 'package:flutter/material.dart';
import '../services/api_service.dart';
import 'package:geolocator/geolocator.dart';
import 'dart:async';
import 'report_incident_screen.dart';
import 'report_history_screen.dart';
import 'conversations_page.dart';
import 'chat_page.dart';
import '../services/chat_service.dart';
import '../services/realtime_service.dart';
import '../services/push_service.dart';

class ResidentHomePage extends StatefulWidget {
  const ResidentHomePage({super.key});

  @override
  _ResidentHomePageState createState() => _ResidentHomePageState();
}

class _ResidentHomePageState extends State<ResidentHomePage> {
  Map<String, dynamic>? userData;
  Map<String, dynamic>? _latestAlarm;
  Timer? _statusPollingTimer;
  int _unreadMessages = 0;
  StreamSubscription? _incomingMessageSubscription;

  @override
  void initState() {
    super.initState();
    _fetchUserData();
    RealtimeService.instance.connect();
    PushService.instance.registerDevice();
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => PushService.instance.handleLaunchNotification(),
    );
    _refreshUnreadMessages();
    _incomingMessageSubscription = RealtimeService.instance.incomingMessages
        .listen((_) => _refreshUnreadMessages());
    // Start polling for alarm status updates every 5 seconds
    _statusPollingTimer = Timer.periodic(const Duration(seconds: 5), (timer) {
      _checkActiveAlarmStatus();
      if (!RealtimeService.instance.isConnected) _refreshUnreadMessages();
    });
  }

  @override
  void dispose() {
    _statusPollingTimer?.cancel();
    _incomingMessageSubscription?.cancel();
    super.dispose();
  }

  Future<void> _refreshUnreadMessages() async {
    final count = await ChatService.getUnreadCount();
    if (mounted) setState(() => _unreadMessages = count);
  }

  Future<void> _openMessages() async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const ConversationsPage()),
    );
    _refreshUnreadMessages();
  }

  /// Whether the latest SOS has a responder the resident can message.
  bool _canMessageResponder() {
    return _latestAlarm != null &&
        _latestAlarm!['responded_by'] != null &&
        ['responding', 'responded'].contains(_latestAlarm!['status']);
  }

  Future<void> _fetchUserData() async {
    var user = await ApiService.getUser();
    if (mounted) {
      setState(() {
        userData = user;
      });
    }
  }

  Future<Position?> _getCurrentLocation() async {
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) return null;

    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) return null;
    }

    if (permission == LocationPermission.deniedForever) return null;

    return await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
    );
  }

  Future<void> _checkActiveAlarmStatus() async {
    var res = await ApiService.getAlarms();
    if (res.containsKey('data') &&
        res['data'] is List &&
        (res['data'] as List).isNotEmpty) {
      final latest = res['data'][0]; // index method returns latest first

      // If status just changed to 'responding', notify the resident
      if (_latestAlarm != null &&
          _latestAlarm!['status'] == 'triggered' &&
          latest['status'] == 'responding') {
        _showResponderComingDialog(latest['responder']?['name'] ?? "Personnel");
      }

      if (mounted) {
        setState(() {
          _latestAlarm = latest;
        });
      }
    }
  }

  void _showResponderComingDialog(String name) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text("Help is on the way!"),
        content: Text("Rescuer $name is now responding to your SOS."),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text("OK"),
          ),
        ],
      ),
    );
  }

  bool _isSosUnavailable() {
    if (_latestAlarm == null) return false;

    // Condition: Unavailable if status is 'responding' and updated within 5 minutes
    if (_latestAlarm!['status'] == 'responding') {
      try {
        DateTime updatedAt = DateTime.parse(
          _latestAlarm!['updated_at'],
        ).toLocal();
        DateTime now = DateTime.now();
        int difference = now.difference(updatedAt).inMinutes;

        return difference < 5;
      } catch (e) {
        return true; // Default to unavailable if date parsing fails while responding
      }
    }

    // Also consider 'triggered' as unavailable to prevent spamming while waiting for response
    if (_latestAlarm!['status'] == 'triggered') return true;

    return false;
  }

  Future<void> _handleCancelAlarm() async {
    if (_latestAlarm == null) return;

    final bool? confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text("Cancel SOS?"),
        content: const Text(
          "Are you sure this was a false alarm? Responders will be notified.",
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text("NO"),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text("YES, CANCEL"),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    var res = await ApiService.updateAlarmStatus(
      _latestAlarm!['id'],
      'false_alarm',
      action_taken: 'Cancelled by resident (False Alarm)',
    );

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message'] ?? "Request processed")),
      );
      _checkActiveAlarmStatus();
    }
  }

  void _handleEmergency() async {
    if (_isSosUnavailable()) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Request in progress. Please wait.")),
      );
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text("Fetching location and sending alarm...")),
    );

    Position? position = await _getCurrentLocation();

    if (position == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text("Error: Location permissions required for SOS."),
          ),
        );
      }
      return;
    }

    var res = await ApiService.triggerAlarm(
      latitude: position.latitude,
      longitude: position.longitude,
    );

    if (!mounted) return;

    // Check if the failure is specifically due to no personnel being on duty
    if (res['status'] == 'no_personnel' ||
        (res['message'] != null &&
            res['message'].toString().contains("no personnel on duty"))) {
      _showActiveStationsDialog(position);
    } else {
      _processAlarmResponse(res);
    }
  }

  void _showActiveStationsDialog(Position position) async {
    // Fetch locations/stations that currently have on-duty personnel
    var stationsRes = await ApiService.getAvailableStations();

    if (!mounted) return;

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text("No Personnel Nearby"),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              "There are no on-duty responders in your current area. Please select an active station to alert:",
            ),
            const SizedBox(height: 15),
            if (stationsRes['data'] == null ||
                (stationsRes['data'] as List).isEmpty)
              const Text(
                "No active personnel found in any location.",
                style: TextStyle(color: Colors.red),
              )
            else
              ...(stationsRes['data'] as List).map(
                (station) => ListTile(
                  leading: const Icon(Icons.location_city, color: Colors.blue),
                  title: Text(station['name']),
                  subtitle: Text(
                    "${station['active_count'] ?? 0} Personnel Active",
                  ),
                  onTap: () async {
                    Navigator.pop(context);
                    var res = await ApiService.triggerAlarm(
                      latitude: position.latitude,
                      longitude: position.longitude,
                      locationId: station[
                          'id'], // Sending the alarm to the specific active location
                    );
                    _processAlarmResponse(res);
                  },
                ),
              ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text("Cancel"),
          ),
        ],
      ),
    );
  }

  void _processAlarmResponse(Map<String, dynamic> res) {
    if (res['status'] == 'success' ||
        res.containsKey('success') ||
        res.containsKey('data')) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("ALARM SENT! Help is on the way.")),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text("Alarm Failed: ${res['message'] ?? 'Unknown error'}"),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Resident Home'),
        backgroundColor: Colors.blue,
        actions: [
          IconButton(
            icon: Badge(
              isLabelVisible: _unreadMessages > 0,
              label: Text('$_unreadMessages'),
              child: const Icon(Icons.forum),
            ),
            onPressed: _openMessages,
          ),
          IconButton(
            icon: Icon(Icons.person),
            onPressed: () {
              Navigator.pushNamed(context, '/manage_account');
            },
          ),
        ],
      ),
      drawer: Drawer(
        child: ListView(
          children: [
            UserAccountsDrawerHeader(
              accountName: Text(userData?['name'] ?? "Loading..."),
              accountEmail: Text(userData?['email'] ?? ""),
              currentAccountPicture: CircleAvatar(
                backgroundColor: Colors.white,
                backgroundImage: userData?['avatar'] != null
                    ? NetworkImage(
                        "${ApiService.baseUrl.replaceAll('/api', '/storage/')}${userData!['avatar']}",
                      )
                    : null,
                child: userData?['avatar'] == null
                    ? const Icon(Icons.person, color: Colors.blue)
                    : null,
              ),
            ),
            ListTile(
              leading: const Icon(Icons.forum),
              title: const Text('Messages'),
              trailing: _unreadMessages > 0
                  ? Badge(label: Text('$_unreadMessages'))
                  : null,
              onTap: () {
                Navigator.pop(context);
                _openMessages();
              },
            ),
            ListTile(
              leading: Icon(Icons.settings),
              title: Text('Manage Account'),
              onTap: () {
                Navigator.pushNamed(context, '/manage_account');
              },
            ),
            Divider(),
            ListTile(
              leading: Icon(Icons.logout),
              title: Text('Logout'),
              onTap: () async {
                await ApiService.logout();
                Navigator.pushReplacementNamed(context, '/');
              },
            ),
          ],
        ),
      ),
      // Scrollable on small screens; the Spacers still spread items on tall ones.
      body: LayoutBuilder(
        builder: (context, constraints) => SingleChildScrollView(
          padding: const EdgeInsets.all(20.0),
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight - 40),
            child: IntrinsicHeight(
              child: Column(
                children: [
                  if (userData != null)
                    Card(
                      child: ListTile(
                        leading: const Icon(Icons.home, color: Colors.blue),
                        title: Text(
                          userData!['location'] != null
                              ? userData!['location']['location_name']
                              : "Unknown Location",
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                        subtitle:
                            Text(userData!['address'] ?? "No address provided"),
                        trailing: const Chip(
                          label: Text("Home Area"),
                          backgroundColor: Colors.blueAccent,
                          labelStyle:
                              TextStyle(color: Colors.white, fontSize: 10),
                        ),
                      ),
                    ),
                  Spacer(),
                  // Emergency Button
                  GestureDetector(
                    onLongPress: _isSosUnavailable()
                        ? _handleCancelAlarm
                        : _handleEmergency,
                    child: Container(
                      height: 180,
                      width: 180,
                      decoration: BoxDecoration(
                        color: _isSosUnavailable() ? Colors.grey : Colors.red,
                        shape: BoxShape.circle,
                        boxShadow: [
                          if (!_isSosUnavailable())
                            BoxShadow(
                              color: Colors.red.withOpacity(0.4),
                              blurRadius: 20,
                              spreadRadius: 5,
                            ),
                        ],
                      ),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.notifications_active,
                            size: 50,
                            color: Colors.white,
                          ),
                          Text(
                            _isSosUnavailable() ? "CANCEL" : "SOS",
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 24,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  SizedBox(height: 10),
                  Text(
                    _isSosUnavailable()
                        ? "SOS is active. Long press to cancel."
                        : "Long press for Emergency",
                    style: const TextStyle(color: Colors.grey),
                  ),
                  if (_canMessageResponder()) ...[
                    const SizedBox(height: 10),
                    OutlinedButton.icon(
                      icon: const Icon(Icons.chat),
                      label: Text(
                        "Message ${_latestAlarm!['responder']?['name'] ?? 'Responder'}",
                      ),
                      onPressed: () =>
                          ChatPage.open(context, alarmId: _latestAlarm!['id']),
                    ),
                  ],
                  Spacer(),
                  // Action Buttons
                  ListTile(
                    tileColor: Colors.orange[50],
                    leading: Icon(Icons.report, color: Colors.orange),
                    title: Text("Report Incident"),
                    subtitle: Text("Non-emergency complaints"),
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => const ReportIncidentScreen(),
                        ),
                      );
                    },
                  ),
                  const SizedBox(height: 12),
                  ListTile(
                    tileColor: Colors.green[50],
                    leading: const Icon(Icons.assignment_turned_in,
                        color: Colors.green),
                    title: const Text("My Reports"),
                    subtitle: const Text("Track status of your incidents"),
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => const ReportHistoryScreen(),
                        ),
                      );
                    },
                  ),
                  const SizedBox(height: 12),
                  ListTile(
                    tileColor: Colors.indigo[50],
                    leading: Badge(
                      isLabelVisible: _unreadMessages > 0,
                      label: Text('$_unreadMessages'),
                      child: const Icon(Icons.forum, color: Colors.indigo),
                    ),
                    title: const Text("Messages"),
                    subtitle: const Text("Chat with on-duty personnel"),
                    onTap: _openMessages,
                  ),
                  const SizedBox(height: 12),
                  ListTile(
                    tileColor: Colors.blue[50],
                    leading: const Icon(Icons.gps_fixed, color: Colors.blue),
                    title: const Text("Track Personnel"),
                    subtitle: const Text("View active responders on the map"),
                    onTap: () {
                      Navigator.pushNamed(context, '/personnel_tracker_screen');
                    },
                  ),
                  Spacer(),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
