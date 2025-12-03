<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class BooksController extends ResourceController
{
    use ResponseTrait;

    // GET /books - Get all books
    public function index()
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->query("SELECT * FROM books ORDER BY id DESC");
            $books = $query->getResultArray();
            
            return $this->respond([
                'status' => 'success',
                'data' => $books,
                'total' => count($books)
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Gagal mengambil data buku: ' . $e->getMessage()
            ], 500);
        }
    }

    // GET /books/{id} - Get single book
    public function show($id = null)
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->query("SELECT * FROM books WHERE id = ?", [$id]);
            $book = $query->getRowArray();
            
            if ($book) {
                return $this->respond([
                    'status' => 'success',
                    'data' => $book
                ]);
            }
            
            return $this->respond([
                'status' => 'error',
                'message' => 'Buku tidak ditemukan dengan ID: ' . $id
            ], 404);
        } catch (\Exception $e) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Error database: ' . $e->getMessage()
            ], 500);
        }
    }

    // POST /books - Create new book
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

            // Validation
            if (empty($json->title) || empty($json->author)) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Title dan author wajib diisi'
                ], 400);
            }

            $quantity = $json->quantity ?? 0;
            if ($quantity < 0) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Quantity tidak boleh negatif'
                ], 400);
            }

            // Insert book
            $db->query("
                INSERT INTO books (title, author, publisher, year_published, isbn, quantity) 
                VALUES (?, ?, ?, ?, ?, ?)
            ", [
                $json->title,
                $json->author,
                $json->publisher ?? null,
                $json->year_published ?? null,
                $json->isbn ?? null,
                $quantity
            ]);
            
            $book_id = $db->insertID();

            // Get the new book
            $query = $db->query("SELECT * FROM books WHERE id = ?", [$book_id]);
            $newBook = $query->getRowArray();

            return $this->respond([
                'status' => 'success',
                'message' => 'Buku berhasil ditambahkan',
                'data' => $newBook
            ], 201);

        } catch (\Exception $e) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Gagal menambah buku: ' . $e->getMessage()
            ], 500);
        }
    }

    // PUT /books/{id} - Update book
    public function update($id = null)
    {
        try {
            $db = \Config\Database::connect();
            
            // Check if book exists
            $query = $db->query("SELECT * FROM books WHERE id = ?", [$id]);
            $existingBook = $query->getRowArray();
            
            if (!$existingBook) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Buku tidak ditemukan dengan ID: ' . $id
                ], 404);
            }

            $json = $this->request->getJSON();
            
            // Build update data
            $updates = [];
            $params = [];
            
            if (isset($json->title)) {
                $updates[] = "title = ?";
                $params[] = $json->title;
            }
            
            if (isset($json->author)) {
                $updates[] = "author = ?";
                $params[] = $json->author;
            }
            
            if (isset($json->publisher)) {
                $updates[] = "publisher = ?";
                $params[] = $json->publisher;
            }
            
            if (isset($json->year_published)) {
                $updates[] = "year_published = ?";
                $params[] = $json->year_published;
            }
            
            if (isset($json->isbn)) {
                $updates[] = "isbn = ?";
                $params[] = $json->isbn;
            }
            
            if (isset($json->quantity)) {
                if ($json->quantity < 0) {
                    return $this->respond([
                        'status' => 'error',
                        'message' => 'Quantity tidak boleh negatif'
                    ], 400);
                }
                $updates[] = "quantity = ?";
                $params[] = $json->quantity;
            }
            
            if (empty($updates)) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Tidak ada data yang diupdate'
                ], 400);
            }
            
            $params[] = $id;
            
            // Update book
            $sql = "UPDATE books SET " . implode(", ", $updates) . " WHERE id = ?";
            $db->query($sql, $params);

            // Get updated book
            $query = $db->query("SELECT * FROM books WHERE id = ?", [$id]);
            $updatedBook = $query->getRowArray();

            return $this->respond([
                'status' => 'success',
                'message' => 'Buku berhasil diperbarui',
                'data' => $updatedBook
            ]);

        } catch (\Exception $e) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Gagal memperbarui buku: ' . $e->getMessage()
            ], 500);
        }
    }

    // DELETE /books/{id} - Delete book
    public function delete($id = null)
    {
        try {
            $db = \Config\Database::connect();
            
            // Check if book exists
            $query = $db->query("SELECT * FROM books WHERE id = ?", [$id]);
            $book = $query->getRowArray();
            
            if (!$book) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Buku tidak ditemukan dengan ID: ' . $id
                ], 404);
            }

            // Check if book is being borrowed
            $borrowQuery = $db->query("
                SELECT COUNT(*) as total FROM detail_peminjaman 
                WHERE buku_id = ? AND peminjaman_id IN (
                    SELECT id FROM peminjaman WHERE status = 'dipinjam'
                )
            ", [$id]);
            
            $borrowCount = $borrowQuery->getRowArray()['total'] ?? 0;
            
            if ($borrowCount > 0) {
                return $this->respond([
                    'status' => 'error',
                    'message' => 'Buku tidak dapat dihapus karena masih dipinjam'
                ], 400);
            }

            // Delete book
            $db->query("DELETE FROM books WHERE id = ?", [$id]);

            return $this->respond([
                'status' => 'success',
                'message' => 'Buku berhasil dihapus',
                'data' => ['id' => $id]
            ]);

        } catch (\Exception $e) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Error database: ' . $e->getMessage()
            ], 500);
        }
    }
}