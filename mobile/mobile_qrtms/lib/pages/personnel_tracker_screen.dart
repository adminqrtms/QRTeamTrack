import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../services/api_service.dart';
import 'dart:async';

class PersonnelTrackerScreen extends StatefulWidget {
  const PersonnelTrackerScreen({super.key});

  @override
  State<PersonnelTrackerScreen> createState() => _PersonnelTrackerScreenState();
}

class _PersonnelTrackerScreenState extends State<PersonnelTrackerScreen> {
  final List<Marker> _markers = [];
  Timer? _refreshTimer;
  bool _isLoading = true;
  final MapController _mapController = MapController();
  Marker? _sosMarker;
  LatLng? _targetLocation;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Extract SOS data if passed from the Emergency Dialog
    final args = ModalRoute.of(context)?.settings.arguments;
    if (args is Map<String, dynamic> && _targetLocation == null) {
      final lat = double.tryParse(args['latitude']?.toString() ?? '') ?? 0.0;
      final lng = double.tryParse(args['longitude']?.toString() ?? '') ?? 0.0;

      if (lat != 0.0 && lng != 0.0) {
        _targetLocation = LatLng(lat, lng);
        _sosMarker = Marker(
          point: _targetLocation!,
          width: 80,
          height: 80,
          child: Column(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                decoration: BoxDecoration(
                    color: Colors.red, borderRadius: BorderRadius.circular(4)),
                child: const Text("SOS HERE",
                    style: TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.bold)),
              ),
              const Icon(Icons.warning, color: Colors.red, size: 40),
            ],
          ),
        );
      }
    }
  }

  @override
  void initState() {
    super.initState();
    _fetchPersonnelLocations();
    // Periodically refresh locations every 10 seconds
    _refreshTimer = Timer.periodic(
      const Duration(seconds: 10),
      (_) => _fetchPersonnelLocations(),
    );
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _fetchPersonnelLocations() async {
    final personnelData = await ApiService.getActivePersonnel();

    if (!mounted) return;

    setState(() {
      _markers.clear();

      // Always keep the SOS marker on the map if it exists
      if (_sosMarker != null) {
        _markers.add(_sosMarker!);
      }

      for (var p in personnelData) {
        final lat =
            double.tryParse(p['last_latitude']?.toString() ?? '') ?? 0.0;
        final lng =
            double.tryParse(p['last_longitude']?.toString() ?? '') ?? 0.0;

        if (lat != 0.0 && lng != 0.0) {
          _markers.add(
            Marker(
              point: LatLng(lat, lng),
              width: 120,
              height: 60,
              child: Column(
                children: [
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(4),
                      boxShadow: const [
                        BoxShadow(color: Colors.black26, blurRadius: 4)
                      ],
                    ),
                    child: Text(
                      p['name'] ?? 'Unknown',
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: Colors.black,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const Icon(Icons.location_on, color: Colors.blue, size: 30),
                ],
              ),
            ),
          );
        }
      }
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Personnel GPS Tracker"),
        backgroundColor: Colors.blueGrey[900],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : FlutterMap(
              mapController: _mapController,
              options: MapOptions(
                initialCenter:
                    _targetLocation ?? const LatLng(7.210571, 124.241867),
                initialZoom: _targetLocation != null ? 16 : 13,
              ),
              children: [
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'com.example.mobile_qrtms',
                ),
                MarkerLayer(markers: _markers),
              ],
            ),
    );
  }
}
