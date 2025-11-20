<?php

namespace App\Controllers;

use App\Models\BookModel;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class BooksController extends ResourceController
{
    use ResponseTrait;

    protected $model;

    public function __construct()
    {
        $this->model = new BookModel();
    }

    public function index()
    {
        $books = $this->model->findAll();
        return $this->respond($books);
    }

    public function show($id = null)
    {
        $book = $this->model->find($id);
        if ($book) {
            return $this->respond($book);
        }
        return $this->failNotFound('Book not found');
    }

    public function create()
    {
        $data = $this->request->getJSON(true);
        
        if ($this->model->save($data)) {
            return $this->respondCreated([
                'status' => 'success',
                'message' => 'Book created successfully',
                'data' => $data
            ]);
        }
        
        return $this->fail($this->model->errors());
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);
        
        if ($this->model->update($id, $data)) {
            return $this->respond([
                'status' => 'success',
                'message' => 'Book updated successfully'
            ]);
        }
        
        return $this->fail($this->model->errors());
    }

    public function delete($id = null)
    {
        if ($this->model->delete($id)) {
            return $this->respondDeleted([
                'status' => 'success',
                'message' => 'Book deleted successfully'
            ]);
        }
        
        return $this->failNotFound('Book not found');
    }
}