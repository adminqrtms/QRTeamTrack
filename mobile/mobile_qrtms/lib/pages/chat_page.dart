import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';
import '../services/chat_service.dart';
import '../services/realtime_service.dart';
import '../services/call_service.dart';

/// A one-to-one chat between a resident and a personnel.
///
/// [conversation] is the object returned by the /conversations endpoints.
class ChatPage extends StatefulWidget {
  final Map<String, dynamic> conversation;
  const ChatPage({super.key, required this.conversation});

  /// Opens a conversation for a report, an alarm or a personnel, then shows
  /// the chat. Shows a SnackBar with the server's reason if it can't be opened.
  static Future<void> open(
    BuildContext context, {
    int? reportId,
    int? alarmId,
    int? personnelId,
  }) async {
    final res = await ChatService.openConversation(
      reportId: reportId,
      alarmId: alarmId,
      personnelId: personnelId,
    );
    if (!context.mounted) return;

    if (res['data'] is Map<String, dynamic>) {
      await Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => ChatPage(conversation: res['data'])),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message'] ?? 'Unable to open chat')),
      );
    }
  }

  @override
  State<ChatPage> createState() => _ChatPageState();
}

class _ChatPageState extends State<ChatPage> {
  final TextEditingController _textController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final List<Map<String, dynamic>> _messages = [];
  final ImagePicker _picker = ImagePicker();

  ConversationSubscription? _subscription;
  Timer? _fallbackTimer;
  bool _isLoading = true;
  bool _isSending = false;
  bool _isLoadingOlder = false;
  bool _hasOlder = true;

  int get _conversationId => widget.conversation['id'];
  int? get _myId => int.tryParse(ApiService.userId ?? '');

