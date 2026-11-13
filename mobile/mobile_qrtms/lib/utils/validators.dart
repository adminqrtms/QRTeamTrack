/// Input checks for the registration (and profile) forms.
/// Each returns an error message, or null when the value is fine.
/// The server applies the same rules (AuthController::register).
class Validators {
  static final _nameChars = RegExp(r"^[\p{L} .'-]+$", unicode: true);
  static final _email = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]{2,}$');
  static final _phMobile = RegExp(r'^09\d{9}$');
  static final _letter = RegExp(r'[A-Za-z]');
  static final _digit = RegExp(r'\d');

  static String? name(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return 'Please enter your full name';
    if (v.length < 2) return 'Name is too short';
    if (v.length > 255) return 'Name is too long';
    if (!_nameChars.hasMatch(v)) {
      return 'Use letters only, e.g. Juan Dela Cruz';
    }
    return null;
  }

  static String? email(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return 'Please enter your email address';
    if (v.length > 255 || !_email.hasMatch(v)) {
      return 'Enter a valid email, e.g. juan@gmail.com';
    }
    return null;
  }

  /// Philippine mobile number: 11 digits starting with 09.
  static String? phone(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return 'Please enter your mobile number';
    if (!_phMobile.hasMatch(v)) {
      return 'Enter an 11-digit mobile number starting with 09';
    }
    return null;
  }

  static String? address(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return 'Please enter your home address';
    if (v.length < 5) return 'Please enter your complete address';
    if (v.length > 255) return 'Address is too long';
    return null;
  }

  static String? password(String? value) {
    final v = value ?? '';
    if (v.isEmpty) return 'Please enter a password';
    if (v.length < 8) return 'Use at least 8 characters';
    if (!_letter.hasMatch(v) || !_digit.hasMatch(v)) {
      return 'Use both letters and numbers';
    }
    return null;
  }

  static String? confirmPassword(String? value, String password) {
    if ((value ?? '').isEmpty) return 'Please type your password again';
    if (value != password) return 'Passwords do not match';
    return null;
  }
}
