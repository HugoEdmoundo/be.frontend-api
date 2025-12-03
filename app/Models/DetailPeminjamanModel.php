<?php

namespace App\Models;

use CodeIgniter\Model;

class DetailPeminjamanModel extends Model
{
    protected $table = 'detail_peminjaman';
    protected $primaryKey = 'id';
    protected $allowedFields = ['peminjaman_id', 'buku_id', 'jumlah', 'tanggal_dikembalikan'];
    protected $useTimestamps = false;
    protected $validationRules = [
        'peminjaman_id' => 'required|numeric',
        'buku_id' => 'required|numeric',
        'jumlah' => 'required|numeric|greater_than[0]',
        'tanggal_dikembalikan' => 'permit_empty|valid_date'
    ];

    // Get detail dengan info buku
    public function getDetailWithBook($peminjaman_id)
    {
        $builder = $this->db->table('detail_peminjaman d');
        $builder->select('d.*, b.judul, b.penulis, b.stok as buku_stok')
                ->join('books b', 'b.id = d.buku_id')
                ->where('d.peminjaman_id', $peminjaman_id);
        
        return $builder->get()->getResultArray();
    }

    // Get total buku dipinjam
    public function getTotalDipinjam($buku_id)
    {
        $builder = $this->db->table('detail_peminjaman d');
        $builder->selectSum('d.jumlah')
                ->join('peminjaman p', 'p.id = d.peminjaman_id')
                ->where('d.buku_id', $buku_id)
                ->where('p.status', 'dipinjam');
        
        $result = $builder->get()->getRowArray();
        return $result ? $result['jumlah'] : 0;
    }

    // Update stok buku setelah peminjaman
    public function updateBookStock($buku_id, $jumlah, $operation = 'subtract')
    {
        $bookModel = new BookModel();
        $book = $bookModel->find($buku_id);
        
        if ($book) {
            if ($operation === 'subtract') {
                $newStock = $book['stok'] - $jumlah;
            } else {
                $newStock = $book['stok'] + $jumlah;
            }
            
            if ($newStock >= 0) {
                return $bookModel->update($buku_id, ['stok' => $newStock]);
            }
        }
        
        return false;
    }
}