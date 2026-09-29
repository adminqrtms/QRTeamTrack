import 'package:flutter/material.dart';

/// A block of terms content: either a paragraph or a list of items.
class _Block {
  final String? text;
  final List<String>? items;
  final bool numbered;
  const _Block.p(this.text) : items = null, numbered = false;
  const _Block.list(this.items, {this.numbered = false}) : text = null;
}

class _Section {
  final String title;
  final List<_Block> blocks;
  const _Section(this.title, this.blocks);
}

const String _termsTitle = "QRTeamTrack Resident Terms and Conditions";

const String _termsIntro =
    "By registering for and using the QRTeamTrack system, resident users acknowledge that they have read, understood, and agreed to comply with the following Terms and Conditions. These terms are intended to promote responsible use of the system, protect user information, and support the safe and effective delivery of QRT services.";

const List<_Section> _sections = [
  _Section("1. Account Registration and Responsibility", [
    _Block.p(
        "Residents shall provide accurate, complete, and truthful information when creating an account. Users are responsible for maintaining the confidentiality of their login credentials and for all activities performed through their accounts."),
    _Block.p(
        "Users shall not create accounts using false identities, impersonate another person, or use another person's account without authorization."),
  ]),
  _Section("2. Proper and Lawful Use", [
    _Block.p(
        "QRTeamTrack shall only be used for legitimate purposes related to:"),
    _Block.list([
      "Emergency reporting;",
      "Incident and public-safety reporting;",
      "Communication with QRT personnel;",
      "Viewing authorized QRT-related information; and",
      "Other services officially provided through the system.",
    ]),
    _Block.p(
        "Users shall not use the system for unlawful activities, harassment, fraud, unauthorized access, disruption of services, or any activity that may endanger QRT personnel, residents, or the public."),
  ]),
  _Section("3. False Reports, Pranks, and Misuse of Emergency Features", [
    _Block.p(
        "Users are strictly prohibited from submitting false, fabricated, misleading, malicious, or prank emergency reports or alarms."),
    _Block.p("This includes, but is not limited to:"),
    _Block.list([
      "Reporting an emergency that the user knows does not exist;",
      "Activating an emergency alarm as a joke or prank;",
      "Deliberately providing false information about an incident;",
      "Submitting fabricated photographs, locations, or other evidence;",
      "Repeatedly sending unnecessary or nuisance emergency alerts;",
      "Intentionally misleading QRT personnel regarding the nature, location, or severity of an incident; and",
      "Using the emergency-reporting feature to harass, threaten, embarrass, or inconvenience another person.",
    ]),
    _Block.p(
        "False or malicious reports may cause QRT personnel and emergency resources to be unnecessarily deployed and may delay assistance to persons experiencing genuine emergencies."),
    _Block.p(
        "Depending on the nature and circumstances of the act, such conduct may result in account suspension or removal and may be referred to the appropriate authorities for possible action under applicable Philippine laws."),
    _Block.p(
        "Where a false report involves a bomb, explosive, incendiary device, or similar destructive threat, Presidential Decree No. 1727 may apply. The decree specifically prohibits the malicious dissemination of false information or threats concerning bombs, explosives, and similar destructive devices."),
  ]),
  _Section("4. Compliance with Philippine Laws", [
    _Block.p(
        "Users agree to comply with all applicable Philippine laws and regulations when using QRTeamTrack."),
    _Block.p(
        "The system may involve the collection, storage, processing, and transmission of personal information and location information. Accordingly, applicable provisions of Republic Act No. 10173, or the Data Privacy Act of 2012, shall be observed in the processing and protection of personal information."),
    _Block.p(
        "Unauthorized access, interference with computer systems or data, computer-related fraud, identity-related offenses, or other applicable cyber-related activities may be subject to Republic Act No. 10175, or the Cybercrime Prevention Act of 2012, as amended."),
    _Block.p(
        "Acts that cause public disturbance or alarm may also be subject to applicable provisions of the Revised Penal Code (Act No. 3815), as amended, including provisions concerning alarms, scandals, and other offenses depending on the circumstances of the incident."),
    _Block.p(
        "QRTeamTrack does not replace or limit any existing emergency service, law-enforcement procedure, or legal requirement."),
  ]),
  _Section("5. Emergency Reporting", [
    _Block.p(
        "Residents should use the emergency alarm and incident-reporting features only when there is a genuine emergency or legitimate incident requiring QRT attention."),
    _Block.p(
        "When submitting a report, users should provide accurate information regarding:"),
    _Block.list([
      "Nature of the incident;",
      "Location;",
      "Date and approximate time;",
      "Description of the situation; and",
      "Other information reasonably necessary for QRT personnel to respond.",
    ]),
    _Block.p(
        "For life-threatening situations requiring immediate emergency assistance, users should also contact the appropriate emergency service, including the nationwide 911 emergency hotline, when appropriate. Executive Order No. 56 institutionalized Emergency 911 as the nationwide emergency answering point."),
  ]),
  _Section("6. Location and GPS Access", [
    _Block.p(
        "QRTeamTrack may request access to the user's device location when the user uses features that require location information, such as emergency alarms and incident reporting."),
    _Block.p(
        "Location information shall be used only for legitimate system purposes, including assisting QRT personnel in identifying the reported incident location and supporting emergency response."),
    _Block.p(
        "Users may be required to enable location services for features that depend on GPS functionality."),
  ]),
  _Section("7. Incident Reports and Uploaded Content", [
    _Block.p(
        "Users are responsible for the accuracy and lawfulness of information, photographs, videos, descriptions, and other content submitted through QRTeamTrack."),
    _Block.p("Users shall not upload or submit:"),
    _Block.list([
      "Fabricated or manipulated evidence intended to mislead QRT personnel;",
      "Content unrelated to the reported incident;",
      "Threatening or abusive content;",
      "Content intended to harass or identify a person maliciously;",
      "Illegal content; or",
      "Content that violates another person's privacy or applicable Philippine laws.",
    ]),
    _Block.p(
        "The administrators may restrict or remove content that violates these Terms and Conditions, subject to applicable law and proper procedures."),
  ]),
  _Section("8. Privacy and Protection of Personal Information", [
    _Block.p(
        "QRTeamTrack may collect personal information necessary for account registration, authentication, incident reporting, emergency response, system administration, and other legitimate system functions."),
    _Block.p(
        "Personal information shall be collected and processed in accordance with Republic Act No. 10173 (Data Privacy Act of 2012) and its implementing rules and regulations. The Data Privacy Act establishes requirements for the lawful and secure processing of personal information and recognizes the rights of data subjects."),
    _Block.p(
        "QRTeamTrack shall apply appropriate organizational, physical, and technical measures to protect personal information against unauthorized access, disclosure, alteration, loss, or other unlawful processing."),
    _Block.p(
        "Information collected through the system shall not be used for purposes unrelated to the legitimate functions of QRTeamTrack unless otherwise permitted or required by applicable law."),
  ]),
  _Section("9. Disclosure of Information", [
    _Block.p(
        "Personal information and incident information may be accessed or disclosed only to authorized QRT personnel, system administrators, or appropriate government authorities when necessary for legitimate emergency response, investigation, public-safety functions, legal compliance, or other purposes permitted by law."),
    _Block.p(
        "The system shall not intentionally disclose a user's personal information to unauthorized individuals."),
  ]),
  _Section("10. System Security and Unauthorized Access", [
    _Block.p("Users shall not attempt to:"),
    _Block.list([
      "Access administrative accounts or restricted system functions;",
      "Obtain another user's credentials;",
      "Modify, delete, or manipulate system records without authorization;",
      "Bypass authentication or security mechanisms;",
      "Interfere with the operation of the system;",
      "Introduce malicious software or harmful code; or",
      "Access, modify, or extract data without authorization.",
    ]),
    _Block.p(
        "Unauthorized activities involving computer systems or data may be subject to applicable provisions of Republic Act No. 10175 (Cybercrime Prevention Act of 2012) and other applicable laws."),
  ]),
  _Section("11. System Availability and Technical Limitations", [
    _Block.p(
        "QRTeamTrack relies on internet connectivity, GPS/location services, mobile devices, servers, and other technical infrastructure."),
    _Block.p(
        "The system may experience delays, interruptions, inaccurate GPS information, or temporary unavailability due to:"),
    _Block.list([
      "Poor or unavailable internet connection;",
      "GPS limitations;",
      "Device limitations;",
      "Server or network problems;",
      "Maintenance;",
      "Software or hardware failures; or",
      "Circumstances beyond the reasonable control of the administrators.",
    ]),
    _Block.p(
        "Users acknowledge that technical limitations may affect the delivery or processing of reports."),
  ]),
  _Section("12. Emergency Response Limitation", [
    _Block.p(
        "Submitting an emergency alarm or incident report through QRTeamTrack does not guarantee an immediate response."),
    _Block.p("Response time may depend on factors including:"),
    _Block.list([
      "Availability of QRT personnel;",
      "Distance and location of the incident;",
      "Number and severity of ongoing incidents;",
      "Traffic and environmental conditions;",
      "Communication and GPS availability; and",
      "Other emergency circumstances.",
    ]),
    _Block.p(
        "QRTeamTrack is intended to assist and support QRT operations and does not guarantee that every reported incident will result in an immediate deployment."),
  ]),
  _Section("13. Account Suspension, Restriction, or Removal", [
    _Block.p(
        "Administrators may temporarily suspend, restrict, or remove an account when there is reasonable basis to believe that the account has been used to:"),
    _Block.list([
      "Submit false or malicious reports;",
      "Conduct prank or nuisance emergency alarms;",
      "Repeatedly misuse system features;",
      "Violate these Terms and Conditions;",
      "Attempt unauthorized access;",
      "Compromise system security; or",
      "Engage in conduct that may endanger the public or QRT personnel.",
    ]),
    _Block.p(
        "Where appropriate, serious violations may be documented and referred to the appropriate authorities."),
  ]),
  _Section("14. User Cooperation During Investigations", [
    _Block.p(
        "Users agree to cooperate with authorized investigations concerning suspected misuse of QRTeamTrack, subject to applicable laws, regulations, and data privacy requirements."),
    _Block.p(
        "System records relevant to an incident may be retained and provided to authorized authorities when legally required or otherwise permitted by law."),
  ]),
  _Section("15. Respect for Public Safety", [
    _Block.p(
        "Users are expected to recognize that QRTeamTrack is a public-safety-oriented system. Emergency features must be treated seriously."),
    _Block.p(
        "Users shall not intentionally interfere with QRT operations, waste emergency resources, or cause unnecessary deployment of personnel through false or malicious reports."),
  ]),
  _Section("16. Terms and Conditions Updates", [
    _Block.p(
        "QRTeamTrack administrators may modify these Terms and Conditions when necessary to reflect changes in system functionality, operational procedures, applicable laws, regulations, or security requirements."),
    _Block.p(
        "Users may be notified of significant changes through the application or other appropriate communication channels."),
  ]),
  _Section("17. Acceptance of Terms", [
    _Block.p(
        "By creating an account, accessing, or using QRTeamTrack, the resident user confirms that they:"),
    _Block.list([
      "Have read and understood these Terms and Conditions;",
      "Agree to use the system responsibly and lawfully;",
      "Understand that false or malicious emergency reports are prohibited;",
      "Consent to the collection and processing of information necessary for the legitimate operation of the system, subject to applicable privacy laws; and",
      "Agree to comply with applicable Philippine laws and regulations.",
    ], numbered: true),
    _Block.p(
        "By selecting “I Agree,” registering an account, or continuing to use QRTeamTrack, the user acknowledges and accepts these Terms and Conditions."),
  ]),
];

