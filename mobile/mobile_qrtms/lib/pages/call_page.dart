import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:livekit_client/livekit_client.dart';
import '../services/call_service.dart';
import 'chat_page.dart';

/// An audio or video call in progress (or ringing, for the caller).
class CallPage extends StatefulWidget {
  final Map<String, dynamic> call;

  /// {url, token, room} from the call API.
  final Map<String, dynamic> livekit;
  final bool isCaller;
  final Map<String, dynamic> otherPerson;

  const CallPage({
    super.key,
    required this.call,
    required this.livekit,
    required this.isCaller,
    required this.otherPerson,
  });

  @override
  State<CallPage> createState() => _CallPageState();
}

class _CallPageState extends State<CallPage> {
  Room? _room;
  EventsListener<RoomEvent>? _listener;
  StreamSubscription? _signalSubscription;
  Timer? _ringTimeout;
  Timer? _ticker;

  bool _otherJoined = false;
  bool _micOn = true;
  late bool _cameraOn = _isVideo;
  late bool _speakerOn = _isVideo;
  bool _frontCamera = true;
  bool _ending = false;
  DateTime? _talkStartedAt;
  late String _status = widget.isCaller ? 'Calling…' : 'Connecting…';

  int get _callId => widget.call['id'];
  bool get _isVideo => widget.call['type'] == 'video';

