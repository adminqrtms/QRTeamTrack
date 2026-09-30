import 'dart:async';
import 'package:flutter/material.dart';
import '../services/call_service.dart';
import 'chat_page.dart';

/// Full-screen "incoming call" screen with Accept / Decline.
/// Closes with 'accept', 'decline' or 'ended' (the caller hung up).
class IncomingCallPage extends StatefulWidget {
  final Map<String, dynamic> call;
  const IncomingCallPage({super.key, required this.call});

  @override
  State<IncomingCallPage> createState() => _IncomingCallPageState();
}

class _IncomingCallPageState extends State<IncomingCallPage> {
  StreamSubscription? _signalSubscription;
  Timer? _timeout;
  bool _answered = false;

  int get _callId => widget.call['id'];
  bool get _isVideo => widget.call['type'] == 'video';

  @override
  void initState() {
    super.initState();
    _signalSubscription = CallService.instance.signals.listen((event) {
      if (event.callId == _callId && event.signal == 'call.ended') {
        _close('ended');
      }
    });
    // Safety net in case the "ended" signal never arrives
    _timeout = Timer(
      CallService.ringTimeout + const Duration(seconds: 15),
      () => _close('ended'),
    );
  }

  @override
  void dispose() {
    _signalSubscription?.cancel();
    _timeout?.cancel();
    super.dispose();
  }

  void _close(String result) {
    if (_answered || !mounted) return;
    _answered = true;
    Navigator.pop(context, result);
  }

  Future<void> _decline() async {
    if (_answered) return;
    await CallService.decline(_callId);
    _close('decline');
  }

  @override
  Widget build(BuildContext context) {
    final caller = widget.call['caller'] ?? {};

    return PopScope(
      canPop: false,
      child: Scaffold(
        backgroundColor: const Color(0xFF0D47A1),
        body: SafeArea(
          child: Column(
            children: [
              const SizedBox(height: 60),
              Text(
                _isVideo ? 'Incoming video call' : 'Incoming voice call',
                style: const TextStyle(color: Colors.white70, fontSize: 16),
              ),
              const SizedBox(height: 32),
              ChatAvatar(user: caller, radius: 60),
              const SizedBox(height: 24),
              Text(
                caller['name'] ?? 'Unknown',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                caller['role'] == 'personnel' ? 'Personnel' : 'Resident',
                style: const TextStyle(color: Colors.white70),
              ),
              const Spacer(),
              Padding(
                padding: const EdgeInsets.only(bottom: 60),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    _RoundButton(
                      icon: Icons.call_end,
                      label: 'Decline',
                      color: Colors.red,
                      onPressed: _decline,
                    ),
                    _RoundButton(
                      icon: _isVideo ? Icons.videocam : Icons.call,
                      label: 'Accept',
                      color: Colors.green,
                      onPressed: () => _close('accept'),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RoundButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onPressed;

  const _RoundButton({
    required this.icon,
    required this.label,
    required this.color,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SizedBox(
          width: 72,
          height: 72,
          child: ElevatedButton(
            onPressed: onPressed,
            style: ElevatedButton.styleFrom(
              backgroundColor: color,
              shape: const CircleBorder(),
              padding: EdgeInsets.zero,
            ),
            child: Icon(
              icon,
              color: Colors.white,
              size: 34,
              semanticLabel: label,
            ),
          ),
        ),
        const SizedBox(height: 8),
        Text(label, style: const TextStyle(color: Colors.white)),
      ],
    );
  }
}