/// Full-screen Terms and Conditions. Pops with `true` when the user taps
/// "I Agree"; the button unlocks once they have scrolled to the end.
class TermsAndConditionsPage extends StatefulWidget {
  const TermsAndConditionsPage({super.key});

  @override
  State<TermsAndConditionsPage> createState() => _TermsAndConditionsPageState();
}

class _TermsAndConditionsPageState extends State<TermsAndConditionsPage> {
  final ScrollController _scrollController = ScrollController();
  bool _reachedEnd = false;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_checkReachedEnd);
    // Short screens (e.g. tablets) may show everything without scrolling.
    WidgetsBinding.instance.addPostFrameCallback((_) => _checkReachedEnd());
  }

  void _checkReachedEnd() {
    if (_reachedEnd || !_scrollController.hasClients) return;
    final position = _scrollController.position;
    if (position.pixels >= position.maxScrollExtent - 40) {
      setState(() => _reachedEnd = true);
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  Widget _buildBlock(_Block block) {
    const bodyStyle = TextStyle(fontSize: 14, height: 1.5, color: Colors.black87);

    if (block.text != null) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Text(block.text!, style: bodyStyle, textAlign: TextAlign.justify),
      );
    }

    final items = block.items!;
    return Padding(
      padding: const EdgeInsets.only(left: 8, bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (var i = 0; i < items.length; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 22,
                    child: Text(block.numbered ? "${i + 1}." : "•",
                        style: bodyStyle),
                  ),
                  Expanded(child: Text(items[i], style: bodyStyle)),
                ],
              ),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Terms and Conditions"),
        backgroundColor: Colors.orange,
        foregroundColor: Colors.white,
      ),
      body: Scrollbar(
        controller: _scrollController,
        child: ListView(
          controller: _scrollController,
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 30),
          children: [
            const Text(
              _termsTitle,
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            _buildBlock(const _Block.p(_termsIntro)),
            for (final section in _sections) ...[
              const SizedBox(height: 10),
              Text(
                section.title,
                style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    color: Colors.orange),
              ),
              const SizedBox(height: 8),
              ...section.blocks.map(_buildBlock),
            ],
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
          decoration: const BoxDecoration(
            color: Colors.white,
            boxShadow: [BoxShadow(color: Colors.black12, blurRadius: 6)],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (!_reachedEnd)
                const Padding(
                  padding: EdgeInsets.only(bottom: 8),
                  child: Text(
                    "Please scroll to the end to accept.",
                    style: TextStyle(color: Colors.grey, fontSize: 12),
                  ),
                ),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(context, false),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.grey[700],
                        minimumSize: const Size.fromHeight(48),
                      ),
                      child: const Text("Decline"),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed:
                          _reachedEnd ? () => Navigator.pop(context, true) : null,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.orange,
                        foregroundColor: Colors.white,
                        minimumSize: const Size.fromHeight(48),
                      ),
                      child: const Text("I Agree",
                          style: TextStyle(fontWeight: FontWeight.bold)),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
