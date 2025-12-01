<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class UserController extends ResourceController
{
    use ResponseTrait;

    protected $model;
    protected $format = 'json';

    public function __construct()
    {
        $this->model = new UserModel();
    }

    // GET /users - Get all users
    public function index()
    {
        try {
            $users = $this->model->findAll();
            
            // Remove password from response for security
            $usersWithoutPassword = array_map(function($user) {
                unset($user['password']);
                unset($user['token']);
                return $user;
            }, $users);

            return $this->respond([
                'status' => 'success',
                'data' => $usersWithoutPassword,
                'total' => count($usersWithoutPassword)
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Failed to retrieve users: ' . $e->getMessage());
        }
    }

    // GET /users/{id} - Get single user
    public function show($id = null)
    {
        try {
            $user = $this->model->find($id);
            
            if ($user) {
                // Remove sensitive data
                unset($user['password']);
                unset($user['token']);
                
                return $this->respond([
                    'status' => 'success',
                    'data' => $user
                ]);
            }
            
            return $this->failNotFound('User not found with ID: ' . $id);
        } catch (\Exception $e) {
            return $this->failServerError('Database error: ' . $e->getMessage());
        }
    }

    // DELETE /users/{id} - Delete user
    public function delete($id = null)
    {
        // Check if user exists
        $user = $this->model->find($id);
        if (!$user) {
            return $this->failNotFound('User not found with ID: ' . $id);
        }

        try {
            if ($this->model->delete($id)) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'User deleted successfully',
                    'data' => ['id' => $id]
                ]);
            } else {
                return $this->failServerError('Failed to delete user');
            }
        } catch (\Exception $e) {
            return $this->failServerError('Database error: ' . $e->getMessage());
        }
    }
}