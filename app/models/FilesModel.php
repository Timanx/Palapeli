<?php

namespace App\Models;

use App\Presenters\BasePresenter;
use Nette;

class FilesModel
{

    /** @var Nette\Database\Context */
    private $database;

    private $year;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function setYear($year)
    {
        $this->year = $year;
    }

    public function getFileId($path, $filename)
    {
        return $this->database->query('
            SELECT id
                FROM files
                WHERE path = ? AND name = ?
            ', $path, $filename
        )->fetchField();
    }

    public function insertFile($path, $filename)
    {
        $this->database->beginTransaction();

        $id = $this->getFileId($path, $filename);

        if(!$id) {
            $this->database->query('
                INSERT INTO files (path, name) VALUES (?, ?)
                ', $path, $filename
            );

            $id = $this->database->getInsertId();
        }

        $this->database->commit();
        return $id;
    }
}