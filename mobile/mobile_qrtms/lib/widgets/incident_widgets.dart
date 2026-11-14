import 'package:flutter/material.dart';
import '../services/api_service.dart';

/// Friendly names and colours for report / SOS statuses.
class IncidentStatus {
  static const awaiting = 'awaiting_confirmation';

  static String label(String? status) {
    switch (status) {
      case 'pending':
        return 'Pending';
      case 'triggered':
        return 'SOS sent';
      case 'ongoing':
      case 'in_progress':
        return 'Ongoing';
      case 'responding':
        return 'Responding';
      case 'responded':
        return 'Responders on site';
      case awaiting:
        return "Waiting for resident's confirmation";
      case 'resolved':
        return 'Resolved';
      case 'closed':
        return 'Closed';
      case 'false_alarm':
        return 'False alarm';
      default:
        return (status ?? '').replaceAll('_', ' ');
    }
  }

  static Color color(String? status) {
    switch (status) {
      case 'pending':
      case 'false_alarm':
      case 'closed':
        return Colors.grey;
      case 'triggered':
        return Colors.red;
      case awaiting:
        return Colors.orange;
      case 'resolved':
        return Colors.green;
      default:
        return Colors.blue;
    }
  }

  static bool isClosed(String? status) =>
      status == 'resolved' || status == 'closed' || status == 'false_alarm';
}

/// Names of everyone who responded, from the API's `responders` list.
List<String> responderNames(Map<dynamic, dynamic> incident) =>
    ((incident['responders'] as List?) ?? [])
        .map((r) => '${(r as Map)['name'] ?? ''}')
        .where((n) => n.isNotEmpty)
        .toList();

bool isResponder(Map<dynamic, dynamic> incident) =>
    ((incident['responders'] as List?) ?? [])
        .any((r) => '${(r as Map)['id']}' == '${ApiService.userId}');

/// Personnel view: who is responding, with a JOIN RESPONSE button.
class ResponseTeamCard extends StatelessWidget {
  final List<String> names;
  final bool canJoin;
  final bool joining;
  final VoidCallback onJoin;

  const ResponseTeamCard({
    super.key,
    required this.names,
    required this.canJoin,
    required this.joining,
    required this.onJoin,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.groups, color: Colors.blue),
                const SizedBox(width: 8),
                Text('Response team (${names.length})',
                    style: const TextStyle(fontWeight: FontWeight.bold)),
              ],
            ),
            const SizedBox(height: 8),
            if (names.isEmpty)
              const Text('No responder yet.',
                  style: TextStyle(color: Colors.grey))
            else
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: names
                    .map((n) => Chip(
                          avatar: const Icon(Icons.person, size: 18),
                          label: Text(n),
                          visualDensity: VisualDensity.compact,
                        ))
                    .toList(),
              ),
            if (canJoin) ...[
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: joining ? null : onJoin,
                  icon: joining
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.group_add),
                  label: const Text('JOIN RESPONSE'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.blue,
                    foregroundColor: Colors.white,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Personnel view while the resident hasn't confirmed yet.
class AwaitingConfirmationNotice extends StatelessWidget {
  const AwaitingConfirmationNotice({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.orange[50],
        border: Border.all(color: Colors.orange),
        borderRadius: BorderRadius.circular(8),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.hourglass_top, color: Colors.orange),
          SizedBox(width: 10),
          Expanded(
            child: Text(
              "Waiting for the resident to confirm it is resolved. "
              "If they don't answer within 24 hours, it resolves automatically.",
            ),
          ),
        ],
      ),
    );
  }
}

/// Resident view: "Is it resolved?" with YES / NOT YET.
class ResolutionConfirmCard extends StatelessWidget {
  final String title;
  final List<String> responders;
  final bool busy;
  final void Function(bool resolved) onAnswer;

  const ResolutionConfirmCard({
    super.key,
    required this.title,
    required this.responders,
    required this.busy,
    required this.onAnswer,
  });

  @override
  Widget build(BuildContext context) {
    final who = responders.isEmpty ? 'The responder' : responders.join(', ');
    return Card(
      color: Colors.orange[50],
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: Colors.orange),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.help_outline, color: Colors.orange),
                SizedBox(width: 8),
                Text('Is it resolved?',
                    style:
                        TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              ],
            ),
            const SizedBox(height: 6),
            Text('$who marked "$title" as resolved. Do you agree?'),
            const SizedBox(height: 12),
            if (busy)
              const Center(child: CircularProgressIndicator())
            else
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => onAnswer(false),
                      child: const Text('NOT YET'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () => onAnswer(true),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                        foregroundColor: Colors.white,
                      ),
                      child: const Text('YES, RESOLVED'),
                    ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}
