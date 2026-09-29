import 'dart:async';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';
import '../services/chat_service.dart';
import '../services/realtime_service.dart';
import 'chat_page.dart';

/// Inbox: every conversation the logged-in resident or personnel is part of.
class ConversationsPage extends StatefulWidget {
  const ConversationsPage({super.key});

  @override
  State<ConversationsPage> createState() => _ConversationsPageState();
}

class _ConversationsPageState extends State<ConversationsPage> {
  List<dynamic> _conversations = [];
  bool _isLoading = true;
  StreamSubscription? _incomingSubscription;
  Timer? _fallbackTimer;

  bool get _isResident => ApiService.role != 'personnel';

  @override
  void initState() {
    super.initState();
    _load();
    _incomingSubscription =
        RealtimeService.instance.incomingMessages.listen((_) => _load());
    _fallbackTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (!RealtimeService.instance.isConnected) _load();
    });
  }

  @override
  void dispose() {
    _incomingSubscription?.cancel();
    _fallbackTimer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    final conversations = await ChatService.getConversations();
    if (!mounted) return;
    setState(() {
      _conversations = conversations;
      _isLoading = false;
    });
  }

  Future<void> _openChat(Map<String, dynamic> conversation) async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => ChatPage(conversation: conversation)),
    );
    _load();
  }

  /// Residents can start a direct chat with any on-duty personnel.
  Future<void> _startNewChat() async {
    final personnel = await ApiService.getActivePersonnel();
    if (!mounted) return;

    final selected = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Message an on-duty personnel',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
            ),
            if (personnel.isEmpty)
              const Padding(
                padding: EdgeInsets.fromLTRB(16, 0, 16, 24),
                child: Text(
                  'No personnel are on duty right now.',
                  style: TextStyle(color: Colors.grey),
                ),
              )
            else
              Flexible(
                child: ListView(
                  shrinkWrap: true,
                  children: personnel.map((p) {
                    return ListTile(
                      leading: ChatAvatar(user: {...p, 'role': 'personnel'}),
                      title: Text(p['name'] ?? 'Personnel'),
                      subtitle: Text(
                        p['location']?['location_name'] ?? 'On duty',
                      ),
                      onTap: () => Navigator.pop(context, p),
                    );
                  }).toList(),
                ),
              ),
          ],
        ),
      ),
    );

    if (selected == null || !mounted) return;
    await ChatPage.open(context, personnelId: selected['id']);
    _load();
  }

  String _formatTime(String? value) {
    final date = DateTime.tryParse(value ?? '')?.toLocal();
    if (date == null) return '';
    final now = DateTime.now();
    if (date.year == now.year &&
        date.month == now.month &&
        date.day == now.day) {
      return DateFormat('hh:mm a').format(date);
    }
    return DateFormat('MMM dd').format(date);
  }

  String _preview(Map<String, dynamic> conversation) {
    final last = conversation['last_message'];
    if (last == null) return 'No messages yet';
    final isMine = last['sender_id'].toString() == ApiService.userId;
    final text = last['body'] ?? (last['image'] != null ? '📷 Photo' : '');
    return isMine ? 'You: $text' : text;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Messages'),
        backgroundColor: Colors.blue,
        foregroundColor: Colors.white,
      ),
      floatingActionButton: _isResident
          ? FloatingActionButton(
              onPressed: _startNewChat,
              backgroundColor: Colors.blue,
              child: const Icon(Icons.chat, color: Colors.white),
            )
          : null,
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: _conversations.isEmpty
                  ? ListView(
                      children: [
                        const SizedBox(height: 120),
                        const Icon(Icons.forum, size: 60, color: Colors.grey),
                        const SizedBox(height: 16),
                        Text(
                          _isResident
                              ? 'No conversations yet.\nTap the chat button to message on-duty personnel,\nor open one of your reports or alarms.'
                              : 'No conversations yet.\nOpen an alarm or incident to message the resident.',
                          textAlign: TextAlign.center,
                          style: const TextStyle(color: Colors.grey),
                        ),
                      ],
                    )
                  : ListView.separated(
                      itemCount: _conversations.length,
                      separatorBuilder: (_, __) => const Divider(height: 1),
                      itemBuilder: (context, index) {
                        final conversation =
                            Map<String, dynamic>.from(_conversations[index]);
                        final other = conversation['other_user'] ?? {};
                        final subject = conversation['subject'];
                        final unread = conversation['unread_count'] ?? 0;

                        return ListTile(
                          leading: ChatAvatar(user: other),
                          title: Text(
                            other['name'] ?? 'Unknown',
                            style: TextStyle(
                              fontWeight: unread > 0
                                  ? FontWeight.bold
                                  : FontWeight.normal,
                            ),
                          ),
                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              if (subject != null)
                                Text(
                                  subject['title'] ?? '',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: Colors.blue,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              Text(
                                _preview(conversation),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontWeight: unread > 0
                                      ? FontWeight.bold
                                      : FontWeight.normal,
                                ),
                              ),
                            ],
                          ),
                          trailing: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(
                                _formatTime(
                                  conversation['last_message_at'] ??
                                      conversation['created_at'],
                                ),
                                style: const TextStyle(
                                  fontSize: 11,
                                  color: Colors.grey,
                                ),
                              ),
                              const SizedBox(height: 4),
                              if (unread > 0)
                                CircleAvatar(
                                  radius: 10,
                                  backgroundColor: Colors.red,
                                  child: Text(
                                    unread > 99 ? '99+' : '$unread',
                                    style: const TextStyle(
                                      fontSize: 10,
                                      color: Colors.white,
                                    ),
                                  ),
                                ),
                            ],
                          ),
                          onTap: () => _openChat(conversation),
                        );
                      },
                    ),
            ),
    );
  }
}
