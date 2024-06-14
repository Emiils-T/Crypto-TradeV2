<?php
namespace App;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;

class Database
{
    private $connectionParams;
    private $filePath;
    private $dbalConnection;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;

        $this->connectionParams = array(
            'driver' => 'pdo_sqlite',
            'path' => $this->filePath,
        );
        $this->dbalConnection = DriverManager::getConnection($this->connectionParams);
    }

    public function createDatabase():void
    {
        $dbalConnection = DriverManager::getConnection($this->connectionParams);

        $schema = new Schema();


        $usersTable = $schema->createTable('cryptoWallet');
        $usersTable->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $usersTable->addColumn('name', 'string', ['length' => 50]);
        $usersTable->addColumn('symbol', 'string', ['length' => 20]);
        $usersTable->addColumn('amount', 'float');
        $usersTable->addColumn('price', 'float');
        $usersTable->addColumn('purchasePrice', 'float');
        $usersTable->addColumn('dateOfPurchase', 'string');
        $usersTable->addColumn('value', 'float');
        $usersTable->addColumn('valueNow', 'float');
        $usersTable->addColumn('profit', 'float');
        $usersTable->setPrimaryKey(['id']);

        $platform = $dbalConnection->getDatabasePlatform();
        $sqls = $schema->toSql($platform);

        foreach ($sqls as $sql) {
            $dbalConnection->executeStatement($sql);
        }

        echo "SQLite database created successfully at: {$this->filePath}\n";
    }
    public function insertWallet(Wallet $wallet): void
    {
        $data = [
            'name' => $wallet->getName(),
            'symbol' => $wallet->getSymbol(),
            'amount' => $wallet->getAmount(),
            'price' => $wallet->getPrice(),
            'purchasePrice' => $wallet->getPurchasePrice(),
            'dateOfPurchase' => $wallet->getDateOfPurchase()->format('Y-m-d H:i:s'),
            'value' => $wallet->getValue(),
            'valueNow' => $wallet->getValueNow(),
            'profit' => $wallet->getProfit()
        ];

        $this->dbalConnection->insert('cryptoWallet', $data);
    }

    public function getAll():array
    {
        return $this->dbalConnection->fetchAllAssociative('SELECT * FROM cryptoWallet');
    }

    public function deleteWallet(int $id):void
    {
        $this->dbalConnection->delete('cryptoWallet',["id"=>$id]);
    }
    public function deleteByIndex(int $index): void
    {
        $query = "DELETE FROM cryptoWallet WHERE rowid = ?";
        $stmt = $this->dbalConnection->prepare($query);
        $stmt->bindValue(1, $index);
        $stmt->executeQuery();
    }

    public function updateByIndex(int $index, array $data):void
    {

        $tableName = 'cryptoWallet';
        $dataToUpdate = [
            'valueNow' => $data['valueNow'],
            'profit' => $data['profit']
        ];
        $identifier = ['rowid' => $index];

        $this->dbalConnection->update($tableName, $dataToUpdate, $identifier);
    }

    public function insert(array $data):void
    {
        $this->dbalConnection->insert("cryptoWallet",$data);
    }
}