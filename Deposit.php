<?php

namespace modules\deposit;                          //Make sure namespace is same structure with parent directory

use \classes\Auth as Auth;                          //For authentication internal user
use \classes\JSON as JSON;                          //For handling JSON in better way
use \classes\Validation as Validation;              //To validate the string
use \classes\CustomHandlers as CustomHandlers;      //To get default response message
use \modules\deposit\Dictionary as Dictionary;      //To get custom message handler
use PDO;                                            //To connect with database

	/**
     * Deposit class
     *
     * @package    modules/deposit
     * @author     M ABD AZIZ ALFIAN <github.com/aalfiann>
     * @copyright  Copyright (c) 2018 M ABD AZIZ ALFIAN
     * @license    https://github.com/aalfiann/reSlim-modules-deposit/blob/master/LICENSE.md  MIT License
     */
    class Deposit {

        // database var
        protected $db;

        // path var
        var $baseurl,$basepath,$basemod;

        //master var
        var $username,$token;
        
        //data var
        var $task,$refid,$depid,$destid,$description,$mutation,$year,$topnumber;

        // for pagination
		var $page,$itemsPerPage;

		// for search
        var $firstdate,$lastdate,$search;
        
        // for multi language
        var $lang;

        //construct database object
        function __construct($db=null) {
            if (!empty($db)) $this->db = $db;
            $this->basemod = dirname(__FILE__);
        }

        //Get modules information
        public function viewInfo(){
            return file_get_contents($this->basemod.'/package.json');
        }

        /**
         * Build database table 
         */
        public function install(){
            if (Auth::validToken($this->db,$this->token,$this->username)){
                $role = Auth::getRoleID($this->db,$this->token);
                if ($role == 1){
                    try {
                        $this->db->beginTransaction();
                        $sql = file_get_contents(dirname(__FILE__).'/deposit.sql');
                        $stmt = $this->db->prepare($sql);
                        if ($stmt->execute()) {
                            $data = [
                                'status' => 'success',
                                'code' => 'RS101',
                                'message' => CustomHandlers::getreSlimMessage('RS101',$this->lang)
                            ];	
                        } else {
                            $data = [
                                'status' => 'error',
                                'code' => 'RS201',
                                'message' => CustomHandlers::getreSlimMessage('RS201',$this->lang)
                            ];
                        }
                        $this->db->commit();
                    } catch (PDOException $e) {
                        $data = [
                            'status' => 'error',
                            'code' => $e->getCode(),
                            'message' => $e->getMessage()
                        ];
                        $this->db->rollBack();
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'RS404',
                        'message' => CustomHandlers::getreSlimMessage('RS404',$this->lang)
                    ];
                }
            } else {
                $data = [
	    			'status' => 'error',
					'code' => 'RS401',
        	    	'message' => CustomHandlers::getreSlimMessage('RS401',$this->lang)
				];
            }

			return JSON::encode($data,true);
			$this->db = null;
        }

        /**
         * Remove database table 
         */
        public function uninstall(){
            if (Auth::validToken($this->db,$this->token,$this->username)){
                $role = Auth::getRoleID($this->db,$this->token);
                if ($role == 1){
                    try {
                        $this->db->beginTransaction();
                        $sql = "DROP TABLE IF EXISTS deposit_balance;DROP TABLE IF EXISTS deposit_history;DROP TABLE IF EXISTS deposit_mutation;";
                        $stmt = $this->db->prepare($sql);
                        if ($stmt->execute()) {
                            $data = [
                                'status' => 'success',
                                'code' => 'RS104',
                                'message' => CustomHandlers::getreSlimMessage('RS104',$this->lang)
                            ];	
                            Auth::deleteCacheAll('deposit-*',30);
                        } else {
                            $data = [
                                'status' => 'error',
                                'code' => 'RS204',
                                'message' => CustomHandlers::getreSlimMessage('RS204',$this->lang)
                            ];
                        }
                        $this->db->commit();
                    } catch (PDOException $e) {
                        $data = [
                            'status' => 'error',
                            'code' => $e->getCode(),
                            'message' => $e->getMessage()
                        ];
                        $this->db->rollBack();
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'RS404',
                        'message' => CustomHandlers::getreSlimMessage('RS404',$this->lang)
                    ];
                }
            } else {
                $data = [
	    			'status' => 'error',
					'code' => 'RS401',
        	    	'message' => CustomHandlers::getreSlimMessage('RS401',$this->lang)
				];
            }

			return JSON::encode($data,true);
			$this->db = null;
        }


        //DEPOSIT============================================


        private function isAccountExist(){
            $r = false;
            $newdepid = strtolower($this->depid);
            if (Auth::isKeyCached('deposit-'.$newdepid.'-exists',86400)){
                $r = true;
            } else {
                $sql = "SELECT a.DepositID
			    	FROM deposit_balance a 
				    WHERE a.DepositID = :depid;";
    			$stmt = $this->db->prepare($sql);
	    		$stmt->bindParam(':depid', $newdepid, PDO::PARAM_STR);
		    	if ($stmt->execute()) {	
                	if ($stmt->rowCount() > 0){
                        $r = true;
                        Auth::writeCache('deposit-'.$newdepid.'-exists');
        	        }          	   	
	    		}
            }	 		
			return $r;
			$this->db = null;
        }

        private function createAccount(){
            $r = false;
            $newdepid = strtolower($this->depid);
            try {
                $this->db->beginTransaction();
                $sql = "INSERT INTO deposit_balance (DepositID,Balance) 
                    VALUES (:depid,'0');";
    		    $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':depid', $this->depid, PDO::PARAM_STR);
                if ($stmt->execute()) {	
                    if ($stmt->rowCount() > 0){
                        $r = true;
                    }          	   	
                }
                $this->db->commit();
            } catch (PDOException $e){
                $data = [
                    'status' => 'error',
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ];
                $this->db->rollBack();
            }
            return $r;
            $this->db = null;
        }

        private function getBalance($id=""){
            if(empty($id)) {
                $newdepid = strtolower($this->depid);
            } else {
                $newdepid = strtolower($id);
            }
            $sql = "SELECT a.Balance
			    FROM deposit_balance a 
			    WHERE a.DepositID = :depid;";
    		$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':depid', $newdepid, PDO::PARAM_STR);
	    	if ($stmt->execute()) {	
                if ($stmt->rowCount() > 0){
                    $single = $stmt->fetch();
                    return $single['Balance'];
    	        }          	   	
            }
            return 0;
            $this->db = null;
        }

        private function setBalance($task='db',$balance=0,$id=""){
            $r = false;
            $newtask = strtolower($task);
            if (empty($id)){
                $newdepid = strtolower($this->depid);
            } else {
                $newdepid = strtolower($id);
            }
            switch($newtask){
                case 'db':
                    $sql = "UPDATE deposit_balance a SET a.Balance = a.Balance + :balance WHERE a.DepositID = :depid;";
                    break;
                case 'cr':
                    $sql = "UPDATE deposit_balance a SET a.Balance = a.Balance - :balance WHERE a.DepositID = :depid;";
                    break;
                default:
                    $sql = "UPDATE deposit_balance a SET a.Balance = a.Balance + :balance WHERE a.DepositID = :depid;";
            }
    		$stmt = $this->db->prepare($sql);
            $stmt->bindParam(':depid', $newdepid, PDO::PARAM_STR);
            $stmt->bindParam(':balance', $balance, PDO::PARAM_STR);
	    	if ($stmt->execute()) {	
                if ($stmt->rowCount() > 0){
                    $r = true;
    	        }          	   	
            }
            return $r;
            $this->db = null;
        }

        private function preMutation(){
            $r = false;
            $balance = $this->getBalance();
            $sql = "INSERT INTO deposit_history (ReferenceID,Balance) 
                VALUES (:refid,:balance);";
    		$stmt = $this->db->prepare($sql);
            $stmt->bindParam(':refid', $this->refid, PDO::PARAM_STR);
            $stmt->bindParam(':balance', $balance, PDO::PARAM_STR);
	        if ($stmt->execute()) {	
                if ($stmt->rowCount() > 0){
                    $r = true;
                }          	   	
            }
            return $r;
            $this->db = null;
        }

        private function addMutation(){
            $newdepid = strtolower($this->depid);
            $newusername = strtolower($this->username);
            $newtask = strtoupper($this->task);
            try {
                $this->db->beginTransaction();
                if ($this->preMutation()){
                    $sql = "INSERT INTO deposit_mutation (DepositID,ReferenceID,Description,Task,Mutation,Created_by,Created_at) 
                        VALUES (:depid,:refid,:desc,:task,:mutation,:username,current_timestamp);";
                    $stmt = $this->db->prepare($sql);
                    $stmt->bindParam(':depid', $newdepid, PDO::PARAM_STR);
                    $stmt->bindParam(':refid', $this->refid, PDO::PARAM_STR);
                    $stmt->bindParam(':desc', $this->description, PDO::PARAM_STR);
                    $stmt->bindParam(':task', $newtask, PDO::PARAM_STR);
                    $stmt->bindParam(':mutation', $this->mutation, PDO::PARAM_STR);
                    $stmt->bindParam(':username', $newusername, PDO::PARAM_STR);
                    if ($stmt->execute()) {
                        if($this->setBalance($newtask,$this->mutation)){
                            $data = [
                                'status' => 'success',
                                'code' => 'DP104',
                                'message' => Dictionary::write('DP104',$this->lang)
                            ];
                        } else {
                            $data = [
                                'status' => 'error',
                                'code' => 'RS201',
                                'message' => CustomHandlers::getreSlimMessage('RS201',$this->lang),
                                'case' => 'postmutation'
                            ];
                        }
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 'RS201',
                            'message' => CustomHandlers::getreSlimMessage('RS201',$this->lang),
                            'case' => 'mutation'
                        ];
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'RS201',
                        'message' => CustomHandlers::getreSlimMessage('RS201',$this->lang),
                        'case' => 'premutation'
                    ];
                }
                $this->db->commit();
            } catch (PDOException $e) {
                $data = [
                    'status' => 'error',
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ];
                $this->db->rollBack();
            }
            return $data;
            $this->db = null;
        }

        public function generateReferenceID(){
            return str_replace('.','-',uniqid('',true));
        }

        public function isBalanceEnough($id=""){
            if($this->getBalance($id)>=$this->mutation) return true;
            return false;
        }
        
        public function transactionCR(){
            if ($this->isAccountExist()){
                if ($this->isBalanceEnough()){
                    $data = $this->addMutation();
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'DP207',
                        'message' => Dictionary::write('DP207',$this->lang)
                    ];
                }
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 'DP205',
                    'message' => Dictionary::write('DP205',$this->lang)
                ];
            }
            return $data;
			$this->db = null;
        }

        public function checkBalance(){
            if (Auth::validToken($this->db,$this->token,$this->username)){
                if($this->isAccountExist()) {
                    $data = [
                        'result' => [
                            'DepositID' => $this->depid,
                            'Balance' => $this->getBalance()
                        ],
                        'status' => 'success',
                        'code' => 'RS501',
                        'message' => CustomHandlers::getreSlimMessage('RS501',$this->lang)
                    ];
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'DP207',
                        'message' => Dictionary::write('DP207',$this->lang)
                    ];
                }
            } else {
                $data = [
	    			'status' => 'error',
					'code' => 'RS401',
        	    	'message' => CustomHandlers::getreSlimMessage('RS401',$this->lang)
				];
            }
            return JSON::safeEncode($data,true);
	        $this->db= null;
        }

        /**
         * transaction Deposit 
         */
        public function transaction(){
            if (Auth::validToken($this->db,$this->token,$this->username)){
                if ($this->isAccountExist()){
                    $data = $this->addMutation($this->lang);
                } else {
                    if($this->createAccount()){
                        $data = $this->addMutation($this->lang);
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 'RS201',
                            'message' => CustomHandlers::getreSlimMessage('RS201',$this->lang)
                        ];
                    }
                }
            } else {
                $data = [
	    			'status' => 'error',
					'code' => 'RS401',
        	    	'message' => CustomHandlers::getreSlimMessage('RS401',$this->lang)
				];
            }
            return JSON::encode($data,true);
			$this->db = null;
        }

        /**
         * Show mutation as pagination
         */
        public function showMutation(){
            if (Auth::validToken($this->db,$this->token,$this->username)){
				//count total row
				$sqlcountrow = "SELECT count(a.DepositID) as TotalRow 
					from deposit_mutation a
                    inner join deposit_history b on a.ReferenceID = b.ReferenceID
					where DATE(a.Created_at) BETWEEN :firstdate AND :lastdate AND a.DepositID = :depid
					order by a.Created_at asc;";
                $stmt = $this->db->prepare($sqlcountrow);
                $stmt->bindParam(':depid', $this->depid, PDO::PARAM_STR);
				$stmt->bindParam(':firstdate', $this->firstdate, PDO::PARAM_STR);
                $stmt->bindParam(':lastdate', $this->lastdate, PDO::PARAM_STR);
				
				if ($stmt->execute()) {	
    	    		if ($stmt->rowCount() > 0){
						$single = $stmt->fetch();
						
						// Paginate won't work if page and items per page is negative.
						// So make sure that page and items per page is always return minimum zero number.
						$newpage = Validation::integerOnly($this->page);
						$newitemsperpage = Validation::integerOnly($this->itemsPerPage);
						$limits = (((($newpage-1)*$newitemsperpage) <= 0)?0:(($newpage-1)*$newitemsperpage));
						$offsets = (($newitemsperpage <= 0)?0:$newitemsperpage);

						// Query Data
						$sql = "SELECT a.DepositID as 'DepositID',a.Created_at,a.ReferenceID,a.Description,a.Task,b.Balance as 'Before', a.Mutation,(if(a.Task = 'DB',(b.Balance + a.Mutation),(b.Balance - a.Mutation))) as 'Balance'
                            from deposit_mutation a
                            inner join deposit_history b on a.ReferenceID = b.ReferenceID
							where DATE(a.Created_at) BETWEEN :firstdate AND :lastdate AND a.DepositID = :depid
							order by a.Created_at asc LIMIT :limpage , :offpage;";
						$stmt2 = $this->db->prepare($sql);
						$stmt2->bindParam(':depid', $this->depid, PDO::PARAM_STR);
        				$stmt2->bindParam(':firstdate', $this->firstdate, PDO::PARAM_STR);
                        $stmt2->bindParam(':lastdate', $this->lastdate, PDO::PARAM_STR);
						$stmt2->bindValue(':limpage', (INT) $limits, PDO::PARAM_INT);
						$stmt2->bindValue(':offpage', (INT) $offsets, PDO::PARAM_INT);
						
						if ($stmt2->execute()){
                            $pagination = new \classes\Pagination();
                            $pagination->lang = $this->lang;
							$pagination->totalRow = $single['TotalRow'];
							$pagination->page = $this->page;
							$pagination->itemsPerPage = $this->itemsPerPage;
							$pagination->fetchAllAssoc = $stmt2->fetchAll(PDO::FETCH_ASSOC);
							$data = $pagination->toDataArray();
						} else {
							$data = [
        	    	    		'status' => 'error',
		        		    	'code' => 'RS202',
	    			    	    'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
							];	
						}			
				    } else {
    	    			$data = [
        	    			'status' => 'error',
		    	    		'code' => 'RS601',
        			    	'message' => CustomHandlers::getreSlimMessage('RS601',$this->lang)
						];
		    	    }          	   	
				} else {
					$data = [
    	    			'status' => 'error',
						'code' => 'RS202',
	        		    'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
					];
				}
				
			} else {
				$data = [
	    			'status' => 'error',
					'code' => 'RS401',
        	    	'message' => CustomHandlers::getreSlimMessage('RS401',$this->lang)
				];
			}		
        
			return JSON::safeEncode($data,true);
	        $this->db= null;
        }

        /**
         * Show mutation as pagination for admin
         */
        public function showMutationAdmin(){
            if (Auth::validToken($this->db,$this->token,$this->username)){
                $role = Auth::getRoleID($this->db,$this->token);
                if ($role == '1' || $role == '2'){
                    $search = "%$this->search%";
				    //count total row
    				$sqlcountrow = "SELECT count(a.DepositID) as TotalRow 
	    				from deposit_mutation a
                        inner join deposit_history b on a.ReferenceID = b.ReferenceID
			    		where DATE(a.Created_at) BETWEEN :firstdate AND :lastdate
                        AND (a.DepositID like :search OR a.ReferenceID like :search)
    					order by a.Created_at asc;";
                    $stmt = $this->db->prepare($sqlcountrow);
                    $stmt->bindParam(':search', $search, PDO::PARAM_STR);
			    	$stmt->bindParam(':firstdate', $this->firstdate, PDO::PARAM_STR);
                    $stmt->bindParam(':lastdate', $this->lastdate, PDO::PARAM_STR);
                    
    				if ($stmt->execute()) {	
        	    		if ($stmt->rowCount() > 0){
		    				$single = $stmt->fetch();
						
			    			// Paginate won't work if page and items per page is negative.
				    		// So make sure that page and items per page is always return minimum zero number.
					    	$newpage = Validation::integerOnly($this->page);
						    $newitemsperpage = Validation::integerOnly($this->itemsPerPage);
    						$limits = (((($newpage-1)*$newitemsperpage) <= 0)?0:(($newpage-1)*$newitemsperpage));
	    					$offsets = (($newitemsperpage <= 0)?0:$newitemsperpage);

		    				// Query Data
			    			$sql = "SELECT a.DepositID as 'DepositID',a.Created_at,a.ReferenceID,a.Description,a.Task,b.Balance as 'Before', a.Mutation,(if(a.Task = 'DB',(b.Balance + a.Mutation),(b.Balance - a.Mutation))) as 'Balance'
                                from deposit_mutation a
                                inner join deposit_history b on a.ReferenceID = b.ReferenceID
						    	where DATE(a.Created_at) BETWEEN :firstdate AND :lastdate
                                AND (a.DepositID like :search OR a.ReferenceID like :search)
    							order by a.Created_at asc LIMIT :limpage , :offpage;";
	    					$stmt2 = $this->db->prepare($sql);
		    				$stmt2->bindParam(':search', $search, PDO::PARAM_STR);
        	    			$stmt2->bindParam(':firstdate', $this->firstdate, PDO::PARAM_STR);
                            $stmt2->bindParam(':lastdate', $this->lastdate, PDO::PARAM_STR);
					    	$stmt2->bindValue(':limpage', (INT) $limits, PDO::PARAM_INT);
    						$stmt2->bindValue(':offpage', (INT) $offsets, PDO::PARAM_INT);
						
	    					if ($stmt2->execute()){
                                $pagination = new \classes\Pagination();
                                $pagination->lang = $this->lang;
			    				$pagination->totalRow = $single['TotalRow'];
				    			$pagination->page = $this->page;
					    		$pagination->itemsPerPage = $this->itemsPerPage;
						    	$pagination->fetchAllAssoc = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    							$data = $pagination->toDataArray();
	    					} else {
		    					$data = [
        	        	    		'status' => 'error',
		            		    	'code' => 'RS202',
	    			        	    'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
						    	];	
    						}			
	    			    } else {
    	        			$data = [
        	        			'status' => 'error',
		    	        		'code' => 'RS601',
        			        	'message' => CustomHandlers::getreSlimMessage('RS601',$this->lang)
						    ];
    		    	    }          	   	
	    			} else {
		    			$data = [
    	        			'status' => 'error',
				    		'code' => 'RS202',
	        		        'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
    					];
	    			}
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'RS404',
                        'message' => CustomHandlers::getreSlimMessage('RS404',$this->lang)
                    ];
                }
			} else {
				$data = [
	    			'status' => 'error',
					'code' => 'RS401',
        	    	'message' => CustomHandlers::getreSlimMessage('RS401',$this->lang)
				];
			}		
        
			return JSON::safeEncode($data,true);
	        $this->db= null;
        }

        /**
         * Show balance as pagination for admin
         */
        public function showBalanceAdmin(){
            if (Auth::validToken($this->db,$this->token,$this->username)){
                $role = Auth::getRoleID($this->db,$this->token);
                if ($role == '1' || $role == '2'){
                    $search = "%$this->search%";
				    //count total row
    				$sqlcountrow = "SELECT count(a.DepositID) as 'TotalRow'
                        from deposit_balance a
                        where a.DepositID like :search
                        order by a.DepositID asc;";
                    $stmt = $this->db->prepare($sqlcountrow);
                    $stmt->bindParam(':search', $search, PDO::PARAM_STR);
                    
    				if ($stmt->execute()) {	
        	    		if ($stmt->rowCount() > 0){
		    				$single = $stmt->fetch();
						
			    			// Paginate won't work if page and items per page is negative.
				    		// So make sure that page and items per page is always return minimum zero number.
					    	$newpage = Validation::integerOnly($this->page);
						    $newitemsperpage = Validation::integerOnly($this->itemsPerPage);
    						$limits = (((($newpage-1)*$newitemsperpage) <= 0)?0:(($newpage-1)*$newitemsperpage));
	    					$offsets = (($newitemsperpage <= 0)?0:$newitemsperpage);

		    				// Query Data
			    			$sql = "SELECT a.DepositID,a.Balance 
                                from deposit_balance a
                                where a.DepositID like :search
                                order by a.DepositID asc LIMIT :limpage , :offpage;";
	    					$stmt2 = $this->db->prepare($sql);
		    				$stmt2->bindParam(':search', $search, PDO::PARAM_STR);
					    	$stmt2->bindValue(':limpage', (INT) $limits, PDO::PARAM_INT);
    						$stmt2->bindValue(':offpage', (INT) $offsets, PDO::PARAM_INT);
						
	    					if ($stmt2->execute()){
                                $pagination = new \classes\Pagination();
                                $pagination->lang = $this->lang;
			    				$pagination->totalRow = $single['TotalRow'];
				    			$pagination->page = $this->page;
					    		$pagination->itemsPerPage = $this->itemsPerPage;
						    	$pagination->fetchAllAssoc = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    							$data = $pagination->toDataArray();
	    					} else {
		    					$data = [
        	        	    		'status' => 'error',
		            		    	'code' => 'RS202',
	    			        	    'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
						    	];	
    						}			
	    			    } else {
    	        			$data = [
        	        			'status' => 'error',
		    	        		'code' => 'RS601',
        			        	'message' => CustomHandlers::getreSlimMessage('RS601',$this->lang)
						    ];
    		    	    }          	   	
	    			} else {
		    			$data = [
    	        			'status' => 'error',
				    		'code' => 'RS202',
	        		        'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
    					];
	    			}
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'RS404',
                        'message' => CustomHandlers::getreSlimMessage('RS404',$this->lang)
                    ];
                }
			} else {
				$data = [
	    			'status' => 'error',
					'code' => 'RS401',
        	    	'message' => CustomHandlers::getreSlimMessage('RS401',$this->lang)
				];
			}		
        
			return JSON::safeEncode($data,true);
	        $this->db= null;
        }

        /**
         * Show the most deposit
         */
        public function showMostDeposit(){
            if (Auth::validToken($this->db,$this->token,$this->username)){
                $role = Auth::getRoleID($this->db,$this->token);
                if ($role == '1' || $role == '2'){
                    $newyear = Validation::integerOnly($this->year);
                    $newtopnumber = Validation::integerOnly($this->topnumber);
                    if ($newtopnumber <= 1000){
                        $sql = "SELECT a.DepositID,year(a.Created_at) as 'Year',count(a.ReferenceID) as 'Total'
                            from deposit_mutation a 
                            where a.Task='DB' 
                            and year(a.Created_at) = :year
                            group by a.DepositID
                            order by Total desc
                            limit :lim;";
                        $stmt = $this->db->prepare($sql);
                        $stmt->bindParam(':year', $newyear, PDO::PARAM_STR);
                        $stmt->bindValue(':lim', (INT) $newtopnumber, PDO::PARAM_INT);
                        
				
	        			if ($stmt->execute()) {	
    	            	    if ($stmt->rowCount() > 0){
        	           		   	$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
				        		$data = [
			   	                    'results' => $results, 
    	    		                'status' => 'success', 
			           	            'code' => 'RS501',
                		        	'message' => CustomHandlers::getreSlimMessage('RS501',$this->lang)
	        					];
		        	        } else {
        	        		    $data = [
            	        	    	'status' => 'error',
		        	        	    'code' => 'RS601',
        		    	            'message' => CustomHandlers::getreSlimMessage('RS601',$this->lang)
    						    ];
    	        	        }          	   	
	    	    		} else {
		    	    		$data = [
    	        	    		'status' => 'error',
				    	    	'code' => 'RS202',
    	        	    	    'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
	    				    ];
    		    		}
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 'RS604',
                            'message' => CustomHandlers::getreSlimMessage('RS604',$this->lang).' '.CustomHandlers::getreSlimMessage('RS605',$this->lang).'1000.'
                        ];
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'RS404',
                        'message' => CustomHandlers::getreSlimMessage('RS404',$this->lang)
                    ];
                }
			} else {
				$data = [
	    			'status' => 'error',
					'code' => 'RS401',
        	    	'message' => CustomHandlers::getreSlimMessage('RS401',$this->lang)
				];
			}		
        
			return JSON::safeEncode($data,true);
	        $this->db= null;
        }

        /**
         * Show the most transaction
         */
        public function showMostTransaction(){
            if (Auth::validToken($this->db,$this->token,$this->username)){
                $role = Auth::getRoleID($this->db,$this->token);
                if ($role == '1' || $role == '2'){
                    $newyear = Validation::integerOnly($this->year);
                    $newtopnumber = Validation::integerOnly($this->topnumber);
                    if ($newtopnumber <= 1000){
                        $sql = "SELECT a.DepositID,year(a.Created_at) as 'Year',count(a.ReferenceID) as 'Total'
                            from deposit_mutation a 
                            where a.Task='CR' 
                            and year(a.Created_at) = :year
                            group by a.DepositID
                            order by Total desc
                            limit :lim;";
                        $stmt = $this->db->prepare($sql);
                        $stmt->bindParam(':year', $newyear, PDO::PARAM_STR);
                        $stmt->bindValue(':lim', (INT) $newtopnumber, PDO::PARAM_INT);
                        
				
	        			if ($stmt->execute()) {	
    	            	    if ($stmt->rowCount() > 0){
        	           		   	$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
				        		$data = [
			   	                    'results' => $results, 
    	    		                'status' => 'success', 
			           	            'code' => 'RS501',
                		        	'message' => CustomHandlers::getreSlimMessage('RS501',$this->lang)
	        					];
		        	        } else {
        	        		    $data = [
            	        	    	'status' => 'error',
		        	        	    'code' => 'RS601',
        		    	            'message' => CustomHandlers::getreSlimMessage('RS601',$this->lang)
    						    ];
    	        	        }          	   	
	    	    		} else {
		    	    		$data = [
    	        	    		'status' => 'error',
				    	    	'code' => 'RS202',
    	        	    	    'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
	    				    ];
    		    		}
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 'RS604',
                            'message' => CustomHandlers::getreSlimMessage('RS604',$this->lang).' '.CustomHandlers::getreSlimMessage('RS605',$this->lang).'1000.'
                        ];
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'RS404',
                        'message' => CustomHandlers::getreSlimMessage('RS404',$this->lang)
                    ];
                }
			} else {
				$data = [
	    			'status' => 'error',
					'code' => 'RS401',
        	    	'message' => CustomHandlers::getreSlimMessage('RS401',$this->lang)
				];
			}		
        
			return JSON::safeEncode($data,true);
	        $this->db= null;
        }

        /**
         * Show the most rich
         */
        public function showMostRich(){
            if (Auth::validToken($this->db,$this->token,$this->username)){
                $role = Auth::getRoleID($this->db,$this->token);
                if ($role == '1' || $role == '2'){
                    $newtopnumber = Validation::integerOnly($this->topnumber);
                    if ($newtopnumber <= 1000){
                        $sql = "SELECT a.DepositID,a.Balance
                            from deposit_balance a 
                            order by a.Balance desc
                            limit :lim;";
                        $stmt = $this->db->prepare($sql);
                        $stmt->bindValue(':lim', (INT) $newtopnumber, PDO::PARAM_INT);
                        
				
	        			if ($stmt->execute()) {	
    	            	    if ($stmt->rowCount() > 0){
        	           		   	$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
				        		$data = [
			   	                    'results' => $results, 
    	    		                'status' => 'success', 
			           	            'code' => 'RS501',
                		        	'message' => CustomHandlers::getreSlimMessage('RS501',$this->lang)
	        					];
		        	        } else {
        	        		    $data = [
            	        	    	'status' => 'error',
		        	        	    'code' => 'RS601',
        		    	            'message' => CustomHandlers::getreSlimMessage('RS601',$this->lang)
    						    ];
    	        	        }          	   	
	    	    		} else {
		    	    		$data = [
    	        	    		'status' => 'error',
				    	    	'code' => 'RS202',
    	        	    	    'message' => CustomHandlers::getreSlimMessage('RS202',$this->lang)
	    				    ];
    		    		}
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 'RS604',
                            'message' => CustomHandlers::getreSlimMessage('RS604',$this->lang).' '.CustomHandlers::getreSlimMessage('RS605',$this->lang).'1000.'
                        ];
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 'RS404',
                        'message' => CustomHandlers::getreSlimMessage('RS404',$this->lang)
                    ];
                }
			} else {
				$data = [
	    			'status' => 'error',
					'code' => 'RS401',
        	    	'message' => CustomHandlers::getreSlimMessage('RS401',$this->lang)
				];
			}		
        
			return JSON::safeEncode($data,true);
	        $this->db= null;
        }
    }