<?php

require_once './vendor/autoload.php';

use App\Exchange;
use App\Logging\Activity;
use App\Logging\Log;
use App\Logging\TransactionLog;
use App\User;
use Carbon\Carbon;
use App\Database;


$sqliteFile = __DIR__ . '/database.sqlite';
$database = new Database($sqliteFile);
/*$database->createDatabase();*/

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$baseDir = __DIR__;
$userFilePath = __DIR__ . "/User/UserWallet.json";
$user = User::loadFromFile($baseDir);

if ($user === null) {
    $name = (string)readline("Enter user name: ");
    $walletAmount = (float)readline("Enter wallet amount: ");
    $user = new User($name, $walletAmount, $baseDir);
    $user->saveToFile();
}

while (true) {
    $user->displayUser();
    echo "1. List top crypto\n2. Search for crypto by Symbol\n3. Buy crypto\n4. Sell crypto\n";
    echo "5. Display Wallet\n6. Display activity log\n7. Transaction List\n8. Exit\n";
    $choice = (int)readline("Enter index to select choice: ");

    switch ($choice) {
        case 1:
            $exchange = new Exchange($baseDir, $user, $database);
            $exchange->displayCrypto();
            break;
        case 2:
            $symbol = strtoupper((string)readline("Enter symbol: "));
            $exchange = new Exchange($baseDir, $user,$database);
            $exchange->searchAndDisplay($symbol);

            $activity = new Activity("{$user->getName()}:Searched for $symbol", carbon::now());
            $log = new Log($baseDir);
            $log->addActivityToLog($activity);
            break;
        case 3:
            $exchange = new Exchange($baseDir, $user,$database);
            $exchange->displayCrypto();
            $exchange->buy();
            break;
        case 4:
            $exchange = new Exchange($baseDir, $user, $database);
            $exchange->displayWallet();
            $exchange->sell();
            break;
        case 5:
            $exchange = new Exchange($baseDir, $user, $database);
            $exchange->displayWallet();
            $activity = new Activity("{$user->getName()}:Looked at wallet", carbon::now());
            $log = new Log($baseDir);
            $log->addActivityToLog($activity);
            break;
        case 6:
            $log = new Log($baseDir);
            $log->displayLog();
            break;
        case 7:
            $transactionLog = new TransactionLog($baseDir);
            $transactionLog->displayTransactionLog();
            break;
        case 8:
            $user->saveToFile();
            exit;
        default:
            echo "Error: Wrong Input";
            break;
    }
}