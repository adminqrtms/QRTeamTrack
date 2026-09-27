import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../services/api_service.dart';

class RegisterPage extends StatefulWidget {
  const RegisterPage({super.key});

  @override
  _RegisterPageState createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  TextEditingController name = TextEditingController();
  TextEditingController email = TextEditingController();
  TextEditingController password = TextEditingController();
  TextEditingController phone = TextEditingController();
  TextEditingController address = TextEditingController();

  List<dynamic> _locations = [];
  String? _selectedLocationId;
  bool _isLoading = false;
  bool _obscureText = true;
  bool _acceptTerms = false;

  @override
  void initState() {
    super.initState();
    _fetchLocations();
  }

  Future<void> _fetchLocations() async {
    var data = await ApiService.getLocations();
    setState(() {
      _locations = data;
    });
  }

  void _showTermsDialog() {
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text("Terms and Conditions"),
          content: SingleChildScrollView(
            child: const Text(
              "By registering and using the QRTeamTrack system, resident users agree to follow the terms and conditions stated below:\n\n"
              "Account Responsibility\n"
              "Residents are responsible for providing accurate and complete information during registration and for maintaining the confidentiality of their account credentials.\n\n"
              "Proper Use of the System\n"
              "The system must only be used for legitimate emergency reporting, incident reporting, and communication purposes related to public safety and QRT services.\n\n"
              "False Reports and Misuse\n"
              "Submitting false reports, fake emergency alarms, misleading information, or misuse of the emergency alert feature is strictly prohibited and may result in account suspension or legal action.\n\n"
              "Location Access\n"
              "Residents agree to allow the system to access their device location when using emergency alarms or incident reporting features to help QRT personnel respond accurately and efficiently.\n\n"
              "Incident Reports and Uploaded Content\n"
              "Residents are responsible for the accuracy of submitted reports, images, and other uploaded content. Any offensive, harmful, or unrelated content is prohibited.\n\n"
              "Privacy and Data Protection\n"
              "Personal information and location data collected by the system will only be used for emergency response, monitoring, and system-related purposes in accordance with applicable privacy policies.\n\n"
              "System Availability\n"
              "The system depends on internet connectivity and GPS services. Delays or interruptions caused by poor connection, technical issues, or device limitations are beyond the responsibility of the developers and administrators.\n\n"
              "Emergency Response Limitation\n"
              "Submitting an emergency alarm or incident report does not guarantee immediate response, as response time may depend on personnel availability, location, and emergency conditions.\n\n"
              "Account Suspension or Removal\n"
              "Administrators reserve the right to suspend, restrict, or remove accounts that violate system rules, misuse features, or compromise the safety and integrity of the platform.\n\n"
              "Acceptance of Terms\n"
              "By creating and using an account in the QRTeamTrack system, residents acknowledge that they have read, understood, and agreed to these Terms and Conditions.",
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text("Close"),
            ),
          ],
        );
      },
    );
  }

  void handleRegister() async {
    if (_selectedLocationId == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text("Please select a location")));
      return;
    }

    setState(() => _isLoading = true);
    try {
      var res = await ApiService.register(
        name: name.text,
        email: email.text,
        address: address.text,
        password: password.text,
        phoneNumber: phone.text,
        locationId: _selectedLocationId!,
      );

      print("Register Response: $res");

      // Check for 'user', 'token', or 'data' to confirm success
      if (res.containsKey('user') ||
          res.containsKey('data') ||
          res.containsKey('token')) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text("Registered Successfully")));
        Navigator.pop(context);
      } else {
        // Show the actual response content if message is missing, to help debug
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['message'] ?? "Registration Failed: $res"),
          ),
        );
      }
    } catch (e) {
      print("UI ERROR: $e");
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[50],
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 20),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // App Logo/Icon
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: Colors.orange.withOpacity(0.1),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.person_add_rounded,
                    size: 70,
                    color: Colors.orange,
                  ),
                ),
                const SizedBox(height: 20),
                const Text(
                  "CREATE ACCOUNT",
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.bold,
                    color: Colors.black87,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  "Create your account to report incidents",
                  style: TextStyle(color: Colors.grey[600], fontSize: 15),
                ),
                const SizedBox(height: 30),
                // Full Name
                TextField(
                  controller: name,
                  decoration: InputDecoration(
                    labelText: 'Full Name',
                    prefixIcon: const Icon(Icons.person_outline),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
                const SizedBox(height: 15),
                // Email
                TextField(
                  controller: email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: InputDecoration(
                    labelText: 'Email Address',
                    prefixIcon: const Icon(Icons.email_outlined),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
                const SizedBox(height: 15),
                // Phone
                TextField(
                  controller: phone,
                  decoration: InputDecoration(
                    labelText: 'Phone Number',
                    prefixIcon: const Icon(Icons.phone_outlined),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  keyboardType: TextInputType.phone,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                ),
                const SizedBox(height: 15),
                // Address
                TextField(
                  controller: address,
                  decoration: InputDecoration(
                    labelText: 'Home Address',
                    prefixIcon: const Icon(Icons.home_outlined),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
                const SizedBox(height: 15),
                // Location Dropdown
                DropdownButtonFormField<String>(
                  initialValue: _selectedLocationId,
                  decoration: InputDecoration(
                    labelText: 'Select Active Station Near You',
                    prefixIcon: const Icon(Icons.location_on_outlined),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  items: _locations.map((loc) {
                    return DropdownMenuItem<String>(
                      value: loc['id'].toString(),
                      child: Text(loc['location_name']),
                    );
                  }).toList(),
                  onChanged: (value) =>
                      setState(() => _selectedLocationId = value),
                ),
                const SizedBox(height: 15),
                // Password
                TextField(
                  controller: password,
                  obscureText: _obscureText,
                  decoration: InputDecoration(
                    labelText: 'Password',
                    prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(
                      icon: Icon(_obscureText
                          ? Icons.visibility
                          : Icons.visibility_off),
                      onPressed: () =>
                          setState(() => _obscureText = !_obscureText),
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
                const SizedBox(height: 15),
                // Terms and Conditions Checkbox
                Row(
                  children: [
                    Checkbox(
                      activeColor: Colors.orange,
                      value: _acceptTerms,
                      onChanged: (value) {
                        setState(() {
                          _acceptTerms = value ?? false;
                        });
                      },
                    ),
                    Expanded(
                      child: GestureDetector(
                        onTap: _showTermsDialog,
                        child: const Text(
                          "I agree to the Terms and Conditions",
                          style: TextStyle(
                            color: Colors.orange,
                            fontWeight: FontWeight.bold,
                            decoration: TextDecoration.underline,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                // Register Button
                SizedBox(
                  width: double.infinity,
                  height: 55,
                  child: _isLoading
                      ? const Center(child: CircularProgressIndicator())
                      : ElevatedButton(
                          onPressed: _acceptTerms ? handleRegister : null,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.orange,
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12)),
                          ),
                          child: const Text('REGISTER',
                              style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.white)),
                        ),
                ),
                const SizedBox(height: 15),
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text("Already have an account? Login",
                      style: TextStyle(
                          color: Colors.orange, fontWeight: FontWeight.bold)),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
