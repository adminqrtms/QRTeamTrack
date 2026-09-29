import 'dart:async';
import 'package:flutter/widgets.dart';

/// Re-runs [onAutoRefresh] on a fixed interval while the screen is open and
/// the app is in the foreground, so data stays current without pull-to-refresh.
mixin AutoRefreshMixin<T extends StatefulWidget> on State<T> {
  Timer? _autoRefreshTimer;
  bool _autoRefreshRunning = false;
  _LifecycleObserver? _lifecycleObserver;

  Duration get autoRefreshInterval => const Duration(seconds: 5);

  /// Fetch fresh data silently (no loading spinner).
  Future<void> onAutoRefresh();

  void startAutoRefresh() {
    _lifecycleObserver ??= _LifecycleObserver(_onLifecycleChanged);
    WidgetsBinding.instance.addObserver(_lifecycleObserver!);
    _scheduleAutoRefresh();
  }

  void _scheduleAutoRefresh() {
    _autoRefreshTimer?.cancel();
    _autoRefreshTimer =
        Timer.periodic(autoRefreshInterval, (_) => _runAutoRefresh());
  }

  Future<void> _runAutoRefresh() async {
    if (!mounted || _autoRefreshRunning) return;
    _autoRefreshRunning = true;
    try {
      await onAutoRefresh();
    } catch (_) {
      // Ignore transient network errors; the next tick retries.
    } finally {
      _autoRefreshRunning = false;
    }
  }

  void _onLifecycleChanged(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _runAutoRefresh();
      _scheduleAutoRefresh();
    } else if (state == AppLifecycleState.paused) {
      _autoRefreshTimer?.cancel();
    }
  }

  @override
  void dispose() {
    if (_lifecycleObserver != null) {
      WidgetsBinding.instance.removeObserver(_lifecycleObserver!);
    }
    _autoRefreshTimer?.cancel();
    super.dispose();
  }
}

class _LifecycleObserver with WidgetsBindingObserver {
  final void Function(AppLifecycleState) onChange;
  _LifecycleObserver(this.onChange);

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) => onChange(state);
}