  @override
  void initState() {
    super.initState();
    RealtimeService.instance.activeConversationId = _conversationId;
    _scrollController.addListener(_onScroll);
    _loadInitial();

    _subscription = RealtimeService.instance.subscribeConversation(
      _conversationId,
      onMessage: (message) {
        _addMessages([message]);
        if (message['sender_id'] != _myId) {
          ChatService.markRead(_conversationId);
        }
      },
      onRead: _applyReadReceipt,
    );

    // If the live connection is unavailable, keep the chat fresh by fetching.
    _fallbackTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!RealtimeService.instance.isConnected) _fetchNewer();
    });
  }

  @override
  void dispose() {
    if (RealtimeService.instance.activeConversationId == _conversationId) {
      RealtimeService.instance.activeConversationId = null;
    }
    _subscription?.cancel();
    _fallbackTimer?.cancel();
    _textController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _loadInitial() async {
    final messages = await ChatService.getMessages(_conversationId);
    if (!mounted) return;
    setState(() {
      _isLoading = false;
      _hasOlder = (messages?.length ?? 0) >= 50;
    });
    if (messages != null) _addMessages(messages);
    ChatService.markRead(_conversationId);
  }

  /// Catches up on anything newer than the last message we have.
  Future<void> _fetchNewer() async {
    final lastId = _messages.isNotEmpty ? _messages.last['id'] as int : null;
    final messages = await ChatService.getMessages(
      _conversationId,
      afterId: lastId,
    );
    if (messages == null || messages.isEmpty || !mounted) return;
    _addMessages(messages);
    ChatService.markRead(_conversationId);
  }

  Future<void> _loadOlder() async {
    if (_isLoadingOlder || !_hasOlder || _messages.isEmpty) return;
    setState(() => _isLoadingOlder = true);
    final messages = await ChatService.getMessages(
      _conversationId,
      beforeId: _messages.first['id'] as int,
    );
    if (!mounted) return;
    setState(() {
      _isLoadingOlder = false;
      _hasOlder = (messages?.length ?? 0) >= 50;
    });
    if (messages != null) _addMessages(messages, scrollToBottom: false);
  }

  void _onScroll() {
    // The list is reversed, so the oldest messages are at the max extent.
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 100) {
      _loadOlder();
    }
  }

  /// Merges messages by id (so live events and fetches never duplicate).
  void _addMessages(List<dynamic> incoming, {bool scrollToBottom = true}) {
    if (!mounted) return;
    setState(() {
      for (final raw in incoming) {
        if (raw is! Map) continue;
        final message = Map<String, dynamic>.from(raw);
        final index = _messages.indexWhere((m) => m['id'] == message['id']);
        if (index >= 0) {
          _messages[index] = message;
        } else {
          _messages.add(message);
        }
      }
      _messages.sort((a, b) => (a['id'] as int).compareTo(b['id'] as int));
    });
    if (scrollToBottom && _scrollController.hasClients) {
      _scrollController.animateTo(
        0,
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeOut,
      );
    }
  }

  void _applyReadReceipt(Map<String, dynamic> data) {
    if (data['reader_id'] == _myId || !mounted) return;
    final lastReadId = data['last_read_id'] as int? ?? 0;
    setState(() {
      for (final message in _messages) {
        if (message['sender_id'] == _myId &&
            message['read_at'] == null &&
            (message['id'] as int) <= lastReadId) {
          message['read_at'] = data['read_at'];
        }
      }
    });
  }

  Future<void> _send({File? image}) async {
    final text = _textController.text.trim();
    if ((text.isEmpty && image == null) || _isSending) return;

    setState(() => _isSending = true);
    final res = await ChatService.sendMessage(
      _conversationId,
      body: text.isEmpty ? null : text,
      image: image,
    );
    if (!mounted) return;
    setState(() => _isSending = false);

    if (res['data'] is Map) {
      if (image == null || text.isNotEmpty) _textController.clear();
      _addMessages([res['data']]);
    } else {
      final errors = res['errors'];
      String message = res['message'] ?? 'Failed to send message';
      if (errors is Map && errors.isNotEmpty) {
        message = (errors.values.first as List).first.toString();
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message)),
      );
    }
  }

  Future<void> _pickImage(ImageSource source) async {
    final picked = await _picker.pickImage(
      source: source,
      maxWidth: 1600,
      imageQuality: 75,
    );
    if (picked != null) await _send(image: File(picked.path));
  }

  void _showAttachmentOptions() {
    showModalBottomSheet(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera),
              title: const Text('Take a photo'),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Choose from gallery'),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.gallery);
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final other = widget.conversation['other_user'] ?? {};
    final subject = widget.conversation['subject'];
    final isPersonnel = other['role'] == 'personnel';

    return Scaffold(
      appBar: AppBar(
        backgroundColor: Colors.blue,
        foregroundColor: Colors.white,
        titleSpacing: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.call),
            tooltip: 'Voice call',
            onPressed: () => CallService.instance
                .startCall(context, widget.conversation, 'audio'),
          ),
          IconButton(
            icon: const Icon(Icons.videocam),
            tooltip: 'Video call',
            onPressed: () => CallService.instance
                .startCall(context, widget.conversation, 'video'),
          ),
        ],
        title: Row(
          children: [
            ChatAvatar(user: other, radius: 18),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    other['name'] ?? 'Unknown',
                    style: const TextStyle(fontSize: 16),
                    overflow: TextOverflow.ellipsis,
                  ),
                  Text(
                    subject != null
                        ? subject['title'] ?? ''
                        : (isPersonnel ? 'Personnel' : 'Resident'),
                    style: const TextStyle(fontSize: 12, color: Colors.white70),
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _messages.isEmpty
                    ? const Center(
                        child: Text(
                          'No messages yet. Say hello!',
                          style: TextStyle(color: Colors.grey),
                        ),
                      )
                    : ListView.builder(
                        controller: _scrollController,
                        reverse: true,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 8,
                        ),
                        itemCount: _messages.length + (_isLoadingOlder ? 1 : 0),
                        itemBuilder: (context, index) {
                          if (index == _messages.length) {
                            return const Padding(
                              padding: EdgeInsets.all(8),
                              child: Center(
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              ),
                            );
                          }
                          final message =
                              _messages[_messages.length - 1 - index];
                          return _MessageBubble(
                            message: message,
                            isMine: message['sender_id'] == _myId,
                          );
                        },
                      ),
          ),
          _buildComposer(),
        ],
      ),
    );
  }

  Widget _buildComposer() {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(4, 6, 8, 6),
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
                color: Colors.black.withValues(alpha: 0.05), blurRadius: 4),
          ],
        ),
        child: Row(
          children: [
            IconButton(
              icon: const Icon(Icons.add_photo_alternate, color: Colors.blue),
              onPressed: _isSending ? null : _showAttachmentOptions,
            ),
            Expanded(
              child: TextField(
                controller: _textController,
                minLines: 1,
                maxLines: 4,
                textCapitalization: TextCapitalization.sentences,
                decoration: InputDecoration(
                  hintText: 'Type a message...',
                  filled: true,
                  fillColor: Colors.grey[100],
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 10,
                  ),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(24),
                    borderSide: BorderSide.none,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 6),
            _isSending
                ? const Padding(
                    padding: EdgeInsets.all(12),
                    child: SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  )
                : IconButton(
                    icon: const Icon(Icons.send, color: Colors.blue),
                    onPressed: () => _send(),
                  ),
          ],
        ),
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  final Map<String, dynamic> message;
  final bool isMine;

  const _MessageBubble({required this.message, required this.isMine});

  @override
  Widget build(BuildContext context) {
    final createdAt = DateTime.tryParse(message['created_at'] ?? '')?.toLocal();
    final String? image = message['image'];
    final String? body = message['body'];

    return Align(
      alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 3),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.75,
        ),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: isMine ? Colors.blue : Colors.grey[200],
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(isMine ? 16 : 4),
            bottomRight: Radius.circular(isMine ? 4 : 16),
          ),
        ),
        child: Column(
          crossAxisAlignment:
              isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            if (image != null)
              GestureDetector(
                onTap: () => _showFullImage(context, image),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: Image.network(
                    ChatService.storageUrl(image),
                    width: 220,
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stackTrace) =>
                        const SizedBox(
                      width: 220,
                      height: 120,
                      child: Icon(Icons.broken_image, size: 40),
                    ),
                  ),
                ),
              ),
            if (image != null && body != null) const SizedBox(height: 6),
            if (body != null)
              Text(
                body,
                style: TextStyle(
                  color: isMine ? Colors.white : Colors.black87,
                  fontSize: 15,
                ),
              ),
            const SizedBox(height: 4),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  createdAt != null
                      ? DateFormat('hh:mm a').format(createdAt)
                      : '',
                  style: TextStyle(
                    fontSize: 10,
                    color: isMine ? Colors.white70 : Colors.grey[600],
                  ),
                ),
                if (isMine) ...[
                  const SizedBox(width: 4),
                  Icon(
                    message['read_at'] != null ? Icons.done_all : Icons.done,
                    size: 14,
                    color: Colors.white70,
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  void _showFullImage(BuildContext context, String image) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => Scaffold(
          backgroundColor: Colors.black,
          appBar: AppBar(
            backgroundColor: Colors.black,
            foregroundColor: Colors.white,
          ),
          body: Center(
            child: InteractiveViewer(
              child: Image.network(ChatService.storageUrl(image)),
            ),
          ),
        ),
      ),
    );
  }
}

/// Round avatar with the user's photo, or an icon when there is none.
class ChatAvatar extends StatelessWidget {
  final Map<dynamic, dynamic> user;
  final double radius;

  const ChatAvatar({super.key, required this.user, this.radius = 20});

  @override
  Widget build(BuildContext context) {
    final String? avatar = user['avatar'];
    return CircleAvatar(
      radius: radius,
      backgroundColor: Colors.white,
      backgroundImage:
          avatar != null ? NetworkImage(ChatService.storageUrl(avatar)) : null,
      child: avatar == null
          ? Icon(
              user['role'] == 'personnel' ? Icons.local_police : Icons.person,
              color: Colors.blue,
              size: radius,
            )
          : null,
    );
  }
}
