<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class AuthController extends ResourceController
{
    use ResponseTrait;

    protected $model;

    public function __construct()
    {
        $this->model = new UserModel();
        helper(['form', 'url']);
    }

    public function register()
    {
        // Get JSON input
        $json = $this->request->getJSON();
        
        // Debug: log input data
        log_message('debug', 'Register input: ' . print_r($json, true));
        
        if ($json) {
            $data = [
                'name' => $json->name ?? null,
                'email' => $json->email ?? null,
                'password' => $json->password ?? null
            ];
        } else {
            $data = [
                'name' => $this->request->getPost('name'),
                'email' => $this->request->getPost('email'),
                'password' => $this->request->getPost('password')
            ];
        }

        // Validation
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return $this->fail('All fields are required', 400);
        }

        // Email format validation
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Invalid email format', 400);
        }

        // Check if email already exists
        if ($this->model->getUserByEmail($data['email'])) {
            return $this->fail('Email already exists', 409);
        }

        try {
            // Create user
            if ($this->model->save($data)) {
                return $this->respondCreated([
                    'status' => 'success',
                    'message' => 'User registered successfully',
                    'data' => [
                        'user_id' => $this->model->getInsertID(),
                        'name' => $data['name'],
                        'email' => $data['email']
                    ]
                ]);
            } else {
                $errors = $this->model->errors();
                return $this->fail('Registration failed: ' . implode(', ', $errors), 400);
            }
        } catch (\Exception $e) {
            log_message('error', 'Register exception: ' . $e->getMessage());
            return $this->fail('Server error: ' . $e->getMessage(), 500);
        }
    }

    public function login()
    {
        // Get JSON input
        $json = $this->request->getJSON();
        
        if ($json) {
            $email = $json->email ?? null;
            $password = $json->password ?? null;
        } else {
            $email = $this->request->getPost('email');
            $password = $this->request->getPost('password');
        }

        // Validation
        if (empty($email) || empty($password)) {
            return $this->fail('Email and password are required', 400);
        }

        try {
            $user = $this->model->getUserByEmail($email);

            if (!$user) {
                return $this->fail('User not found', 404);
            }

            // Verify password
            if (!password_verify($password, $user['password'])) {
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
                        'name' => $user['name'],
                        'email' => $user['email']
                    ],
                    'token' => $token
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Login exception: ' . $e->getMessage());
            return $this->fail('Server error: ' . $e->getMessage(), 500);
        }
    }
}