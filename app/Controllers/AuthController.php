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
        $data = [
            'name' => $json->name ?? $this->request->getPost('name'),
            'email' => $json->email ?? $this->request->getPost('email'),
            'password' => $json->password ?? $this->request->getPost('password')
        ];

        // Validation
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return $this->failValidationErrors('All fields are required');
        }

        // Check if email already exists
        if ($this->model->getUserByEmail($data['email'])) {
            return $this->fail('Email already exists', 409);
        }

        // Create user
        if ($this->model->save($data)) {
            return $this->respondCreated([
                'status' => 'success',
                'message' => 'User registered successfully',
                'data' => [
                    'user_id' => $this->model->getInsertID()
                ]
            ]);
        }

        return $this->fail('Registration failed', 500);
    }

    public function login()
    {
        // Get JSON input
        $json = $this->request->getJSON();
        $email = $json->email ?? $this->request->getPost('email');
        $password = $json->password ?? $this->request->getPost('password');

        // Validation
        if (empty($email) || empty($password)) {
            return $this->failValidationErrors('Email and password are required');
        }

        $user = $this->model->getUserByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            return $this->fail('Invalid email or password', 401);
        }

        // Simple token simulation
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
    }
}