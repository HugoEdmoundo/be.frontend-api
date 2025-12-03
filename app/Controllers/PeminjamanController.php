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
            
            // Query yang diperbaiki - tanpa ORDER BY created_at jika kolom tidak ada
            $query = $db->query("
                SELECT 
                    p.*,
                    u.name as anggota_nama,
                    u.email as anggota_email
                FROM peminjaman p
                LEFT JOIN users u ON p.users_id = u.id
                ORDER BY p.id DESC  -- Ganti dengan ORDER BY id atau hapus jika tidak perlu
            ");
            
            $peminjaman = $query->getResultArray();
            
            // Debug: lihat struktur data yang diambil
            // log_message('debug', 'Peminjaman data: ' . json_encode($peminjaman));
            
            // Get detail untuk setiap peminjaman
            foreach ($peminjaman as &$item) {
                $detailQuery = $db->query("
                    SELECT 
                        d.*,
                        b.title as judul_buku,
                        b.author as penulis_buku,
                        b.quantity as stok_buku
                    FROM detail_peminjaman d
                    LEFT JOIN books b ON d.books_id = b.id
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
            // Log error dengan detail
            log_message('error', 'Error in PeminjamanController::index: ' . $e->getMessage());
            log_message('error', 'Error trace: ' . $e->getTraceAsString());
            
            return $this->respond([
                'status' => 'error',
                'message' => 'Gagal mengambil data peminjaman: ' . $e->getMessage(),
                'error_detail' => $e->getMessage()
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
            LEFT JOIN users u ON p.users_id = u.id
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
                b.title as judul,
                b.author as penulis,
                b.quantity as stok_buku
            FROM detail_peminjaman d
            LEFT JOIN books b ON d.books_id = b.id
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
            // Ambil data dari request
            $json = $this->request->getJSON();
            
            // Debug: log data yang diterima
            log_message('debug', 'Received data for peminjaman: ' . json_encode($json));
            
            if (!$json) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Data JSON tidak valid'
                ], 400);
            }

            $db = \Config\Database::connect();
            $db->transBegin();

            // Debug: cek apakah data lengkap
            log_message('debug', 'users_id: ' . ($json->users_id ?? 'not set'));
            log_message('debug', 'buku count: ' . (is_array($json->buku) ? count($json->buku) : 'not array'));

            // Validasi data
            if (empty($json->users_id) && empty($json->user_id)) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'User ID wajib diisi',
                    'received_data' => $json // Untuk debugging
                ], 400);
            }

            
            $userId = $json->users_id ?? $json->user_id ?? null;

            $userQuery = $db->query("SELECT id, name FROM users WHERE id = ?", [$userId]);
            $user = $userQuery->getRowArray();
            
            if (!$user) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'User dengan ID ' . $userId . ' tidak ditemukan'
                ], 404);
            }

            // Validasi data buku
            if (empty($json->buku) || !is_array($json->buku) || count($json->buku) === 0) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Pilih minimal 1 buku. Data buku: ' . json_encode($json->buku)
                ], 400);
            }

            // Validasi setiap buku
            foreach ($json->buku as $index => $buku) {
                if (empty($buku->buku_id)) {
                    return $this->respond([
                        'status' => 'error',
                        'message' => 'Buku ke-' . ($index + 1) . ' tidak memiliki ID'
                    ], 400);
                }
                
                if (empty($buku->jumlah) || $buku->jumlah <= 0) {
                    return $this->respond([
                        'status' => 'error',
                        'message' => 'Jumlah buku ke-' . ($index + 1) . ' harus lebih dari 0'
                    ], 400);
                }
            }

            // Set tanggal
            $tanggal_pinjam = $json->tanggal_pinjam ?? date('Y-m-d');
            $tanggal_kembali = $json->tanggal_kembali ?? date('Y-m-d', strtotime('+7 days'));

            // Insert peminjaman - gunakan field yang sesuai dengan database
            $db->query("
                INSERT INTO peminjaman (users_id, tanggal_pinjam, tanggal_kembali, status) 
                VALUES (?, ?, ?, 'dipinjam')
            ", [$userId, $tanggal_pinjam, $tanggal_kembali]);
            
            $peminjaman_id = $db->insertID();
            
            log_message('debug', 'Created peminjaman ID: ' . $peminjaman_id);

            // Insert detail dan update stok
            foreach ($json->buku as $buku) {
                $bukuId = $buku->buku_id;
                $jumlah = $buku->jumlah;

                // Cek apakah buku exists
                $bookQuery = $db->query("SELECT id, title, quantity FROM books WHERE id = ?", [$bukuId]);
                $book = $bookQuery->getRowArray();
                
                if (!$book) {
                    $db->transRollback();
                    return $this->respond([
                        'status' => 'error',
                        'message' => 'Buku dengan ID ' . $bukuId . ' tidak ditemukan'
                    ], 404);
                }

                // Cek stok tersedia
                if ($book['quantity'] < $jumlah) {
                    $db->transRollback();
                    return $this->respond([
                        'status' => 'error',
                        'message' => 'Stok tidak cukup untuk buku "' . $book['title'] . '". 
                                    Stok tersedia: ' . $book['quantity'] . ', Diminta: ' . $jumlah
                    ], 400);
                }

                log_message('debug', 'Processing book ID ' . $bukuId . ', quantity: ' . $jumlah);

                // Insert detail peminjaman
                $db->query("
                    INSERT INTO detail_peminjaman (peminjaman_id, books_id, jumlah) 
                    VALUES (?, ?, ?)
                ", [$peminjaman_id, $bukuId, $jumlah]);

                // Update stok buku
                $db->query("
                    UPDATE books 
                    SET quantity = quantity - ? 
                    WHERE id = ?
                ", [$jumlah, $bukuId]);
                
                log_message('debug', 'Updated stock for book ID ' . $bukuId . ', reduced by ' . $jumlah);
            }

            $db->transCommit();

            // Get data yang baru dibuat
            $newPeminjaman = $this->getPeminjamanData($peminjaman_id);

            return $this->respond([
                'status' => 'success',
                'message' => 'Peminjaman berhasil dibuat untuk ' . $user['name'],
                'data' => $newPeminjaman,
                'peminjaman_id' => $peminjaman_id
            ], 201);

        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', 'Error creating peminjaman: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            
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
                    'message' => 'Books sudah dikembalikan'
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
                ", [$detail['jumlah'], $detail['books_id']]);
            }

            $db->transCommit();

            // Get updated data
            $updatedPeminjaman = $this->getPeminjamanData($id);

            return $this->respond([
                'status' => 'success',
                'message' => 'Books berhasil dikembalikan',
                'data' => $updatedPeminjaman
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->respond([
                'status' => 'error',
                'message' => 'Gagal mengembalikan books: ' . $e->getMessage()
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
            LEFT JOIN users u ON p.users_id = u.id
            WHERE p.id = ?
        ", [$id]);
        
        $peminjaman = $query->getRowArray();
        
        if ($peminjaman) {
            $detailQuery = $db->query("
                SELECT 
                    d.*,
                    b.title as judul,
                    b.author as penulis,
                    b.quantity as stok_buku
                FROM detail_peminjaman d
                LEFT JOIN books b ON d.books_id = b.id
                WHERE d.peminjaman_id = ?
            ", [$id]);
            
            $peminjaman['detail_buku'] = $detailQuery->getResultArray();
        }
        
        return $peminjaman;
    }

    // Juga perbaiki method show(), kembalikan(), byUser(), dll

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
                LEFT JOIN users u ON p.users_id = u.id
                WHERE p.users_id = ?
                ORDER BY p.id DESC  // Ganti dengan field yang ada
            ", [$user_id]);
            
            $peminjaman = $query->getResultArray();
            
            // Get details
            foreach ($peminjaman as &$item) {
                $detailQuery = $db->query("
                    SELECT 
                        d.*,
                        b.title as judul,
                        b.author as penulis,
                        b.quantity as stok_buku
                    FROM detail_peminjaman d
                    LEFT JOIN books b ON d.books_id = b.id
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
                    WHERE d.books_id = ? AND p.status = 'dipinjam'
                ", [$book['id']]);
                
                $borrowed = $borrowedQuery->getRowArray();
                $book['tersedia'] = $book['quantity'] - ($borrowed['total_dipinjam'] ?? 0);
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