  @override
  void initState() {
    super.initState();
    CallService.instance.activeCallId = _callId;

    _signalSubscription = CallService.instance.signals.listen((event) {
      if (event.callId != _callId) return;
      if (event.signal == 'call.accepted' && mounted) {
        setState(() => _status = 'Connecting…');
      } else if (event.signal == 'call.ended') {
        final status = event.call['status'];
        _finish(
          status == 'declined'
              ? 'Call declined'
              : _otherJoined
                  ? 'Call ended'
                  : 'No answer',
        );
      }
    });

    if (widget.isCaller) {
      _ringTimeout = Timer(CallService.ringTimeout, () {
        if (!_otherJoined) _hangUp(timedOut: true, message: 'No answer');
      });
    }

    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted && _talkStartedAt != null) setState(() {});
    });

    _connect();
  }

  @override
  void dispose() {
    _ringTimeout?.cancel();
    _ticker?.cancel();
    _signalSubscription?.cancel();
    if (!_ending) {
      // Left the screen some other way: make sure the call ends everywhere
      CallService.end(_callId);
    }
    _listener?.dispose();
    _room?.disconnect();
    _room?.dispose();
    if (CallService.instance.activeCallId == _callId) {
      CallService.instance.activeCallId = null;
    }
    super.dispose();
  }

  Future<void> _connect() async {
    final room = Room(
      roomOptions: const RoomOptions(
        adaptiveStream: true,
        dynacast: true,
        defaultCameraCaptureOptions: CameraCaptureOptions(
          cameraPosition: CameraPosition.front,
        ),
      ),
    );
    final listener = room.createListener();
    _room = room;
    _listener = listener;

    listener
      ..on<ParticipantConnectedEvent>((_) => _onOtherJoined())
      ..on<ParticipantDisconnectedEvent>((_) {
        // The other person hung up or lost their connection
        if (_otherJoined) _hangUp(message: 'Call ended');
      })
      ..on<TrackSubscribedEvent>((_) => _refresh())
      ..on<TrackUnsubscribedEvent>((_) => _refresh())
      ..on<TrackMutedEvent>((_) => _refresh())
      ..on<TrackUnmutedEvent>((_) => _refresh())
      ..on<RoomDisconnectedEvent>((_) {
        if (!_ending) _hangUp(message: 'Call disconnected');
      });

    try {
      await room.connect(widget.livekit['url'], widget.livekit['token']);
      await room.localParticipant?.setMicrophoneEnabled(true);
      if (_isVideo) await room.localParticipant?.setCameraEnabled(true);
      await _applySpeaker();

      if (room.remoteParticipants.isNotEmpty) _onOtherJoined();
      _refresh();
    } catch (e) {
      debugPrint('CALL CONNECT ERROR: $e');
      _hangUp(
          message: "Couldn't connect the call. Is the call server running?");
    }
  }

  void _onOtherJoined() {
    if (_otherJoined) return;
    _ringTimeout?.cancel();
    if (!mounted) return;
    setState(() {
      _otherJoined = true;
      _talkStartedAt = DateTime.now();
      _status = '';
    });
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  Future<void> _applySpeaker() async {
    if (kIsWeb) return;
    try {
      await AudioManager.instance.setSpeakerOutputPreferred(_speakerOn);
    } catch (e) {
      debugPrint('SPEAKER ERROR: $e');
    }
  }

  /// This side hangs up.
  Future<void> _hangUp({bool timedOut = false, String? message}) async {
    if (_ending) return;
    _ending = true;
    await CallService.end(_callId, timedOut: timedOut);
    await _room?.disconnect();
    _close(message);
  }

  /// The other side ended it.
  Future<void> _finish(String message) async {
    if (_ending) return;
    _ending = true;
    await _room?.disconnect();
    _close(message);
  }

  void _close(String? message) {
    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    Navigator.pop(context);
    if (message != null) {
      messenger.showSnackBar(SnackBar(content: Text(message)));
    }
  }

  Future<void> _toggleMic() async {
    final value = !_micOn;
    await _room?.localParticipant?.setMicrophoneEnabled(value);
    setState(() => _micOn = value);
  }

  Future<void> _toggleCamera() async {
    final value = !_cameraOn;
    await _room?.localParticipant?.setCameraEnabled(value);
    setState(() => _cameraOn = value);
  }

  Future<void> _switchCamera() async {
    final track = _localVideoTrack;
    if (track == null) return;
    final front = !_frontCamera;
    await track.setCameraPosition(
      front ? CameraPosition.front : CameraPosition.back,
    );
    setState(() => _frontCamera = front);
  }

  Future<void> _toggleSpeaker() async {
    setState(() => _speakerOn = !_speakerOn);
    await _applySpeaker();
  }

  VideoTrack? get _remoteVideoTrack {
    final participants = _room?.remoteParticipants.values;
    if (participants == null || participants.isEmpty) return null;
    for (final publication in participants.first.videoTrackPublications) {
      if (publication.subscribed && !publication.muted) {
        return publication.track;
      }
    }
    return null;
  }

  LocalVideoTrack? get _localVideoTrack {
    final publications = _room?.localParticipant?.videoTrackPublications;
    if (publications == null || publications.isEmpty) return null;
    return publications.first.track;
  }

  String get _duration {
    final started = _talkStartedAt;
    if (started == null) return '';
    final seconds = DateTime.now().difference(started).inSeconds;
    final minutes = (seconds ~/ 60).toString().padLeft(2, '0');
    final rest = (seconds % 60).toString().padLeft(2, '0');
    return '$minutes:$rest';
  }

  @override
  Widget build(BuildContext context) {
    final remoteVideo = _remoteVideoTrack;
    final localVideo = _cameraOn ? _localVideoTrack : null;
    final showRemoteVideo = _isVideo && remoteVideo != null;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _hangUp();
      },
      child: Scaffold(
        backgroundColor: const Color(0xFF102027),
        body: Stack(
          children: [
            // The other person: their video, or their photo and name
            Positioned.fill(
              child: showRemoteVideo
                  ? VideoTrackRenderer(remoteVideo, fit: VideoViewFit.cover)
                  : _buildPersonInfo(),
            ),
            if (showRemoteVideo)
              Positioned(
                top: 0,
                left: 0,
                right: 0,
                child: SafeArea(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        Text(
                          widget.otherPerson['name'] ?? '',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            shadows: [Shadow(blurRadius: 4)],
                          ),
                        ),
                        Text(
                          _duration,
                          style: const TextStyle(
                            color: Colors.white,
                            shadows: [Shadow(blurRadius: 4)],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            // Yourself, small in the corner
            if (localVideo != null)
              Positioned(
                top: 0,
                right: 0,
                child: SafeArea(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: SizedBox(
                        width: 110,
                        height: 160,
                        child: VideoTrackRenderer(
                          localVideo,
                          fit: VideoViewFit.cover,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: SafeArea(child: _buildControls()),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPersonInfo() {
    return SafeArea(
      child: Column(
        children: [
          const SizedBox(height: 80),
          ChatAvatar(user: widget.otherPerson, radius: 60),
          const SizedBox(height: 24),
          Text(
            widget.otherPerson['name'] ?? 'Unknown',
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 26,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            _otherJoined ? _duration : _status,
            style: const TextStyle(color: Colors.white70, fontSize: 16),
          ),
          if (_isVideo && _otherJoined)
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text(
                'Camera is off',
                style: TextStyle(color: Colors.white54),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildControls() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      child: Wrap(
        alignment: WrapAlignment.center,
        spacing: 16,
        runSpacing: 16,
        children: [
          _ControlButton(
            icon: _micOn ? Icons.mic : Icons.mic_off,
            label: _micOn ? 'Mute' : 'Unmute',
            active: !_micOn,
            onPressed: _toggleMic,
          ),
          if (!kIsWeb)
            _ControlButton(
              icon: _speakerOn ? Icons.volume_up : Icons.hearing,
              label: _speakerOn ? 'Speaker' : 'Earpiece',
              active: _speakerOn,
              onPressed: _toggleSpeaker,
            ),
          if (_isVideo)
            _ControlButton(
              icon: _cameraOn ? Icons.videocam : Icons.videocam_off,
              label: _cameraOn ? 'Camera' : 'Camera off',
              active: !_cameraOn,
              onPressed: _toggleCamera,
            ),
          if (_isVideo && _cameraOn && !kIsWeb)
            _ControlButton(
              icon: Icons.cameraswitch,
              label: 'Flip',
              onPressed: _switchCamera,
            ),
          _ControlButton(
            icon: Icons.call_end,
            label: 'End',
            color: Colors.red,
            onPressed: () => _hangUp(),
          ),
        ],
      ),
    );
  }
}

class _ControlButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onPressed;
  final bool active;
  final Color? color;

  const _ControlButton({
    required this.icon,
    required this.label,
    required this.onPressed,
    this.active = false,
    this.color,
  });

  @override
  Widget build(BuildContext context) {
    final background =
        color ?? (active ? Colors.white : Colors.white.withValues(alpha: 0.15));
    final foreground =
        color != null ? Colors.white : (active ? Colors.black87 : Colors.white);

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        SizedBox(
          width: 60,
          height: 60,
          child: ElevatedButton(
            onPressed: onPressed,
            style: ElevatedButton.styleFrom(
              backgroundColor: background,
              shape: const CircleBorder(),
              padding: EdgeInsets.zero,
              elevation: 0,
            ),
            child: Icon(
              icon,
              color: foreground,
              size: 28,
              semanticLabel: label,
            ),
          ),
        ),
        const SizedBox(height: 6),
        Text(label, style: const TextStyle(color: Colors.white, fontSize: 12)),
      ],
    );
  }
}
