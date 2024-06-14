<?php

namespace App;

use App\Api\CoinGecko;
use App\Api\CoinMC;
use App\Api\CryptoApi;
use App\Logging\Activity;
use App\Logging\Log;
use App\Logging\Transaction;
use App\Logging\TransactionLog;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Doctrine\DBAL\Exception;


class Exchange
{
    private array $crypto;
    private string $baseDir;
    private array $wallet;
    private User $user;
    private CryptoApi $exchangeApi;
    private Database $database;


    public function __construct(string $baseDir, User $user,Database $database)
    {
        $this->database = $database;
        $this->crypto = $this->getCryptoList();
        $this->baseDir = $baseDir;
        $this->user = $user;
        $this->latestUpdate= $this->exchangeApi->getLatest();
        $this->wallet=$this->getWallet();
    }


    public function getCryptoList(): array
    {
        $this->exchangeApi = new CoinMC();//can be CoinGecko or CoinMC
        return $this->exchangeApi->getLatest();
    }


    public function selectCrypto(int $index)
    {
        $cryptoList = $this->getCryptoList();

        return $cryptoList[$index];
    }

    public function getWallet(): array
    {
        $walletData = $this->database->getAll();
        $items=[];
        foreach($walletData as $value){
            $items[] = new Wallet(
                $value['name'],
                $value['symbol'],
                $value['amount'],
                $value['price'],
                $value['purchasePrice'],
                Carbon::parse($value['dateOfPurchase'])->setTimezone('Europe/Riga'),
                $value['value'],
                $value['valueNow'],
                $value['profit']
            );
        }
        return $items;
    }


    public function addToWallet(Wallet $coin): void
    {
        $data = [
            'name' => $coin->getName(),
            'symbol' => $coin->getSymbol(),
            'amount' => $coin->getAmount(),
            'price' => $coin->getPrice(),
            'purchasePrice'=> $coin->getPurchasePrice(),
            'dateOfPurchase'=> $coin->getDateOfPurchase(),
            'value'=> $coin->getValue(),
            'valueNow'=> $coin->getValueNow(),
            'profit'=> $coin->getProfit(),
        ];
        $this->database->insert($data);
    }

    public function sell():void//works
    {
        $index = readline("Enter index to select crypto: ");
        if (isset($this->wallet[$index])) {
            $crypto = $this->wallet[$index];
            $name = $crypto->getName();
            $valueNow = $crypto->getValueNow();

            $this->user->setWallet($this->user->getWallet() + $valueNow);
            $this->user->saveToFile();


            unset($this->wallet[$index]);
            $this->wallet = array_values($this->wallet);
            $this->database->deleteByIndex($index + 1);


            $transaction = new Transaction("Sold $name", Carbon::now());
            $transactionLog = new TransactionLog($this->baseDir);
            $transactionLog->addTransactionToLog($transaction);

            $activity = new Activity("{$this->user->getName()}: Sold $name", Carbon::now());
            $log = new Log($this->baseDir);
            $log->addActivityToLog($activity);
        } else {
            echo "Invalid index provided.\n";
        }
    }

    public function buy():void//works
    {
        $index = (int) readline("Enter index to select Crypto: ");
        $selectedCrypto = $this->selectCrypto($index);
        $name = $selectedCrypto->getName();
        $symbol = $selectedCrypto->getSymbol();
        $price = $selectedCrypto->getPrice();
        $purchasePrice = (int) readline("Enter how much to buy in USD: ");
        $amount = $purchasePrice / $price; // Dollars worth
        $dateOfPurchase = Carbon::now('Europe/Riga');
        $value = $amount * $price;
        $valueNow = $amount * $price;
        $this->user->setWallet($this->user->getWallet() - $purchasePrice);

        $crypto = new Wallet($name, $symbol, $amount, $price, $purchasePrice, $dateOfPurchase, $value, $valueNow);
        $this->addToWallet($crypto);
        $this->user->saveToFile();

        $transaction = new Transaction("Bought $symbol for $$purchasePrice", Carbon::now('Europe/Riga'));
        $transactionLog = new TransactionLog($this->baseDir);
        $transactionLog->addTransactionToLog($transaction);

        $activity = new Activity("{$this->user->getName()}: Bought $symbol for $$purchasePrice", Carbon::now());
        $log = new Log($this->baseDir);
        $log->addActivityToLog($activity);
    }

    public function updateWallet(): void//works
    {
        $wallet = $this->getWallet();
        foreach ($wallet as $index => $crypto) {
            $search = $this->search($crypto->getSymbol());
            $valueNow = $search->getPrice() * $crypto->getAmount();
            $crypto->setValueNow($valueNow);
            $crypto->setProfit($crypto->getValueNow() - $crypto->getValue());

            $data = [
                'valueNow' => $crypto->getValueNow(),
                'profit' => $crypto->getProfit()
            ];
            $this->database->updateByIndex($index , $data);
        }
    }

    public function searchAndDisplay(string $symbol):void//works
    {
        $selectedCrypto = $this->search($symbol);
        foreach ($this->crypto as $key => $crypto) {

            if ($crypto->getSymbol() === $symbol) {
                $selectedCrypto = $this->crypto[$key];
            }
        }
        $rows = [];
        $rows[] = [
            $selectedCrypto->getName(),
            $selectedCrypto->getSymbol(),
            $selectedCrypto->getPrice()
        ];

        $output = new ConsoleOutput();
        $table = new Table($output);
        $table
            ->setHeaders([
                "Name",
                "Symbol",
                "Price"
            ])
            ->setRows($rows);
        $table->render();
    }

    public function search(string $symbol): ?Currency
    {
        foreach ($this->crypto as $crypto) {
            if ($crypto->getSymbol() === $symbol) {
                return $crypto;
            }
        }

        echo "Error: couldn't find crypto $symbol\n";
        return null;
    }

    public function displayCrypto(): void
    {
        $rows = [];

        foreach ($this->crypto as $index => $crypto) {
            $rows[] = [$index, $crypto->getName(), $crypto->getSymbol(), $crypto->getPrice()];
        }

        $output = new ConsoleOutput();
        $table = new Table($output);
        $table->setHeaders(["Index", "Name", "Symbol", "Price"])->setRows($rows);
        $table->render();
    }

    public function displayWallet(): void
    {
        $this->updateWallet();
        $wallet = $this->getWallet();
        $rows = [];
        foreach ($wallet as $index => $crypto) {
            $rows[] = [
                $index,
                $crypto->getName(),
                $crypto->getSymbol(),
                $crypto->getPrice(),
                $crypto->getPurchasePrice(),
                $crypto->getAmount(),
                $crypto->getDateOfPurchase(),
                $crypto->getValue(),
                $crypto->getValueNow(),
                $crypto->getProfit()
            ];
        }
        $output = new ConsoleOutput();
        $table = new Table($output);
        $table->setHeaders([
            "Index",
            "Name",
            "Symbol",
            "Price",
            "Purchase Price",
            "Amount",
            "DateOfPurchase",
            "Value",
            "Value Now",
            "Profit/Loss"]);
        $table->setRows($rows);
        $table->render();
    }
}