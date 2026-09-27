import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../services/api_service.dart';

class ManageAccountPage extends StatefulWidget {
  const ManageAccountPage({super.key});

  @override
  _ManageAccountPageState createState() => _ManageAccountPageState();
}

class _ManageAccountPageState extends State<ManageAccountPage> {
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _phoneController = TextEditingController();
  final _addressController = TextEditingController();

  File? _avatar;
  String? _currentAvatar;
  String? _selectedLocationId;
  List<dynamic> _locations = [];
  final ImagePicker _picker = ImagePicker();
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _loadUser();
    if (ApiService.role == 'resident') {
      _fetchLocations();
    }
  }

  Future<void> _fetchLocations() async {
    var data = await ApiService.getLocations();
    if (mounted) {
      setState(() {
        _locations = data;
      });
    }
  }

  Future<void> _loadUser() async {
    setState(() => _isLoading = true);
    var res = await ApiService.getUser();
    if (res.containsKey('id') ||
        res.containsKey('name') ||
        res.containsKey('data')) {
      var user = res.containsKey('data') ? res['data'] : res;
      _nameController.text = user['name'] ?? '';
      _emailController.text = user['email'] ?? '';
      _phoneController.text = user['phone_number'] ?? '';
      _addressController.text = user['address'] ?? '';
      _currentAvatar = user['avatar'];
      _selectedLocationId = user['location_id']?.toString();
    }
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _pickImage() async {
    final XFile? image = await _picker.pickImage(source: ImageSource.gallery);
    if (image != null) {
      setState(() {
        _avatar = File(image.path);
      });
    }
  }

  Future<void> _updateProfile() async {
    setState(() => _isLoading = true);
    var res = await ApiService.updateProfile(
      name: _nameController.text,
      email: _emailController.text,
      password: _passwordController.text,
      avatar: _avatar,
      phoneNumber: _phoneController.text,
      address: _addressController.text,
      locationId: _selectedLocationId,
    );

    if (mounted) {
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message'] ?? 'Profile Updated')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text("Manage Account"),
        backgroundColor: Colors.blue,
      ),
      body: _isLoading
          ? Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: EdgeInsets.all(16),
              child: Column(
                children: [
                  GestureDetector(
                    onTap: _pickImage,
                    child: CircleAvatar(
                      radius: 50,
                      backgroundColor: Colors.grey[300],
                      backgroundImage: _avatar != null
                          ? FileImage(_avatar!)
                          : (_currentAvatar != null
                                    ? NetworkImage(
                                        "http://192.168.68.110:8000/storage/$_currentAvatar",
                                      )
                                    : null)
                                as ImageProvider?,
                      child: (_avatar == null && _currentAvatar == null)
                          ? Icon(
                              Icons.camera_alt,
                              size: 40,
                              color: Colors.grey[700],
                            )
                          : null,
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    "Tap to change profile picture",
                    style: TextStyle(color: Colors.grey, fontSize: 12),
                  ),
                  const SizedBox(height: 30),
                  TextField(
                    controller: _nameController,
                    decoration: InputDecoration(
                      labelText: "Name",
                      border: OutlineInputBorder(),
                    ),
                  ),
                  SizedBox(height: 10),
                  TextField(
                    controller: _phoneController,
                    decoration: InputDecoration(
                      labelText: "Phone Number",
                      border: OutlineInputBorder(),
                    ),
                  ),
                  SizedBox(height: 10),
                  TextField(
                    controller: _addressController,
                    decoration: InputDecoration(
                      labelText: "Address",
                      border: OutlineInputBorder(),
                    ),
                  ),
                  SizedBox(height: 10),
                  if (ApiService.role == 'resident')
                    DropdownButtonFormField<String>(
                      initialValue: _selectedLocationId,
                      decoration: InputDecoration(
                        labelText: 'Your Location',
                        border: OutlineInputBorder(),
                      ),
                      items: _locations.map((loc) {
                        return DropdownMenuItem<String>(
                          value: loc['id'].toString(),
                          child: Text(loc['location_name']),
                        );
                      }).toList(),
                      onChanged: (value) {
                        setState(() => _selectedLocationId = value);
                      },
                    ),
                  if (ApiService.role == 'resident') SizedBox(height: 10),
                  TextField(
                    controller: _emailController,
                    decoration: InputDecoration(
                      labelText: "Email",
                      border: OutlineInputBorder(),
                    ),
                  ),
                  SizedBox(height: 10),
                  TextField(
                    controller: _passwordController,
                    decoration: InputDecoration(
                      labelText: "New Password (optional)",
                      border: OutlineInputBorder(),
                      helperText: "Leave blank to keep current password",
                    ),
                    obscureText: true,
                  ),
                  SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: _updateProfile,
                      style: ElevatedButton.styleFrom(
                        padding: EdgeInsets.symmetric(vertical: 15),
                        backgroundColor: Colors.blue,
                      ),
                      child: Text(
                        "Update Profile",
                        style: TextStyle(color: Colors.white, fontSize: 16),
                      ),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
