<?php

namespace App\Controllers;

use App\Models\SingleUserModel;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class SingleUserController extends ResourceController
{
    use ResponseTrait;

    protected $model;
    
    public function __construct()
    {
        $this->model = new SingleUserModel();
    }

    // GET /single-users - Get all users from 'user' table
    public function index()
    {
        try {
            $users = $this->model->getAllUsers();
            
            return $this->respond([
                'status' => 'success',
                'data' => $users,
                'total' => count($users),
                'table' => 'user'
            ]);
        } catch (\Exception $e) {
            return $this->fail('Failed to get users: ' . $e->getMessage(), 500);
        }
    }

    // GET /single-users/{id} - Get single user
    public function show($id = null)
    {
        try {
            $user = $this->model->find($id);
            
            if ($user) {
                return $this->respond([
                    'status' => 'success',
                    'data' => $user
                ]);
            }
            
            return $this->fail('User not found with ID: ' . $id, 404);
        } catch (\Exception $e) {
            return $this->fail('Database error: ' . $e->getMessage(), 500);
        }
    }

    // POST /single-users - Create new user (NO password hashing)
    public function create()
    {
        try {
            $json = $this->request->getJSON();
            
            if ($json) {
                $data = [
                    'username' => $json->username ?? null,
                    'password' => $json->password ?? null // Password stored as plain text
                ];
            } else {
                $data = [
                    'username' => $this->request->getPost('username'),
                    'password' => $this->request->getPost('password')
                ];
            }

            // Validation
            if (empty($data['username']) || empty($data['password'])) {
                return $this->fail('Username and password are required', 400);
            }

            // Check if username already exists
            if ($this->model->getUserByUsername($data['username'])) {
                return $this->fail('Username already exists', 409);
            }

            // Save user (password NOT hashed)
            if ($this->model->save($data)) {
                return $this->respondCreated([
                    'status' => 'success',
                    'message' => 'User created successfully',
                    'data' => [
                        'id' => $this->model->getInsertID(),
                        'username' => $data['username']
                    ]
                ]);
            } else {
                return $this->fail('Failed to create user: ' . implode(', ', $this->model->errors()), 400);
            }

        } catch (\Exception $e) {
            return $this->fail('Server error: ' . $e->getMessage(), 500);
        }
    }

    // POST /single-users/login - Login for 'user' table
    public function login()
    {
        try {
            $json = $this->request->getJSON();
            
            if ($json) {
                $username = $json->username ?? null;
                $password = $json->password ?? null;
            } else {
                $username = $this->request->getPost('username');
                $password = $this->request->getPost('password');
            }

            // Validation
            if (empty($username) || empty($password)) {
                return $this->fail('Username and password are required', 400);
            }

            // Get user
            $user = $this->model->getUserByUsername($username);

            if (!$user) {
                return $this->fail('User not found', 404);
            }

            // Compare passwords (plain text comparison)
            if ($password !== $user['password']) {
                return $this->fail('Invalid password', 401);
            }

            // Generate token
            $token = bin2hex(random_bytes(32));
            
            return $this->respond([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username']
                    ],
                    'token' => $token,
                    'table' => 'user'
                ]
            ]);

        } catch (\Exception $e) {
            return $this->fail('Server error: ' . $e->getMessage(), 500);
        }
    }

    // PUT /single-users/{id} - Update user
    public function update($id = null)
    {
        try {
            $user = $this->model->find($id);
            if (!$user) {
                return $this->fail('User not found with ID: ' . $id, 404);
            }

            $json = $this->request->getJSON();
            $data = [];
            
            if ($json) {
                if (isset($json->username)) $data['username'] = $json->username;
                if (isset($json->password)) $data['password'] = $json->password; // Plain text
            } else {
                if ($this->request->getPost('username')) $data['username'] = $this->request->getPost('username');
                if ($this->request->getPost('password')) $data['password'] = $this->request->getPost('password');
            }

            // Check if new username already exists (excluding current user)
            if (isset($data['username']) && $data['username'] !== $user['username']) {
                $existing = $this->model->getUserByUsername($data['username']);
                if ($existing) {
                    return $this->fail('Username already exists', 409);
                }
            }

            if (!empty($data) && $this->model->update($id, $data)) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'User updated successfully',
                    'data' => [
                        'id' => $id,
                        'username' => $data['username'] ?? $user['username']
                    ]
                ]);
            } else {
                return $this->fail('No changes made or update failed', 400);
            }

        } catch (\Exception $e) {
            return $this->fail('Server error: ' . $e->getMessage(), 500);
        }
    }

    // DELETE /single-users/{id} - Delete user
    public function delete($id = null)
    {
        try {
            $user = $this->model->find($id);
            if (!$user) {
                return $this->fail('User not found with ID: ' . $id, 404);
            }

            if ($this->model->delete($id)) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'User deleted successfully',
                    'data' => ['id' => $id]
                ]);
            } else {
                return $this->fail('Failed to delete user', 500);
            }

        } catch (\Exception $e) {
            return $this->fail('Server error: ' . $e->getMessage(), 500);
        }
    }
}