import 'package:flutter/material.dart';
import 'services/api_service.dart';
import 'services/push_service.dart';
import 'pages/login_page.dart';
import 'pages/register_page.dart';
import 'pages/resident_home.dart';
import 'pages/personnel_home.dart';
import 'pages/manage_account.dart';
import 'pages/weekly_schedule_page.dart';
import 'pages/personnel_tracker_screen.dart';
import 'pages/colleagues_page.dart';
import 'pages/report_details_page.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await ApiService.loadAuth();
  await PushService.instance.init();
  runApp(MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    // Determine where to go based on loaded token/role
    String initialRoute = '/';
    if (ApiService.token != null) {
      if (ApiService.role == 'personnel') {
        initialRoute = '/personnel_home';
      } else {
        initialRoute = '/resident_home';
      }
    }

    return MaterialApp(
      navigatorKey: PushService.navigatorKey,
      title: 'Barangay System',
      debugShowCheckedModeBanner: false,
      initialRoute: initialRoute,
      routes: {
        '/': (context) => LoginPage(),
        '/register': (context) => RegisterPage(),
        '/resident_home': (context) => ResidentHomePage(),
        '/personnel_home': (context) => PersonnelHomePage(),
        '/manage_account': (context) => ManageAccountPage(),
        '/weekly_schedule': (context) => WeeklySchedulePage(),
        '/personnel_tracker_screen': (context) =>
            const PersonnelTrackerScreen(),
        '/colleagues': (context) => ColleaguesPage(),
        '/report_details': (context) {
          final args = ModalRoute.of(context)?.settings.arguments;
          if (args is Map<String, dynamic>) {
            return ReportDetailsPage(data: args);
          }
          return const Scaffold(
            body: Center(child: Text("Error: Missing report data")),
          );
        },
      },
    );
  }
}
