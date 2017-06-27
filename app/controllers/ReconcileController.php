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

   private function checkSequence($account){
       if(strcasecmp($account,"0")==0){
          return true;
       }
       elseif(strcasecmp($account,"1")==0){
          return true;
       }
       elseif(strcasecmp($account,"12")==0){
          return true;
       }
       elseif(strcasecmp($account,"123")==0){
          return true;
       }
       elseif(strcasecmp($account,"1234")==0){
          return true;
       }
       elseif(strcasecmp($account,"12345")==0){
          return true;
       }
       elseif(strcasecmp($account,"123456")==0){
          return true;
       }
       elseif(strcasecmp($account,"1234567")==0){
          return true;
       }

       elseif(strcasecmp($account,"12345678")==0){
          return true;
       }
       elseif(strcasecmp($account,"123456789")==0){
          return true;
       }
       else{
        return false;
       }
   }

   public function redoReconciledTransactions() {
        $jwtManager = new JwtManager();
        $request = new Request();
        $res = new SystemResponses();
        $json = $request->getJsonRawBody();
        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();

        $selectQuery = "select ct.customerTransactionID,ct.transactionID,c.fullName,t.referenceNumber,t.fullName,c.nationalIdNumber,c.contactsID from customer_transaction ct join transaction t on ct.transactionID=t.transactionID join contacts c on t.salesID=c.nationalIdNumber where ct.contactsID=3190";
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

    public function reconcileAgentSales(){
            $jwtManager = new JwtManager();
            $request = new Request();
            $res = new SystemResponses();
            $json = $request->getJsonRawBody();
            $transactionManager = new TransactionManager();
            $dbTransaction = $transactionManager->get();
        //get all agents
        $agentsQuery = "Select * from users u join contacts c on u.contactID=c.contactsID";
        $agents = $this->rawSelect($agentsQuery);

        try{

            foreach ($agents as $agent) {
                $contactsID = $agent['contactsID'];
                $nationalIdNumber = $agent['nationalIdNumber'];

                //get transactions mapped to this agent 
                $agentTransactionsQuery = "SELECT * from customer_transaction ct join transaction t on ct.transactionID=t.transactionID where "
                                            ." ct.contactsID=$contactsID";
                $agentTransactions = $this->rawSelect($agentTransactionsQuery);

                foreach ($agentTransactions as $agentTransaction) {
                    $paymentAccount = $agentTransaction['salesID'];
                    $customerTransactionID = $agentTransaction['customerTransactionID'];

                    $contact = Contacts::findFirst("nationalIdNumber = $paymentAccount");

                    if(!$contact || $paymentAccount==0 || $this->checkSequence($paymentAccount)){
                        continue;
                    }
                    elseif($contact->contactsID == $contactsID){
                        continue;
                    }
                    else {
                        $newCustomerTransaction = CustomerTransaction::findFirst("customerTransactionID=$customerTransactionID");
                        $newCustomerTransaction->contactsID = $contact->contactsID;
                         if ($newCustomerTransaction->save() === false) {
                                $errors = array();
                                $messages = $newCustomerTransaction->getMessages();
                                foreach ($messages as $message) {
                                    $e["message"] = $message->getMessage();
                                    $e["field"] = $message->getField();
                                    $errors[] = $e;
                                }
                                $dbTransaction->rollback("agent transactions  update failed " . json_encode($errors));
                            }
                    }
                }
            }
              $dbTransaction->commit();
            return $res->success("customerTransaction status updated successfully", $agents);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('agent transactions change error', $message);
        }

    }

    public function reconcileMonthlySales(){
           $jwtManager = new JwtManager();
            $request = new Request();
            $res = new SystemResponses();
            $json = $request->getJsonRawBody();
            $transactionManager = new TransactionManager();
            $dbTransaction = $transactionManager->get();

            $selectQuery = "SELECT s.salesID,c.contactsID,c.fullName,t.fullName,s.amount,SUM(replace(t.depositAmount,',','')) as deposit from sales s join contacts c on s.contactsID=c.contactsID join customer_transaction ct on c.contactsID=ct.contactsID join transaction t on ct.transactionID=t.transactionID where date(s.createdAt) >= '2017-02-01' and date(s.createdAt) <= '2017-02-28' and s.paid >0 and s.status <=0 group by s.salesID";
            $sales = $this->rawSelect($selectQuery);


            try{

                foreach ($sales as $sale) {
                    $salesID = $sale['salesID'];

                    $saleAmount = $sale['amount'];
                    $deposit = $sale['deposit'];
                    $o_sale=Sales::findFirst("salesID=$salesID");
                    if(!$o_sale){
                        continue;
                    }

                    if($deposit >= $saleAmount ) {
                        $o_sale->status = 2;
                         $o_sale->paid = $deposit;
                    }
                    elseif($deposit < $saleAmount && $deposit >0) {
                         $o_sale->status = 1;
                         $o_sale->paid = $deposit;
                    }

                    if ($o_sale->save() === false) {
                            $errors = array();
                            $messages = $o_sale->getMessages();
                            foreach ($messages as $message) {
                                $e["message"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                $errors[] = $e;
                            }
                            $dbTransaction->rollback("march sales  update failed " . json_encode($errors));
                        }

                }

                $dbTransaction->commit();
                return $res->success("march sales   updated successfully", $sales);
        } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('march sales   change error', $message);
        }

    }

    public function reconcileSaleWithContactTransaction(){
           $jwtManager = new JwtManager();
            $request = new Request();
            $res = new SystemResponses();
            $json = $request->getJsonRawBody();
            $transactionManager = new TransactionManager();
            $dbTransaction = $transactionManager->get();

            $salesFebQuery = "SELECT s.salesID,s.contactsID,c.fullName,t.fullName,SUM(replace(t.depositAmount,',','')) as m_amount,s.amount FROM sales s JOIN contacts c ON s.contactsID=c.contactsID JOIN customer_transaction ct ON c.contactsID=ct.contactsID JOIN transaction t ON ct.transactionID=t.transactionID WHERE date(s.createdAt) >= '2017-01-01' AND date(s.createdAt) <= '2017-01-31' AND paid <=0 AND s.status<=0 group by s.salesID";

            $febSales = $this->rawSelect($salesFebQuery);

             try{
                foreach ($febSales as $febSale) {
                    $contactsID = $febSale['contactsID'];
                    $userDepositAmount = $febSale['m_amount'];
                    $salesID = $febSale['salesID'];
                    $userSalesQuery = "SELECT * FROM sales WHERE salesID=$salesID AND status >=0";
                    $userSales = $this->rawSelect($userSalesQuery);
                    foreach ($userSales as $userSale) {
                        $saleID = $userSale['salesID'];
                        $sale = Sales::findFirst("salesID=$saleID");
                        if($userDepositAmount >= 1800){
                            $sale->status = 1;
                            $sale->paid = 1800;
                            $userDepositAmount = $userDepositAmount - 1800;
                        }
                        elseif ($userDepositAmount >0 && $userDepositAmount <1800) {
                            $sale->status = 1;
                            $sale->paid = $userDepositAmount;
                        }

                        if ($sale->save() === false) {
                                $errors = array();
                                $messages = $sale->getMessages();
                                foreach ($messages as $message) {
                                    $e["message"] = $message->getMessage();
                                    $e["field"] = $message->getField();
                                    $errors[] = $e;
                                }
                                $dbTransaction->rollback("april sales  update failed " . json_encode($errors));
                            }
                    }

                }

                 $dbTransaction->commit();
                return $res->success("march sales   updated successfully", $sales);
                

             } catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
            $message = $e->getMessage();
            return $res->dataError('april sales   change error', $message);
        }
    }



}

