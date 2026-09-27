// // import 'package:google_maps_flutter/google_maps_flutter.dart';
// import '../services/api_service.dart';

// class TrackerController {
//   // Fetches active personnel and converts them to Map Markers
//   Future<Set<Marker>> getPersonnelMarkers() async {
//     final List<dynamic> personnelList = await ApiService.getActivePersonnel();
//     Set<Marker> markers = {};

//     for (var person in personnelList) {
//       markers.add(
//         Marker(
//           markerId: MarkerId(person['id'].toString()),
//           position: LatLng(
//             double.parse(person['last_latitude'].toString()),
//             double.parse(person['last_longitude'].toString()),
//           ),
//           infoWindow: InfoWindow(
//             title: person['name'],
//             snippet: "Last seen: ${person['phone_number'] ?? 'No contact'}",
//           ),
//           icon: BitmapDescriptor.defaultMarkerWithHue(
//             BitmapDescriptor.hueAzure,
//           ),
//         ),
//       );
//     }

//     return markers;
//   }
// }
