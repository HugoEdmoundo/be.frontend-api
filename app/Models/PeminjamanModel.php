<?php

namespace App\Models;

use CodeIgniter\Model;

class PeminjamanModel extends Model
{
    protected $table = 'peminjaman';
    protected $primaryKey = 'id';
    protected $allowedFields = ['user_id', 'tanggal_pinjam', 'tanggal_kembali', 'status'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $validationRules = [
        'user_id' => 'required|numeric',
        'tanggal_pinjam' => 'required|valid_date',
        'tanggal_kembali' => 'required|valid_date',
        'status' => 'required|in_list[dipinjam,dikembalikan,terlambat]'
    ];

    // Get peminjaman dengan detail dan user
    public function getPeminjamanWithDetails($id = null)
    {
        $builder = $this->db->table('peminjaman p');
        $builder->select('p.*, u.name as anggota_nama, u.email')
                ->join('users u', 'u.id = p.user_id')
                ->orderBy('p.created_at', 'DESC');
        
        if ($id) {
            $builder->where('p.id', $id);
            return $builder->get()->getRowArray();
        }
        
        return $builder->get()->getResultArray();
    }

    // Get peminjaman by user
    public function getByUser($user_id)
    {
        return $this->where('user_id', $user_id)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }

    // Check if user has active peminjaman
    public function hasActivePeminjaman($user_id)
    {
        return $this->where([
            'user_id' => $user_id,
            'status' => 'dipinjam'
        ])->countAllResults() > 0;
    }
}