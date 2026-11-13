import 'package:flutter_test/flutter_test.dart';
import 'package:mobile_qrtms/utils/validators.dart';

void main() {
  test('name', () {
    expect(Validators.name(''), isNotNull);
    expect(Validators.name('J'), isNotNull);
    expect(Validators.name('Juan123'), isNotNull);
    expect(Validators.name('Juan Dela Cruz'), isNull);
    expect(Validators.name("Ma. Señora O'Neil-Santos"), isNull);
  });

  test('email', () {
    expect(Validators.email(''), isNotNull);
    expect(Validators.email('juan'), isNotNull);
    expect(Validators.email('juan@gmail'), isNotNull);
    expect(Validators.email('juan @gmail.com'), isNotNull);
    expect(Validators.email(' juan@gmail.com '), isNull);
  });

  test('Philippine mobile number', () {
    expect(Validators.phone(''), isNotNull);
    expect(Validators.phone('0917123456'), isNotNull); // 10 digits
    expect(Validators.phone('091712345678'), isNotNull); // 12 digits
    expect(Validators.phone('19171234567'), isNotNull);
    expect(Validators.phone('09171234567'), isNull);
  });

  test('address', () {
    expect(Validators.address(''), isNotNull);
    expect(Validators.address('abc'), isNotNull);
    expect(Validators.address('Purok 3, Poblacion'), isNull);
  });

  test('password', () {
    expect(Validators.password(''), isNotNull);
    expect(Validators.password('abc123'), isNotNull); // too short
    expect(Validators.password('abcdefgh'), isNotNull); // no number
    expect(Validators.password('12345678'), isNotNull); // no letter
    expect(Validators.password('secret123'), isNull);
    expect(Validators.confirmPassword('secret12', 'secret123'), isNotNull);
    expect(Validators.confirmPassword('secret123', 'secret123'), isNull);
  });
}
