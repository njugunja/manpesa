<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT; 
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class ItemsController extends Controller
{

    public function indexAction()
    { 

    }

    protected $assigned = 0;
    protected $received = 1;
    protected $sold = 2;
    protected $returned = 3;


     protected function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
          $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }

	public function create(){//{productID,serialNumber,userID,token,status}
	    $jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$transactionManager = new TransactionManager(); 
	    $dbTransaction = $transactionManager->get();

    	$token = $json->token;
    	$serialNumber = $json->serialNumber;
    	$productID = $json->productID;
    	$status = $json->status;
    	$userID = $json->userID;

    	if(!$token || !$serialNumber || !$productID || !$userID){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	      if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	      if(!$status){
	      	$status=$this->assigned;
	      }


	    $item = Item::findFirst(array("serialNumber=:serialNumber:",
	    					'bind'=>array("serialNumber"=>$serialNumber)));

	    if($item){
	    	return $res->dataError("An item with the same serialNumber exists");
	    }

		 try {

			    $item = new Item();
			    $item->serialNumber = $serialNumber;
			    $item->productID = $productID;
			    $item->status=$status;
			    $item->createdAt = date("Y-m-d H:i:s");

			     if($item->save()===false){
			            $errors = array();
			                    $messages = $item->getMessages();
			                    foreach ($messages as $message) 
			                       {
			                         $e["message"] = $message->getMessage();
			                         $e["field"] = $message->getField();
			                          $errors[] = $e;
			                        }
			                 // return $res->dataError('item create failed',$errors);
			               $dbTransaction->rollback("Item create failed " . json_encode($errors));
			          }
			    $userItem = UserItems::findFirst(array("itemID=:itemId: AND userID=:userID:",
	    					'bind'=>array("itemId"=>$itemID,"userID"=>$userID))); 
			    if($userItem){
			    	$dbTransaction->rollback("Item already assigned, create failed failed " . json_encode($errors));
			    }

			    $userItem = new UserItems();
	      	    $userItem->userID = $userID;
	      	    $userItem->itemID = $item->itemID;
	      	    $userItem->status = $status;
	      	    $userItem->createdAt=date("Y-m-d H:i:s");

	      	    if($userItem->save()===false){
			            $errors = array();
			                    $messages = $userItem->getMessages();
			                    foreach ($messages as $message) 
			                       {
			                         $e["message"] = $message->getMessage();
			                         $e["field"] = $message->getField();
			                          $errors[] = $e;
			                        }
			                 // return $res->dataError('item create failed',$errors);
			               $dbTransaction->rollback("Assigning item failed, Item create failed " . json_encode($errors));
			          }

			     $dbTransaction->commit();
			     return $res->success("Item created successfully ",$item);
			}
		catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
		   $message = $e->getMessage(); 
		   return $res->dataError('Item create error', $message); 
		}


	} 
	public function update(){//{productID,serialNumber,token,itemID,status}
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$transactionManager = new TransactionManager(); 
	    $dbTransaction = $transactionManager->get();

    	$token = $json->token;
    	$serialNumber = $json->serialNumber;
    	$productID = $json->productID;
    	$itemID = $json->itemID;
    	$status = $json->status;

    	if(!$token || !$itemID){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	     $item = Item::findFirst(array("itemID=:id:",
	    					'bind'=>array("id"=>$itemID))); 

	     if(!$item){
	     	return $res->dataError("Item not found");
	     }

	     if($productID){
	     	$item->productID = $productID;
	     }
	     if($serialNumber){
	     	$item->serialNumber = $serialNumber;
	     }
	     if($status){
	     	$item->status = $status;
	     }
	     try {

		     if($item->save()===false){
		            $errors = array();
		            $messages = $item->getMessages();
		            foreach ($messages as $message) 
		                  {
		                     $e["message"] = $message->getMessage();
		                     $e["field"] = $message->getField();
		                      $errors[] = $e;
		                    }
		              $dbTransaction->rollback("Item update failed " . json_encode($errors));
		          }

		      $dbTransaction->commit();
		      return $res->success("Item updated successfully",$item);
		    }
		catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
		   $message = $e->getMessage(); 
		   return $res->dataError('Item update error', $message); 
		}


	}
	public function getAllItems(){
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $itemID = $request->getQuery('itemID');
        $productID = $request->getQuery('productID');
        $status = $request->getQuery('status');
        $action = $request->getQuery('action');

     //   $itemsQuery = "SELECT i.itemID,i.serialNumber,i.status,i.productID,i.createdAt FROM `user_items` ui JOIN item i on ui.itemID=i.itemID WHERE i.status=0";//ui.userID=2 AND

        if(!$token){
        	return $res->dataError("Token Missing");
        }

        $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	       $itemsQuery ="SELECT * FROM item ";
	       $condition = " ";

	       if($productID && $itemID && $status >= 0 && $status >=0 ){
	       	  $condition = " WHERE productID=$productID AND itemID=$itemID AND status = $status ";
	       }

	       elseif ($productID && $itemID  && $status < 0) {
	       	    $condition = " WHERE productID=$productID AND itemID=$itemID  ";
	       }
	      
	       elseif($productID && $action && !$itemID && !$status ){  
	       	     $condition = " WHERE productID=$productID AND status <= 1";
	       }
	       elseif($productID && !$itemID && !$status ){
	       	     $condition = " WHERE productID=$productID ";
	       }
	       elseif(!$productID  && $itemID  && !$status){
	       		$condition = " WHERE itemID=$itemID ";
	       }
	       elseif(!$productID  && !$itemID  && $status >=  0){
	       	    $condition = " WHERE status=$status ";
	       }
	       else{
	       	  $condition="";
	       }
	       $itemsQuery = $itemsQuery.$condition;

	     
	     
	    // return $res->success($itemsQuery);


		$items= $this->rawSelect($itemsQuery);
		return $res->success("Items fetch success",$items);

		//return $res->getSalesSuccess($items);
	}
 
	public function getTableItems(){ //sort, order, page, limit,filter
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$token = $request->getQuery('token');
        $productID = $request->getQuery('productID');
        $userID = $request->getQuery('userID');
        $sort = $request->getQuery('sort');
        $order = $request->getQuery('order');
        $page = $request->getQuery('page');
        $limit = $request->getQuery('limit');
        $filter = $request->getQuery('filter');

        $selectQuery = "SELECT i.itemID,i.serialNumber,i.status,i.productID,i.createdAt,u.userID,co.fullName FROM item i LEFT JOIN user_items ui on i.itemID=ui.itemID LEFT JOIN users u ON ui.userID=u.userID LEFT JOIN contacts co on u.contactID=co.contactsID ";//"SELECT * FROM item i ";
        $countQuery = "SELECT count(i.itemID) as totalItems from item i";
        $condition = " WHERE ";

        if($productID && $filter){
        	//$selectQuery = $selectQuery." WHERE i.productID=$productID ";
        	$condition = " WHERE i.productID=$productID AND ";
        }

        elseif(!$productID && !$filter){
        	$condition = " WHERE ";
        }
        elseif ($productID && !$filter) {
        	$condition = " WHERE i.productID=$productID ";    
        }

        $queryBuilder = $this->tableQueryBuilder($sort,$order,$page,$limit,$filter);

        if($queryBuilder){
        	$selectQuery=$selectQuery.$condition." ".$queryBuilder;
        	if($filter){
        		$countQuery = $countQuery.$condition." ".$queryBuilder;
        	}
        	else{
        		$countQuery = $countQuery.$condition;
        	}
        }
        else{
        	$selectQuery=$selectQuery.$condition;
        	$countQuery = $countQuery.$condition;
        }

      //  return $res->success($selectQuery);
        $count = $this->rawSelect($countQuery);
		$items= $this->rawSelect($selectQuery);

		 $data["totalItems"] = $count[0]['totalItems'];
          $data["items"] = $items;
		return $res->success("Items get successfully ",$data);



	}

	public function tableQueryBuilder($sort="",$order="",$page=0,$limit=10,$filter=""){
		$query = "";

		if(!$page || $page <= 0){
			$page=1;
		}
		if(!$limit){
			$limit =10;
		}

		$ofset = ($page-1)*$limit;
		if($sort  && $order  && $filter ){
			$query = "  i.serialNumber REGEXP $filter ORDER by $sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter ){
			$query = " ORDER by $sort $order LIMIT $ofset,$limit";
		}
		else if($sort  && $order  && !$filter ){
			$query = " ORDER by $sort $order  LIMIT $ofset,$limit";
		}
		else if(!$sort && !$order && !$filter){
			$query = " LIMIT $ofset,$limit";
		}

		else if(!$sort && !$order && $filter){
			$query = " i.serialNumber REGEXP '$filter' LIMIT $ofset,$limit";
		}

		return $query;

	}
	

	public function assignItem(){//{itemID,userID,token}
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$transactionManager = new TransactionManager(); 
	    $dbTransaction = $transactionManager->get();

    	$itemID = $json->itemID;
    	$userID = $json->userID;
    	$token = $json->token;

    	$userItem = new UserItems();

    	if(!$token || !$itemID){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }



	      $userItem = UserItems::findFirst(array("itemID=:itemId: AND userID=:userID:",
	    					'bind'=>array("itemId"=>$itemID,"userID"=>$userID))); 


	      if($userItem){
	      	return $res->dataError("Item already assigned to this user");
	      }

	      $userItem = UserItems::findFirst(array("itemID=:itemId:",
	    					'bind'=>array("itemId"=>$itemID))); 

	      if(!$userItem){ //create new item
	      	$userItem = new UserItems();
	      	$userItem->userID = $userID;
	      	$userItem->itemID = $itemID;
	      	$userItem->createdAt=date("Y-m-d H:i:s");

	      }
	      else{ //update this item
	      	$userItem->userID = $userID;
	      	$userItem->itemID = $itemID;
	      }


	  try{
	      if($userItem->save()===false){
	            $errors = array();
	            $messages = $userItem->getMessages();
	            foreach ($messages as $message) 
	                  {
	                     $e["message"] = $message->getMessage();
	                     $e["field"] = $message->getField();
	                      $errors[] = $e;
	                    }
	               $dbTransaction->rollback("Item update failed " . json_encode($errors));
	          }

	          $item = Item::findFirst(array("itemID=:id:",
	    					'bind'=>array("id"=>$itemID))); 
	          if(!$item){
	          	$dbTransaction->rollback("Item update failed, item not found " . json_encode($errors));
	          }
	          else{
	          	$item->status = $this->assigned;
	          	if($item->save()===false){
		            $errors = array();
		            $messages = $item->getMessages();
		            foreach ($messages as $message) 
		                  {
		                     $e["message"] = $message->getMessage();
		                     $e["field"] = $message->getField();
		                      $errors[] = $e;
		                    }
		               $dbTransaction->rollback("Item update failed " . json_encode($errors));
		          }
	          }
	        $dbTransaction->commit();
	       return $res->success("Items assigned successfully",$userItem);
	       
	   }
	   catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
		   $message = $e->getMessage(); 
		   return $res->dataError('Item create error', $message); 
		}
	
	}

	public function receiveItem(){//{userID,itemID,token}
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$transactionManager = new TransactionManager(); 
	    $dbTransaction = $transactionManager->get();

    	$token = $json->token;
    	$userID=$json->itemID;
    	$itemID = $json->itemID;
    	$status = $this->received;

    	if(!$token || !$itemID || !$itemID || !$status){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	     try {

	     	$userItem = UserItems::findFirst(array("itemID=:itemId: AND userID=:userID:",
	    					'bind'=>array("itemId"=>$itemID,"userID"=>$userID)));
		    $item = Item::findFirst(array("itemID=:itemId: ",
		    					'bind'=>array("itemId"=>$itemID)));

		    if(!$userItem && !$item){
		    	return $res->dataError("Item not assigned to this user or doesnt exist");
		    }

		   $this->updateItemStatus($itemID,$this->received,$dbTransaction);
		     $dbTransaction->commit();
		    return $res->success("Item received successfully");

	     } 
	     catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
		   $message = $e->getMessage(); 
		   return $res->dataError('Item create error', $message); 
		}
	   
	}


	public function returnItem(){//{userID,itemID,token}
		$jwtManager = new JwtManager();
    	$request = new Request();
    	$res = new SystemResponses();
    	$json = $request->getJsonRawBody();
    	$transactionManager = new TransactionManager(); 
	    $dbTransaction = $transactionManager->get();

    	$token = $json->token;
    	$userID=$json->itemID;
    	$itemID = $json->itemID;
    	$status = $this->received;

    	if(!$token || !$itemID || !$itemID || !$status){
	    	return $res->dataError("Missing data ");
	    }
	     $tokenData = $jwtManager->verifyToken($token,'openRequest');

	    if(!$tokenData){
	        return $res->dataError("Data compromised");
	      }

	     try {

	     	$userItem = UserItems::findFirst(array("itemID=:itemId: AND userID=:userID:",
	    					'bind'=>array("itemId"=>$itemID,"userID"=>$userID)));
		    $item = Item::findFirst(array("itemID=:itemId: ",
		    					'bind'=>array("itemId"=>$itemID)));

		    if(!$userItem && !$item){
		    	return $res->dataError("Item not assigned to this user or doesnt exist");
		    }

		  $this->updateItemStatus($itemID,$this->returned,$dbTransaction);
		   $dbTransaction->commit();

		    return $res->success("Item returned successfully");

	     } 
	     catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
		   $message = $e->getMessage(); 
		   return $res->dataError('Item create error', $message); 
		}
	   
	}

	protected function updateItemStatus($itemID, $status,$dbTransaction){
		$item = Item::findFirst(array("itemID=:id: ",
		    					'bind'=>array("id"=>$itemID)));
		$item->status = $status;

		if($item->save()===false){
		            $errors = array();
		            $messages = $item->getMessages();
		            foreach ($messages as $message) 
		                  {
		                     $e["message"] = $message->getMessage();
		                     $e["field"] = $message->getField();
		                      $errors[] = $e;
		                    }
		               $dbTransaction->rollback("Item update failed " . json_encode($errors));
		    }
		return true;
	}


	public function issueItem(){//{salesID,ItemID,userID}
		    $jwtManager = new JwtManager();
	    	$request = new Request();
	    	$res = new SystemResponses();
	    	$trasaction = new TransactionsController();

	    	$json = $request->getJsonRawBody();
	    	$transactionManager = new TransactionManager(); 
		    $dbTransaction = $transactionManager->get();

		    $salesID = $json->salesID;
		    $itemID = $json->itemID;
		    $userID = $json->userID;
		    $token = $json->token;
		//    $contactsID = $json->contactsID;

		    

		    if(!$token || !$salesID || !$itemID || !$userID ){
		    	return $res->dataError("Missing data ");
		    }
		     $tokenData = $jwtManager->verifyToken($token,'openRequest');

		    if(!$tokenData){
		        return $res->dataError("Data compromised");
		      }

		   try {

				$userItem = UserItems::findFirst(array("itemID=:itemId: AND userID=:userID: AND status=0",
			    					'bind'=>array("itemId"=>$itemID,"userID"=>$userID)));

				$item = Item::findFirst(array("itemID=:itemId: AND status=0 ",
				    					'bind'=>array("itemId"=>$itemID)));
				$sale = Sales::findFirst(array("salesID=:id: ",
				    					'bind'=>array("id"=>$salesID)));

				if($userItem && $item && $sale){

					$isPaid = $trasaction->checkSalePaid($salesID);

					if(!$isPaid){
						return $res->dataError("Sale minimum amount not settled ");
					}

					$userItem ->status = $this->sold;
					$item->status = $this->sold;

					$saleItem = SalesItem::findFirst(array("itemID=:i_id:",
				    					'bind'=>array('i_id'=>$itemID)));

					if($saleItem){
							return $res->dataError("Item already sold");
						}
					else{

						$saleItem = new SalesItem();
						$saleItem->salesID=$salesID;
						$saleItem->itemID=$itemID;
						$saleItem->createdAt = date("Y-m-d H:i:s");

						if($saleItem->save()===false){
				            $errors = array();
				            $messages = $saleItem->getMessages();
				            foreach ($messages as $message) 
				                  {
				                     $e["message"] = $message->getMessage();
				                     $e["field"] = $message->getField();
				                      $errors[] = $e;
				                    }
				              $dbTransaction->rollback("Item update failed " . json_encode($errors));
				          }
				          elseif ($item->save()===false){
					            $errors = array();
					            $messages = $item->getMessages();
					            foreach ($messages as $message) 
					                  {
					                     $e["message"] = $message->getMessage();
					                     $e["field"] = $message->getField();
					                      $errors[] = $e;
					                    }
					              $dbTransaction->rollback("Item update failed " . json_encode($errors));
				          }
				          elseif ($userItem->save()===false){
					            $errors = array();
					            $messages = $userItem->getMessages();
					            foreach ($messages as $message) 
					                  {
					                     $e["message"] = $message->getMessage();
					                     $e["field"] = $message->getField();
					                      $errors[] = $e;
					                    }
					              $dbTransaction->rollback("Item update failed " . json_encode($errors));
				          }

				          $dbTransaction->commit();
				          return $res->success("Item issued successfully ");
					}

				}
				else{
					return $res->dataError("Item not found");
				}

		    }
		 catch (Phalcon\Mvc\Model\Transaction\Failed $e) {
		   $message = $e->getMessage(); 
		   return $res->dataError('Item create error', $message); 
		}

	}
 

}

