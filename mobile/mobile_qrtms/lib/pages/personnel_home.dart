import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/auto_refresh.dart';
import 'package:geolocator/geolocator.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'dart:async';
import 'work_history_page.dart';
import 'alarm_details_page.dart';
import 'personnel_alarms_page.dart';
import 'conversations_page.dart';
import '../services/chat_service.dart';
import '../services/realtime_service.dart';
import '../services/push_service.dart';
import '../services/call_service.dart';

class PersonnelHomePage extends StatefulWidget {
  const PersonnelHomePage({super.key});

  @override
  _PersonnelHomePageState createState() => _PersonnelHomePageState();
}

class _PersonnelHomePageState extends State<PersonnelHomePage>
    with AutoRefreshMixin {
  bool isOnDuty = false;
  bool _isLoading = false;
  Timer? _alarmPollingTimer;
  String? currentStation;
  Map<String, dynamic>? userData;
  int? _lastAlarmId;
  int? _displayedAlarmId;
  final Set<int> _notifiedFalseAlarmIds = {};
  int _activeIncidentCount = 0;
  int _unreadMessages = 0;
  StreamSubscription? _incomingMessageSubscription;
  StreamSubscription<int>? _alarmAlertSubscription;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  @override
  void initState() {
    super.initState();
    _initLocalNotifications();
    _checkAttendanceStatus();
    _fetchUserData();
    startAutoRefresh();
    RealtimeService.instance.connect();
    CallService.instance.start();
    PushService.instance.registerDevice();
    // SOS from a push notification (full-screen alert, tap, or app open)
    _alarmAlertSubscription =
        PushService.instance.alarmAlerts.listen(_showAlarmFromPush);
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => PushService.instance.handleLaunchNotification(),
    );
    _refreshUnreadMessages();
    _incomingMessageSubscription = RealtimeService.instance.incomingMessages
        .listen((_) => _refreshUnreadMessages());
    // Start polling for new alarms every 5 seconds if on duty
    _alarmPollingTimer = Timer.periodic(const Duration(seconds: 5), (timer) {
      if (isOnDuty) {
        _fetchAlarms(isBackground: true);
      }
      if (!RealtimeService.instance.isConnected) _refreshUnreadMessages();
    });
  }

  void _initLocalNotifications() async {
    // Shared setup, so tapping notifications keeps working everywhere
    await PushService.instance.ensureLocalNotifications();
  }

  Future<void> _showSystemNotification(String name, String address) async {
    const androidDetails = AndroidNotificationDetails(
      'emergency_channel',
      'Emergency Alarms',
      importance: Importance.max,
      priority: Priority.high,
      playSound: true,
    );
    await _localNotifications.show(
      0,
      'EMERGENCY SOS!',
      'Resident $name needs help at $address',
      const NotificationDetails(android: androidDetails),
    );
  }

  Future<void> _showFalseAlarmNotification(String name) async {
    // Cancel the previous SOS notification (assuming ID 0) to clear the alert
    await _localNotifications.cancel(0);

    const androidDetails = AndroidNotificationDetails(
      'emergency_channel',
      'Emergency Alarms',
      importance: Importance.max,
      priority: Priority.high,
      color: Colors.blue,
    );
    await _localNotifications.show(
      1, // Use a different ID for the cancellation notice
      'SOS CANCELLED',
      'Resident $name flagged this as a false alarm.',
      const NotificationDetails(android: androidDetails),
    );
  }

  void _showFalseAlarmPopup(String name) {
    if (!mounted) return;
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text("SOS Cancelled"),
        content: Text(
          "Resident $name has flagged the SOS as a false alarm. "
          "You no longer need to respond to this request.",
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text("OK"),
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _alarmPollingTimer?.cancel();
    _incomingMessageSubscription?.cancel();
    _alarmAlertSubscription?.cancel();
    super.dispose();
  }

  Future<void> _checkAttendanceStatus() async {
    setState(() => _isLoading = true);
    var res = await ApiService.getAttendanceStatus();
    if (mounted) {
      setState(() {
        isOnDuty = res['is_on_duty'] ?? false;
        currentStation = res['location_name'];
        _isLoading = false;
      });
    }
  }

  Future<void> _refreshUnreadMessages() async {
    final count = await ChatService.getUnreadCount();
    if (mounted) setState(() => _unreadMessages = count);
  }

  /// Keeps the duty status and profile current without showing a spinner.
  @override
  Future<void> onAutoRefresh() async {
    if (_isLoading) return; // a time in/out request is in progress
    var res = await ApiService.getAttendanceStatus();
    if (!mounted || _isLoading || !res.containsKey('is_on_duty')) return;
    setState(() {
      isOnDuty = res['is_on_duty'] ?? false;
      currentStation = res['location_name'];
    });
    await _fetchUserData();
  }

  Future<void> _fetchUserData() async {
    var user = await ApiService.getUser();
    if (mounted && user.isNotEmpty) {
      setState(() {
        userData = user;
      });
    }
  }

  void _fetchAlarms({bool isBackground = false}) async {
    if (!isOnDuty) {
      if (!isBackground) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text("You must be TIMED IN to view or receive alarms."),
          ),
        );
      }
      return;
    }

    if (!isBackground) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text("Checking for active alarms...")),
        );
      }
    }

    // Fetch Alarms and Reports in parallel to get active counts
    final results = await Future.wait([
      ApiService.getAlarms(),
      ApiService.getReports(),
    ]);

    final alarmRes = results[0];
    final reportRes = results[1];

    int activeAlarms = 0;
    int activeReports = 0;

    // Process Alarms
    if (alarmRes.containsKey('data') && alarmRes['data'] is List) {
      final alarms = alarmRes['data'] as List;
      activeAlarms = alarms
          .where(
              (a) => a['status'] == 'triggered' || a['status'] == 'responding')
          .length;
    }

    if (alarmRes.containsKey('data') &&
        alarmRes['data'] is List &&
        alarmRes['data'].isNotEmpty) {
      final alarms = alarmRes['data'] as List;

      // Detect False Alarms (Cancellations)
      final falseAlarms = alarms
          .where((a) => a != null && a['status'] == 'false_alarm')
          .toList();
      for (var fa in falseAlarms) {
        final int faId = fa['id'];

        // Auto-close open dialog if the resident cancels the SOS
        if (faId == _displayedAlarmId && mounted) {
          Navigator.of(context).pop();
          // Reset the ID and show the cancellation message
          _displayedAlarmId = null;
          _showFalseAlarmPopup(fa['user']?['name'] ?? 'Resident');
        }

        if (!_notifiedFalseAlarmIds.contains(faId)) {
          try {
            DateTime updatedAt = DateTime.parse(fa['updated_at']).toLocal();
            // Only notify if it was updated recently (e.g., last 2 minutes)
            if (DateTime.now().difference(updatedAt).inMinutes < 2) {
              _showFalseAlarmNotification(fa['user']?['name'] ?? 'Resident');
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      "Alert Cancelled: ${fa['user']?['name']} flagged a false alarm.",
                    ),
                    backgroundColor: Colors.blue,
                  ),
                );
              }
            }
          } catch (e) {}
          _notifiedFalseAlarmIds.add(faId);
        }
      }

      // Logic for SOS Dialog (Specific to 'triggered' status)
      // Search for any active 'triggered' alarm that hasn't been notified yet
      // Safely find the first triggered alarm
      final triggeredAlarms =
          alarms.where((a) => a != null && a['status'] == 'triggered').toList();

      if (triggeredAlarms.isNotEmpty) {
        final triggeredAlarm = triggeredAlarms.first as Map<String, dynamic>;
        final int? currentId = triggeredAlarm['id'];

        if (currentId == null) return;

        // Detect a brand new emergency OR the first alarm found since starting duty
        bool isNewAlarm =
            _lastAlarmId != null && currentId > (_lastAlarmId ?? 0);
        bool isInitialActiveAlarm = _lastAlarmId == null;

        if (isNewAlarm || isInitialActiveAlarm) {
          _showEmergencyDialog(triggeredAlarm);
          _showSystemNotification(
            triggeredAlarm['user']['name'],
            triggeredAlarm['user']['address'] ?? 'Unknown',
          );
          _lastAlarmId = currentId;
        }
      }
    }

    // Process Reports (Incidents)
    if (reportRes.containsKey('data') && reportRes['data'] is List) {
      final reports = reportRes['data'] as List;
      activeReports = reports
          .where((r) => r['status'] == 'pending' || r['status'] == 'ongoing')
          .length;
    }

    if (mounted) {
      setState(() {
        _activeIncidentCount = activeAlarms + activeReports;
      });
    }
  }

  /// Shows the EMERGENCY ALARM pop-up for an SOS that came in by push.
  Future<void> _showAlarmFromPush(int alarmId) async {
    if (_displayedAlarmId == alarmId) return; // already on screen
    final alarm = await PushService.instance.getAlarm(alarmId);
    if (alarm == null || !mounted || _displayedAlarmId == alarmId) return;

    if (alarm['status'] == 'triggered') {
      // Keep the regular check from showing the same SOS again.
      if (_lastAlarmId == null || alarmId > _lastAlarmId!) {
        _lastAlarmId = alarmId;
      }
      _showEmergencyDialog(alarm);
    } else {
      // Someone already responded (or it was cancelled): show the details.
      Navigator.push(
        context,
        MaterialPageRoute(builder: (context) => AlarmDetailsPage(data: alarm)),
      );
    }
  }

  void _showEmergencyDialog(Map<String, dynamic> alarm) {
    if (!mounted) return;

    _displayedAlarmId = alarm['id'];

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        backgroundColor: Colors.red[900],
        title: const Row(
          children: [
            Icon(Icons.warning, color: Colors.white, size: 30),
            SizedBox(width: 10),
            Text("EMERGENCY ALARM", style: TextStyle(color: Colors.white)),
          ],
        ),
        content: Text(
          "A resident in your area has triggered an SOS!\n\n"
          "Resident: ${alarm['user']?['name'] ?? 'Unknown'}\n"
          "Phone: ${alarm['user']?['phone_number'] ?? 'N/A'}\n"
          "Address: ${alarm['user']?['address'] ?? 'N/A'}\n"
          "Location: ${alarm['latitude']}, ${alarm['longitude']}",
          style: const TextStyle(color: Colors.white, fontSize: 16),
        ),
        actions: [
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
            onPressed: () async {
              var res = await ApiService.updateAlarmStatus(
                alarm['id'],
                'responding',
              );
              if (mounted) {
                Navigator.pop(context);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      res['message'] ?? 'Response sent: Help is on the way!',
                    ),
                    backgroundColor: Colors.green,
                  ),
                );
              }
            },
            child: const Text(
              "I'M COMING",
              style: TextStyle(color: Colors.white),
            ),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.white),
            onPressed: () {
              Navigator.pop(context);
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => AlarmDetailsPage(data: alarm),
                ),
              );
            },
            child: const Text(
              "VIEW DETAILS",
              style: TextStyle(color: Colors.red),
            ),
          ),
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              Navigator.pushNamed(
                context,
                '/personnel_tracker_screen',
                arguments: alarm,
              );
            },
            child: const Text(
              "VIEW ON MAP",
              style: TextStyle(color: Colors.red),
            ),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text("CLOSE", style: TextStyle(color: Colors.white70)),
          ),
        ],
      ),
    ).then((_) {
      if (mounted && _displayedAlarmId == alarm['id']) {
        _displayedAlarmId = null;
      }
    });
  }

  Future<Position?> _getCurrentLocation() async {
    bool serviceEnabled;
    LocationPermission permission;

    serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Location services are disabled.')),
      );
      return null;
    }

    permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();

      if (permission == LocationPermission.denied) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please allow location permission')),
        );
        return null;
      }
    }

    if (permission == LocationPermission.deniedForever) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Location permissions are permanently denied.'),
        ),
      );
      return null;
    }

    return await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
    );
  }

  Future<void> _handleAttendanceToggle() async {
    // Show a confirmation dialog to prevent accidental triggers
    final bool? confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(isOnDuty ? "Confirm Time Out" : "Confirm Time In"),
        content: Text(
          "Are you sure you want to ${isOnDuty ? 'time out and end' : 'time in and start'} your duty?",
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text("CANCEL"),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text("CONFIRM"),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _isLoading = true);

    try {
      Position? position = await _getCurrentLocation();
      if (position == null) {
        setState(() => _isLoading = false);
        return;
      }

      // Note: Make sure ApiService.toggleAttendance exists and sends lat/lng to your PHP controller
      var res = await ApiService.toggleAttendance(
        position.latitude,
        position.longitude,
      );

      if (mounted) {
        if (res['status'] == 'success') {
          setState(() {
            isOnDuty = res['data']['is_on_duty'] ?? !isOnDuty;
            currentStation = res['data']['is_on_duty'] == true
                ? res['data']['location_name']
                : null;

            // Reset the last alarm ID when duty status changes
            if (isOnDuty) {
              _lastAlarmId = null;
              _displayedAlarmId = null;
              _notifiedFalseAlarmIds.clear();
            }
          });
        }
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(res['message'] ?? 'Request processed')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
                "Connection Error: Check if server is running and on same Wi-Fi"),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _viewSalarySummary() async {
    setState(() => _isLoading = true);
    try {
      // Note: Ensure getMonthlySummary() is implemented in your ApiService
      var res = await ApiService.getMonthlySummary();
      if (mounted && res['status'] == 'success') {
        showDialog(
          context: context,
          builder: (context) => AlertDialog(
            title: Text("Salary Summary (${res['month']})"),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text("Total Hours: ${res['total_hours']}"),
                Text("Hourly Rate: ₱${res['hourly_rate']}"),
                const Divider(),
                Text(
                  "Estimated Salary: ${res['monthly_salary_formatted']}",
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 18,
                  ),
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text("Close"),
              ),
            ],
          ),
        );
      }
    } catch (e) {
      print("Salary View Error: $e");
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Personnel Dashboard'),
        backgroundColor: Colors.orange,
      ),
      drawer: Drawer(
        child: ListView(
          children: [
            UserAccountsDrawerHeader(
              accountName: Text(userData?['name'] ?? "User Profile"),
              accountEmail: Text(userData?['email'] ?? "No email data"),
              currentAccountPicture: CircleAvatar(
                backgroundColor: Colors.white,
                backgroundImage: userData?['avatar'] != null
                    ? NetworkImage(
                        "${ApiService.baseUrl.replaceAll('/api', '/storage/')}${userData!['avatar']}",
                      )
                    : null,
                child: userData?['avatar'] == null
                    ? const Icon(Icons.security, color: Colors.orange)
                    : null,
              ),
              decoration: BoxDecoration(color: Colors.orange),
            ),
            ListTile(
              leading: Icon(Icons.person),
              title: Text('Manage Profile'),
              onTap: () {
                Navigator.pop(context); // Close the drawer
                Navigator.pushNamed(context, '/manage_account');
              },
            ),
            // ListTile(
            //   leading: Icon(Icons.lock),
            //   title: Text('Change Password'),
            //   onTap: () {},
            // ),
            ListTile(
              leading: Icon(Icons.attach_money),
              title: Text('View Salary'),
              subtitle: Text('Based on duty hours'),
              onTap: _viewSalarySummary,
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
      body: SingleChildScrollView(
        padding: EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Time In / Time Out Section
            Card(
              elevation: 4,
              color: isOnDuty ? Colors.green[50] : Colors.red[50],
              child: Padding(
                padding: const EdgeInsets.all(20.0),
                child: Column(
                  children: [
                    Text(
                      isOnDuty ? "ON DUTY" : "OFF DUTY",
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                        color: isOnDuty ? Colors.green : Colors.red,
                      ),
                    ),
                    SizedBox(height: 10),
                    if (currentStation != null ||
                        (userData != null && userData!['location'] != null))
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: Text(
                          "Assigned Station: ${currentStation ?? userData!['location']['location_name']}",
                          style: TextStyle(
                            color: Colors.grey[700],
                            fontSize: 14,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    _isLoading
                        ? const CircularProgressIndicator()
                        : ElevatedButton(
                            onPressed: _handleAttendanceToggle,
                            style: ElevatedButton.styleFrom(
                              backgroundColor:
                                  isOnDuty ? Colors.red : Colors.green,
                              padding: const EdgeInsets.symmetric(
                                horizontal: 40,
                                vertical: 15,
                              ),
                            ),
                            child: Text(
                              isOnDuty ? "TIME OUT" : "TIME IN",
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                              ),
                            ),
                          ),
                  ],
                ),
              ),
            ),
            SizedBox(height: 20),
            // Dashboard Grid
            GridView.count(
              shrinkWrap: true,
              physics: NeverScrollableScrollPhysics(),
              crossAxisCount: 2,
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
              children: [
                _buildDashboardItem(
                  Icons.report,
                  "Alarms and Incidents",
                  Colors.blue,
                  badgeCount: _activeIncidentCount,
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const PersonnelAlarmsPage(),
                      ),
                    );
                  },
                ),
                _buildDashboardItem(
                  Icons.notification_important,
                  "Resident Alarms",
                  Colors.red,
                  onTap: _fetchAlarms,
                ),
                _buildDashboardItem(
                  Icons.forum,
                  "Messages",
                  Colors.indigo,
                  badgeCount: _unreadMessages,
                  onTap: () async {
                    await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const ConversationsPage(),
                      ),
                    );
                    _refreshUnreadMessages();
                  },
                ),
                _buildDashboardItem(
                  Icons.calendar_today,
                  "Weekly Schedule",
                  Colors.orange,
                  onTap: () {
                    Navigator.pushNamed(context, '/weekly_schedule');
                  },
                ),
                _buildDashboardItem(
                  Icons.gps_fixed,
                  "Locate Personnel",
                  Colors.purple,
                  onTap: () {
                    Navigator.pushNamed(context, '/personnel_tracker_screen');
                  },
                ),
                _buildDashboardItem(
                  Icons.people,
                  "Colleagues",
                  Colors.teal,
                  onTap: () {
                    Navigator.pushNamed(context, '/colleagues');
                  },
                ),
                _buildDashboardItem(
                  Icons.history,
                  "Work History",
                  Colors.blueGrey,
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const WorkHistoryPage(),
                      ),
                    );
                  },
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDashboardItem(
    IconData icon,
    String label,
    Color color, {
    int badgeCount = 0,
    required VoidCallback onTap,
  }) {
    return Card(
      elevation: 2,
      child: InkWell(
        onTap: onTap,
        child: Stack(
          children: [
            Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(icon, size: 40, color: color),
                  const SizedBox(height: 10),
                  Text(label,
                      textAlign: TextAlign.center,
                      style: const TextStyle(fontWeight: FontWeight.bold)),
                ],
              ),
            ),
            if (badgeCount > 0)
              Positioned(
                right: 8,
                top: 8,
                child: Container(
                  padding: const EdgeInsets.all(4),
                  decoration: BoxDecoration(
                    color: Colors.red,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  constraints: const BoxConstraints(
                    minWidth: 20,
                    minHeight: 20,
                  ),
                  child: Text(
                    '$badgeCount',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
