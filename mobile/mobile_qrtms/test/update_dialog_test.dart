import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile_qrtms/services/update_service.dart';

AppRelease _release({bool required = false}) => AppRelease.fromJson({
      'version_code': 5,
      'version_name': '1.0.5',
      'changes': ['Chat photos now load', 'Registration checks each field'],
      'required': required,
      'size': 52428800,
      'download_path': '/api/app/releases/3/download',
    });

Future<void> _show(WidgetTester tester, AppRelease release) async {
  await tester.pumpWidget(MaterialApp(
    home: Builder(
      builder: (context) => ElevatedButton(
        onPressed: () => showDialog(
          context: context,
          barrierDismissible: !release.required,
          builder: (_) => UpdateDialog(release: release),
        ),
        child: const Text('open'),
      ),
    ),
  ));
  await tester.tap(find.text('open'));
  await tester.pumpAndSettle();
}

void main() {
  test('download link uses the app server address', () {
    expect(
      _release().downloadUrl.path,
      '/api/app/releases/3/download',
    );
    expect(_release().sizeLabel, '50.0 MB');
  });

  testWidgets('shows the version and what is new, and can be postponed',
      (tester) async {
    await _show(tester, _release());

    expect(find.text('Update available'), findsOneWidget);
    expect(find.textContaining('Version 1.0.5 is ready'), findsOneWidget);
    expect(find.text('Chat photos now load'), findsOneWidget);
    expect(find.text('Registration checks each field'), findsOneWidget);
    expect(find.text('Update now'), findsOneWidget);

    await tester.tap(find.text('Later'));
    await tester.pumpAndSettle();
    expect(find.byType(UpdateDialog), findsNothing);
  });

  testWidgets('a required update cannot be skipped', (tester) async {
    await _show(tester, _release(required: true));

    expect(find.text('Update required'), findsOneWidget);
    expect(find.text('Later'), findsNothing);

    // Tapping outside or pressing Back keeps it open
    await tester.tapAt(const Offset(5, 5));
    await tester.pumpAndSettle();
    final navigator = tester.state<NavigatorState>(find.byType(Navigator));
    await navigator.maybePop();
    await tester.pumpAndSettle();
    expect(find.byType(UpdateDialog), findsOneWidget);
  });
}
