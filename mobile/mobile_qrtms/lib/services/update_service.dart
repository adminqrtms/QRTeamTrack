import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import 'api_service.dart';
import 'push_service.dart';

/// In-app updates (no Play Store): on start and when the app comes back to
/// the screen, asks the server for the newest version and offers it.
///
/// build-apk.bat builds with APP_BUILD / APP_VERSION. Builds without them
/// (run-app.bat during development) never check.
class UpdateService with WidgetsBindingObserver {
  UpdateService._();
  static final UpdateService instance = UpdateService._();

  static const int currentBuild = int.fromEnvironment('APP_BUILD');
  static const String currentVersion = String.fromEnvironment(
    'APP_VERSION',
    defaultValue: '1.0.0',
  );

  static const Duration _recheckAfter = Duration(minutes: 30);

  bool _started = false;
  bool _dialogOpen = false;
  DateTime? _lastCheck;

  bool get _supported => !kIsWeb && Platform.isAndroid && currentBuild > 0;

  void start() {
    if (_started || !_supported) return;
    _started = true;
    WidgetsBinding.instance.addObserver(this);
    // Give the first screen a moment to appear
    Future.delayed(const Duration(seconds: 2), check);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) check();
  }

  Future<void> check() async {
    if (!_supported || _dialogOpen) return;
    final now = DateTime.now();
    if (_lastCheck != null && now.difference(_lastCheck!) < _recheckAfter) {
      return;
    }
    _lastCheck = now;

    final release = await _fetchLatest();
    if (release == null || release.versionCode <= currentBuild) return;

    final context = PushService.navigatorKey.currentContext;
    if (context == null || !context.mounted) return;

    _dialogOpen = true;
    await showDialog<void>(
      context: context,
      barrierDismissible: !release.required,
      builder: (_) => UpdateDialog(release: release),
    );
    _dialogOpen = false;
  }

  Future<AppRelease?> _fetchLatest() async {
    try {
      final response = await http.get(
        Uri.parse('${ApiService.baseUrl}/app/latest?current=$currentBuild'),
        headers: {'Accept': 'application/json'},
      ).timeout(const Duration(seconds: 15));
      if (response.statusCode != 200) return null;
      final data = jsonDecode(response.body)['data'];
      return data is Map<String, dynamic> ? AppRelease.fromJson(data) : null;
    } catch (_) {
      return null; // offline or server down: try again later
    }
  }
}

class AppRelease {
  final int versionCode;
  final String versionName;
  final List<String> changes;
  final bool required;
  final int size;
  final String downloadPath;

  AppRelease({
    required this.versionCode,
    required this.versionName,
    required this.changes,
    required this.required,
    required this.size,
    required this.downloadPath,
  });

  factory AppRelease.fromJson(Map<String, dynamic> json) => AppRelease(
        versionCode: int.tryParse('${json['version_code']}') ?? 0,
        versionName: '${json['version_name'] ?? ''}',
        changes: (json['changes'] as List? ?? []).map((c) => '$c').toList(),
        required: json['required'] == true,
        size: int.tryParse('${json['size']}') ?? 0,
        downloadPath: '${json['download_path'] ?? ''}',
      );

  /// The download link on the same server address the app uses (Wi-Fi IP or ngrok).
  Uri get downloadUrl => Uri.parse(ApiService.baseUrl).resolve(downloadPath);

  String get sizeLabel => '${(size / 1048576).toStringAsFixed(1)} MB';
}

/// "Update available" with the change list, then download progress and
/// Android's installer.
class UpdateDialog extends StatefulWidget {
  final AppRelease release;

  const UpdateDialog({super.key, required this.release});

  @override
  State<UpdateDialog> createState() => _UpdateDialogState();
}

class _UpdateDialogState extends State<UpdateDialog> {
  bool _downloading = false;
  double? _progress; // null = size unknown
  String? _error;
  http.Client? _client;

  AppRelease get release => widget.release;

  @override
  void dispose() {
    _client?.close();
    super.dispose();
  }

  Future<void> _updateNow() async {
    setState(() {
      _downloading = true;
      _progress = 0;
      _error = null;
    });

    try {
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/QRTeamTrack-update.apk');
      final sink = file.openWrite();

      _client = http.Client();
      final response = await _client!
          .send(http.Request('GET', release.downloadUrl))
          .timeout(const Duration(seconds: 30));
      if (response.statusCode != 200) {
        await sink.close();
        throw 'The server could not send the update (error ${response.statusCode}).';
      }

      final total = response.contentLength ?? release.size;
      var received = 0;
      await for (final chunk in response.stream) {
        sink.add(chunk);
        received += chunk.length;
        if (mounted) {
          setState(() => _progress = total > 0 ? received / total : null);
        }
      }
      await sink.close();
      _client?.close();
      _client = null;

      // Opens Android's installer. The first time, Android asks to allow
      // "Install unknown apps" for this app.
      final result = await OpenFilex.open(
        file.path,
        type: 'application/vnd.android.package-archive',
      );
      if (!mounted) return;
      if (result.type == ResultType.done) {
        setState(() => _downloading = false);
      } else {
        throw 'Could not open the installer. Allow "Install unknown apps" '
            'for QRTeamTrack in Settings, then tap Update now again.';
      }
    } catch (e) {
      _client?.close();
      _client = null;
      if (!mounted) return;
      setState(() {
        _downloading = false;
        _error = e is String
            ? e
            : "Couldn't download the update. Check your internet connection and try again.";
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: !release.required && !_downloading,
      child: AlertDialog(
        icon: const Icon(Icons.system_update, color: Colors.blue, size: 40),
        title: Text(
          release.required ? 'Update required' : 'Update available',
          textAlign: TextAlign.center,
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Version ${release.versionName} is ready '
                '(you have ${UpdateService.currentVersion}).',
              ),
              if (release.required)
                const Padding(
                  padding: EdgeInsets.only(top: 6),
                  child: Text(
                    'Please install it to keep using the app.',
                    style: TextStyle(color: Colors.red),
                  ),
                ),
              if (release.changes.isNotEmpty) ...[
                const SizedBox(height: 14),
                const Text(
                  "What's new",
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 6),
                for (final change in release.changes)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 4),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('•  '),
                        Expanded(child: Text(change)),
                      ],
                    ),
                  ),
              ],
              if (_downloading) ...[
                const SizedBox(height: 16),
                LinearProgressIndicator(value: _progress),
                const SizedBox(height: 6),
                Text(
                  _progress == null
                      ? 'Downloading...'
                      : 'Downloading... ${(_progress! * 100).toStringAsFixed(0)}%'
                          '${release.size > 0 ? ' of ${release.sizeLabel}' : ''}',
                  style: const TextStyle(fontSize: 12, color: Colors.grey),
                ),
              ],
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: const TextStyle(color: Colors.red)),
              ],
            ],
          ),
        ),
        actions: [
          if (!release.required && !_downloading)
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Later'),
            ),
          ElevatedButton.icon(
            onPressed: _downloading ? null : _updateNow,
            icon: const Icon(Icons.download),
            label: Text(_error == null ? 'Update now' : 'Try again'),
          ),
        ],
      ),
    );
  }
}
