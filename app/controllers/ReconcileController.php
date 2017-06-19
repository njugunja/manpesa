<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;


class ReconcileController extends Controller
{
	   /*
    Raw query select function to work in any version of phalcon
    */
    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
    }

   public function redoReconciledTransactions() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $selectQuery = "select ct.customerTransactionID,ct.transactionID,c.fullName,t.referenceNumber,t.fullName,c.nationalIdNumber,c.contactsID from customer_transaction ct join transaction t on ct.transactionID=t.transactionID join contacts c on t.salesID=c.nationalIdNumber where ct.contactsID=874";
        $transactions = $this->rawSelect($selectQuery);
        try {

            foreach ($transactions as $transaction) {
                $contactsID = $transaction["contactID"];
                $customerTransactionID = $transaction['customerTransactionID'];
                $customerTransaction = CustomerTransaction::findFirst("customerTransactionID = $customerTransactionID");
               
                if ($customerTransaction) {
                    $customerTransaction->contactsID = $transaction["contactsID"];
                    
                    if ($customerTransaction->save() === false) {
                        $errors = array();
                        $messages = $customerTransaction->getMessages();
                        foreach ($messages as $message) {
                            $e["message"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            $errors[] = $e;
                        }
                        $dbTransaction->rollback("customerTransaction status update failed " . json_encode($errors));
                    }
                }
            }

            $dbTransaction->commit();
            return $res->success("customerTransaction status updated successfully", $user);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('customerTransaction status change error', $message);
        }
    }



}
