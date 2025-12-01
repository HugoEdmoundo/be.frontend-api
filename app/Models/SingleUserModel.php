<?php

namespace App\Models;

use CodeIgniter\Model;

class SingleUserModel extends Model
{
    protected $table = 'user'; // Table name: user (single)
    protected $primaryKey = 'id';
    protected $allowedFields = ['username', 'password'];
    protected $useTimestamps = false; // No timestamps
    protected $validationRules = [
        'username' => 'required|min_length[3]|max_length[100]|is_unique[user.username]',
        'password' => 'required|min_length[3]'
    ];
    
    // NO password hashing here!
    
    public function getUserByUsername($username)
    {
        return $this->where('username', $username)->first();
    }
    
    public function getAllUsers()
    {
        return $this->findAll();
    }
}