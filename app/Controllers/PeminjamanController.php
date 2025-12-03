<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class PeminjamanController extends ResourceController
{
    use ResponseTrait;

    // GET /peminjaman - Get all peminjaman
    public function index()
    {
        try {
            $db = \Config\Database::connect();
            
            // Query untuk get semua peminjaman dengan nama user
            $query = $db->query("
                SELECT 
                    p.*,
                    u.name as anggota_nama,
                    u.email as anggota_email
                FROM peminjaman p
                LEFT JOIN users u ON p.user_id = u.id
                ORDER BY p.created_at DESC
            ");
            
            $peminjaman = $query->getResultArray();
            
            // Get detail untuk setiap peminjaman
            foreach ($peminjaman as &$item) {
                $detailQuery = $db->query("
                    SELECT 
                        d.*,
                        b.judul,
                        b.penulis
                    FROM detail_peminjaman d
                    LEFT JOIN books b ON d.buku_id = b.id
                    WHERE d.peminjaman_id = ?
                ", [$item['id']]);
                
                $item['detail_buku'] = $detailQuery->getResultArray();
            }
            
            return $this->respond([
                'status' => 'success',
                'data' => $peminjaman,
                'total' => count($peminjaman)
            ]);
            
        } catch (\Exception $e) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Gagal mengambil data peminjaman: ' . $e->getMessage()
            ], 500);
        }
    }

    // GET /peminjaman/{id} - Get single peminjaman
    public function show($id = null)
    {
        try {
            $db = \Config\Database::connect();
            
            // Query untuk get peminjaman by ID
            $query = $db->query("
                SELECT 
                    p.*,
                    u.name as anggota_nama,
                    u.email as anggota_email
                FROM peminjaman p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.id = ?
            ", [$id]);
            
            $peminjaman = $query->getRowArray();
            
            if (!$peminjaman) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Peminjaman tidak ditemukan'
                ], 404);
            }
            
            // Get detail buku
            $detailQuery = $db->query("
                SELECT 
                    d.*,
                    b.judul,
                    b.penulis,
                    b.stok as buku_stok
                FROM detail_peminjaman d
                LEFT JOIN books b ON d.buku_id = b.id
                WHERE d.peminjaman_id = ?
            ", [$id]);
            
            $peminjaman['detail_buku'] = $detailQuery->getResultArray();
            
            return $this->respond([
                'status' => 'success',
                'data' => $peminjaman
            ]);
            
        } catch (\Exception $e) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // POST /peminjaman - Create new peminjaman
    public function create()
    {
        try {
            $json = $this->request->getJSON();
            
            if (!$json) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Data JSON tidak valid'
                ], 400);
            }

            $db = \Config\Database::connect();
            $db->transBegin();

            // Validasi data
            if (empty($json->user_id)) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'User ID wajib diisi'
                ], 400);
            }

            if (empty($json->buku) || !is_array($json->buku)) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Pilih minimal 1 buku'
                ], 400);
            }

            // Set tanggal
            $tanggal_pinjam = $json->tanggal_pinjam ?? date('Y-m-d');
            $tanggal_kembali = $json->tanggal_kembali ?? date('Y-m-d', strtotime('+7 days'));

            // Insert peminjaman
            $db->query("
                INSERT INTO peminjaman (user_id, tanggal_pinjam, tanggal_kembali, status) 
                VALUES (?, ?, ?, 'dipinjam')
            ", [$json->user_id, $tanggal_pinjam, $tanggal_kembali]);
            
            $peminjaman_id = $db->insertID();

            // Insert detail dan update stok
            foreach ($json->buku as $buku) {
                if (empty($buku->buku_id) || empty($buku->jumlah) || $buku->jumlah <= 0) {
                    continue;
                }

                // Cek stok tersedia
                $stockQuery = $db->query("
                    SELECT stok FROM books WHERE id = ?
                ", [$buku->buku_id]);
                
                $book = $stockQuery->getRowArray();
                
                if (!$book) {
                    $db->transRollback();
                    return $this->respond([
                        'status' => 'error',
                        'message' => 'Buku dengan ID ' . $buku->buku_id . ' tidak ditemukan'
                    ], 404);
                }

                if ($book['stok'] < $buku->jumlah) {
                    $db->transRollback();
                    return $this->respond([
                        'status' => 'error',
                        'message' => 'Stok tidak cukup untuk buku ID ' . $buku->buku_id
                    ], 400);
                }

                // Insert detail
                $db->query("
                    INSERT INTO detail_peminjaman (peminjaman_id, buku_id, jumlah) 
                    VALUES (?, ?, ?)
                ", [$peminjaman_id, $buku->buku_id, $buku->jumlah]);

                // Update stok
                $db->query("
                    UPDATE books SET stok = stok - ? WHERE id = ?
                ", [$buku->jumlah, $buku->buku_id]);
            }

            $db->transCommit();

            // Get data yang baru dibuat
            $newPeminjaman = $this->getPeminjamanData($peminjaman_id);

            return $this->respond([
                'status' => 'success',
                'message' => 'Peminjaman berhasil dibuat',
                'data' => $newPeminjaman
            ], 201);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->respond([
                'status' => 'error',
                'message' => 'Gagal membuat peminjaman: ' . $e->getMessage()
            ], 500);
        }
    }

    // PUT /peminjaman/{id}/kembalikan - Return books
    public function kembalikan($id = null)
    {
        try {
            $db = \Config\Database::connect();
            $db->transBegin();

            // Cek peminjaman
            $query = $db->query("SELECT * FROM peminjaman WHERE id = ?", [$id]);
            $peminjaman = $query->getRowArray();
            
            if (!$peminjaman) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Peminjaman tidak ditemukan'
                ], 404);
            }

            if ($peminjaman['status'] === 'dikembalikan') {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Buku sudah dikembalikan'
                ], 400);
            }

            $json = $this->request->getJSON();
            $tanggal_dikembalikan = $json->tanggal_dikembalikan ?? date('Y-m-d');

            // Update status
            $status = 'dikembalikan';
            if (strtotime($tanggal_dikembalikan) > strtotime($peminjaman['tanggal_kembali'])) {
                $status = 'terlambat';
            }

            $db->query("
                UPDATE peminjaman 
                SET status = ? 
                WHERE id = ?
            ", [$status, $id]);

            // Update detail dan kembalikan stok
            $detailQuery = $db->query("
                SELECT * FROM detail_peminjaman WHERE peminjaman_id = ?
            ", [$id]);
            
            $details = $detailQuery->getResultArray();
            
            foreach ($details as $detail) {
                // Update tanggal dikembalikan
                $db->query("
                    UPDATE detail_peminjaman 
                    SET tanggal_dikembalikan = ? 
                    WHERE id = ?
                ", [$tanggal_dikembalikan, $detail['id']]);

                // Kembalikan stok
                $db->query("
                    UPDATE books 
                    SET stok = stok + ? 
                    WHERE id = ?
                ", [$detail['jumlah'], $detail['buku_id']]);
            }

            $db->transCommit();

            // Get updated data
            $updatedPeminjaman = $this->getPeminjamanData($id);

            return $this->respond([
                'status' => 'success',
                'message' => 'Buku berhasil dikembalikan',
                'data' => $updatedPeminjaman
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->respond([
                'status' => 'error',
                'message' => 'Gagal mengembalikan buku: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper function untuk get data peminjaman
    private function getPeminjamanData($id)
    {
        $db = \Config\Database::connect();
        
        $query = $db->query("
            SELECT 
                p.*,
                u.name as anggota_nama,
                u.email as anggota_email
            FROM peminjaman p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ", [$id]);
        
        $peminjaman = $query->getRowArray();
        
        if ($peminjaman) {
            $detailQuery = $db->query("
                SELECT 
                    d.*,
                    b.judul,
                    b.penulis
                FROM detail_peminjaman d
                LEFT JOIN books b ON d.buku_id = b.id
                WHERE d.peminjaman_id = ?
            ", [$id]);
            
            $peminjaman['detail_buku'] = $detailQuery->getResultArray();
        }
        
        return $peminjaman;
    }

    // GET /peminjaman/user/{user_id} - Get peminjaman by user
    public function byUser($user_id = null)
    {
        try {
            $db = \Config\Database::connect();
            
            // Cek user exists
            $userQuery = $db->query("SELECT id, name, email FROM users WHERE id = ?", [$user_id]);
            $user = $userQuery->getRowArray();
            
            if (!$user) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'User tidak ditemukan'
                ], 404);
            }

            // Get peminjaman by user
            $query = $db->query("
                SELECT 
                    p.*,
                    u.name as anggota_nama,
                    u.email as anggota_email
                FROM peminjaman p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.user_id = ?
                ORDER BY p.created_at DESC
            ", [$user_id]);
            
            $peminjaman = $query->getResultArray();
            
            // Get details
            foreach ($peminjaman as &$item) {
                $detailQuery = $db->query("
                    SELECT 
                        d.*,
                        b.judul,
                        b.penulis
                    FROM detail_peminjaman d
                    LEFT JOIN books b ON d.buku_id = b.id
                    WHERE d.peminjaman_id = ?
                ", [$item['id']]);
                
                $item['detail_buku'] = $detailQuery->getResultArray();
            }

            return $this->respond([
                'status' => 'success',
                'data' => $peminjaman,
                'user' => $user
            ]);
            
        } catch (\Exception $e) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Gagal mengambil data peminjaman user: ' . $e->getMessage()
            ], 500);
        }
    }

    // GET /peminjaman/available-books - Get books available for borrowing
    public function availableBooks()
    {
        try {
            $db = \Config\Database::connect();
            
            // Get all books
            $query = $db->query("SELECT * FROM books");
            $books = $query->getResultArray();
            
            // Calculate available stock (total stock - borrowed)
            foreach ($books as &$book) {
                $borrowedQuery = $db->query("
                    SELECT COALESCE(SUM(d.jumlah), 0) as total_dipinjam
                    FROM detail_peminjaman d
                    JOIN peminjaman p ON d.peminjaman_id = p.id
                    WHERE d.buku_id = ? AND p.status = 'dipinjam'
                ", [$book['id']]);
                
                $borrowed = $borrowedQuery->getRowArray();
                $book['tersedia'] = $book['stok'] - ($borrowed['total_dipinjam'] ?? 0);
            }

            return $this->respond([
                'status' => 'success',
                'data' => $books,
                'total' => count($books)
            ]);
            
        } catch (\Exception $e) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Gagal mengambil data buku tersedia: ' . $e->getMessage()
            ], 500);
        }
    }
}