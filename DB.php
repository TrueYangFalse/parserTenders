<?php

class DB
{
    private $host = 'localhost';
    private $dbname = 'tender_db';
    private $username = 'root';
    private $password = '';
    private $db;

    public function __construct()
    {
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname;

        try {
            $this->db = new PDO($dsn, $this->username, $this->password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo 'Connection failed: ' . $e->getMessage();
        }
    }

    public function insertGame(array $tenders, $key)
    {
        $stmtCheck = $this->db->prepare("
        SELECT COUNT(*) FROM tender WHERE TenderNumber = :TenderNumber
    ");

        $stmtTender = $this->db->prepare("
        INSERT INTO tender (`TenderNumber`, `URL`, `Organization`, `RequestDate`)
        VALUES (:TenderNumber, :URL, :Organization, :RequestDate)
    ");

        $stmtDocument = $this->db->prepare("
        INSERT INTO document (`tender_id`, `name`, `link`) 
        VALUES (:tender_id, :name, :link)
    ");

        $stmtCheck->execute([
            ':TenderNumber' => $tenders[$key]['TenderNumber'],
        ]);

        $existingCount = $stmtCheck->fetchColumn();

        if ($existingCount > 0) {
            return;
        }

        $stmtTender->execute([
            ':TenderNumber' => $tenders[$key]['TenderNumber'],
            ':URL' => $tenders[$key]['URL'],
            ':Organization' => $tenders[$key]['Organization'],
            ':RequestDate' => $tenders[$key]['RequestDate'],
        ]);

        $tenderId = $this->db->lastInsertId();

        if (isset($tenders[$key]['Document'])) {
            foreach ($tenders[$key]['Document'] as $document) {
                $stmtDocument->execute([
                    ':tender_id' => $tenderId,
                    ':name' => $document['name'],
                    ':link' => $document['link'],
                ]);
            }
        }
    }
}