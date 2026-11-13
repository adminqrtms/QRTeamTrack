import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../services/api_service.dart';
import '../utils/validators.dart';
import 'terms_and_conditions_page.dart';

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
  TextEditingController confirmPassword = TextEditingController();

  final _formKey = GlobalKey<FormState>();
  AutovalidateMode _autovalidate = AutovalidateMode.disabled;
  bool _obscureConfirm = true;

  /// Errors the server returned (e.g. email already taken), by field name.
  Map<String, String> _serverErrors = {};

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

  /// Opens the full Terms and Conditions. The box is only checked when the
  /// resident taps "I Agree"; declining leaves it unchecked.
  Future<void> _openTerms() async {
    final agreed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => const TermsAndConditionsPage()),
    );
    if (!mounted || agreed == null) return;
    setState(() => _acceptTerms = agreed);
  }

  @override
  void dispose() {
    for (final c in [name, email, password, phone, address, confirmPassword]) {
      c.dispose();
    }
    super.dispose();
  }

  /// Shows the server's error for [field] if there is one, else the local check.
  String? _check(String field, String? value, String? Function(String?) rule) {
    return _serverErrors[field] ?? rule(value);
  }

  void _clearServerError(String field) {
    if (_serverErrors.containsKey(field)) {
      setState(() => _serverErrors = Map.of(_serverErrors)..remove(field));
    }
  }

  void _showMessage(String text) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  void handleRegister() async {
    FocusScope.of(context).unfocus();
    setState(() => _serverErrors = {});

    if (!_formKey.currentState!.validate()) {
      // From now on, check each field as the user fixes it
      setState(() => _autovalidate = AutovalidateMode.onUserInteraction);
      _showMessage("Please correct the highlighted fields");
      return;
    }
    if (!_acceptTerms) {
      _showMessage("Please accept the Terms and Conditions first");
      return;
    }

    setState(() => _isLoading = true);
    try {
      var res = await ApiService.register(
        name: name.text.trim(),
        email: email.text.trim().toLowerCase(),
        address: address.text.trim(),
        password: password.text,
        phoneNumber: phone.text.trim(),
        locationId: _selectedLocationId!,
      );
      if (!mounted) return;

      // Check for 'user', 'token', or 'data' to confirm success
      if (res.containsKey('user') ||
          res.containsKey('data') ||
          res.containsKey('token')) {
        _showMessage("Registered Successfully");
        Navigator.pop(context);
        return;
      }

      final errors = res['errors'];
      if (errors is Map && errors.isNotEmpty) {
        // Show each server error under its field
        setState(() {
          _serverErrors = {
            for (final entry in errors.entries)
              entry.key.toString(): entry.value is List
                  ? (entry.value as List).first.toString()
                  : entry.value.toString(),
          };
          _autovalidate = AutovalidateMode.onUserInteraction;
        });
        _formKey.currentState!.validate();
        _showMessage(_serverErrors.values.first);
      } else {
        _showMessage(res['message']?.toString() ??
            "Registration failed. Please try again.");
      }
    } catch (e) {
      if (mounted) _showMessage("Registration failed. Please try again.");
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
            child: Form(
              key: _formKey,
              autovalidateMode: _autovalidate,
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
                  TextFormField(
                    controller: name,
                    textCapitalization: TextCapitalization.words,
                    textInputAction: TextInputAction.next,
                    onChanged: (_) => _clearServerError('name'),
                    validator: (v) => _check('name', v, Validators.name),
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
                  TextFormField(
                    controller: email,
                    keyboardType: TextInputType.emailAddress,
                    autocorrect: false,
                    textInputAction: TextInputAction.next,
                    onChanged: (_) => _clearServerError('email'),
                    validator: (v) => _check('email', v, Validators.email),
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
                  TextFormField(
                    controller: phone,
                    maxLength: 11,
                    textInputAction: TextInputAction.next,
                    onChanged: (_) => _clearServerError('phone_number'),
                    validator: (v) =>
                        _check('phone_number', v, Validators.phone),
                    decoration: InputDecoration(
                      labelText: 'Mobile Number',
                      hintText: '09XXXXXXXXX',
                      counterText: '',
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
                  TextFormField(
                    controller: address,
                    textCapitalization: TextCapitalization.words,
                    onChanged: (_) => _clearServerError('address'),
                    validator: (v) => _check('address', v, Validators.address),
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
                    validator: (v) =>
                        _serverErrors['location_id'] ??
                        (v == null ? 'Please select your station' : null),
                    onChanged: (value) {
                      _clearServerError('location_id');
                      setState(() => _selectedLocationId = value);
                    },
                  ),
                  const SizedBox(height: 15),
                  // Password
                  TextFormField(
                    controller: password,
                    obscureText: _obscureText,
                    onChanged: (_) => _clearServerError('password'),
                    validator: (v) =>
                        _check('password', v, Validators.password),
                    decoration: InputDecoration(
                      labelText: 'Password',
                      helperText:
                          'At least 8 characters, with letters and numbers',
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
                  // Confirm Password
                  TextFormField(
                    controller: confirmPassword,
                    obscureText: _obscureConfirm,
                    validator: (v) =>
                        Validators.confirmPassword(v, password.text),
                    decoration: InputDecoration(
                      labelText: 'Confirm Password',
                      prefixIcon: const Icon(Icons.lock_outline),
                      suffixIcon: IconButton(
                        icon: Icon(_obscureConfirm
                            ? Icons.visibility
                            : Icons.visibility_off),
                        onPressed: () =>
                            setState(() => _obscureConfirm = !_obscureConfirm),
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
                          // Checking the box requires reading and accepting the terms.
                          if (value == true) {
                            _openTerms();
                          } else {
                            setState(() => _acceptTerms = false);
                          }
                        },
                      ),
                      Expanded(
                        child: GestureDetector(
                          onTap: _openTerms,
                          child: const Text(
                            "I have read and agree to the Terms and Conditions",
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
                  if (!_acceptTerms)
                    const Padding(
                      padding: EdgeInsets.only(left: 12, top: 2),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          "You must accept the Terms and Conditions to create an account.",
                          style: TextStyle(color: Colors.grey, fontSize: 12),
                        ),
                      ),
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
      ),
    );
  }
}
