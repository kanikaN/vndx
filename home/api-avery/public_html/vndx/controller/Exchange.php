<?php

/**
 * @Author Kanika Navla (kanikanavla@gmail.com)
 * 
 * Has functions for connecting to Ms Exchange and manipulating emails
 */

require_once dirname(__FILE__).'/../php-ews/EWSType/CalendarItemType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/BodyType.php';
require_once dirname(__FILE__)."/../php-ews/ExchangeWebServices.php";
require_once dirname(__FILE__)."/../php-ews/EWS_Exception.php";
require_once dirname(__FILE__).'/../php-ews/EWSType/FindFolderType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/FolderQueryTraversalType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/FolderResponseShapeType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/DefaultShapeNamesType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/IndexedPageViewType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/NonEmptyArrayOfBaseFolderIdsType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/DistinguishedFolderIdType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/DistinguishedFolderIdNameType.php';
require_once dirname(__FILE__).'/../php-ews/EWSAutodiscover.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/FindItemType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/ItemResponseShapeType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/ItemQueryTraversalType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/NonEmptyArrayOfFieldOrdersType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/FieldOrderType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/MessageType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/EmailAddressType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/SingleRecipientType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/CreateItemType.php';
require_once dirname(__FILE__).'/../php-ews/EWSType/NonEmptyArrayOfAllItemsType.php';
require_once dirname(__FILE__).'/../model/Connect.php';

class ExchangeController{
    
    private  $server = 'connect.emailsrvr.com';
    private $ver = 'Exchange2007';
    private $server_outbound = 'secure.emailsrvr.com';//'exchange.domain.com';
    private $port_outbound = 2525;
    //const ALLOWED_SIZE = 20;
    

    public function getAllInboxFolders(){
        $ews = new ExchangeWebServices($this->server, $this->username, $this->password);
        

        // start building the find folder request
        $request = new EWSType_FindFolderType();
        $request->Traversal = EWSType_FolderQueryTraversalType::SHALLOW;
        $request->FolderShape = new EWSType_FolderResponseShapeType();
        $request->FolderShape->BaseShape = EWSType_DefaultShapeNamesType::ALL_PROPERTIES;

        // configure the view
        $request->IndexedPageFolderView = new EWSType_IndexedPageViewType();
        $request->IndexedPageFolderView->BasePoint = 'Beginning';
        $request->IndexedPageFolderView->Offset = 0;

        // set the starting folder as the inbox
        $request->ParentFolderIds = new EWSType_NonEmptyArrayOfBaseFolderIdsType();
        $request->ParentFolderIds->DistinguishedFolderId = new EWSType_DistinguishedFolderIdType();
        $request->ParentFolderIds->DistinguishedFolderId->Id = EWSType_DistinguishedFolderIdNameType::INBOX;

        // make the actual call
        $response = $ews->FindFolder($request);
        //echo '<pre>'.print_r($response, true).'</pre>';
    }
    
    /**
     * fetch all emails for an agent from exchange server
     * @param type $username
     * @param type $password
     * @return array emails 
     */
    public function getEmails($username, $password){
        try{
        $ews = new ExchangeWebServices($this->server, $username, $password);
        $request = new EWSType_FindItemType();

        $request->ItemShape = new EWSType_ItemResponseShapeType();
        $request->ItemShape->BaseShape = EWSType_DefaultShapeNamesType::DEFAULT_PROPERTIES;

        $request->Traversal = EWSType_ItemQueryTraversalType::SHALLOW;

        $request->ParentFolderIds = new EWSType_NonEmptyArrayOfBaseFolderIdsType();
        $request->ParentFolderIds->DistinguishedFolderId = new EWSType_DistinguishedFolderIdType();
        $request->ParentFolderIds->DistinguishedFolderId->Id = EWSType_DistinguishedFolderIdNameType::INBOX;

        // sort order
        $request->SortOrder = new EWSType_NonEmptyArrayOfFieldOrdersType();
        $request->SortOrder->FieldOrder = array();
        $order = new EWSType_FieldOrderType();
        // sorts mails so that oldest appear first
        // more field uri definitions can be found from types.xsd (look for UnindexedFieldURIType)
        $order->FieldURI->FieldURI = 'item:DateTimeReceived'; 
        $order->Order = 'Ascending'; 
        $request->SortOrder->FieldOrder[] = $order;

        $response = $ews->FindItem($request);
        return $response;
        }catch(Exception $e){
            echo $e->getMessage();
        }
    }
    
    /**
     * fetch all agents 
     *  add or update emails in the DB for each agent
     */
    public function addEmailsToDB(){
        $agents = $this->getAllAgent();
        
        foreach($agents as $agent){
            echo '<br>'.date('Y-m-d h:i:s',time())." agent Id: ".$agent['id']." email: ".$agent['exchange_email'].'<br>';
            $this->username = $agent['exchange_email'];
            $this->password = $agent['exchange_password'];
            $emails = $this->getEmails($agent['exchange_email'],$agent['exchange_password']);
            if(isset($emails->ResponseMessages->FindItemResponseMessage->RootFolder->TotalItemsInView) 
                    && $emails->ResponseMessages->FindItemResponseMessage->RootFolder->TotalItemsInView>0)
                $this->addUpdateMessages($emails, $agent['id']);
            //else no emails for this agent
        }
    }
    
    
    
    
    /**
     * add emails to DB for an agent
     * @param array $emails
     * @param int $agentId
     */
    private function addUpdateMessages($emails, $agentId){
        try{
        if($messages = $emails->ResponseMessages->FindItemResponseMessage->RootFolder->Items->Message){
                //echo '<pre>'.print_r($messages, true).'</pre>';
                $values = array();
                foreach( $messages as $message){
                    $arr = array();
                    //if the entry exists - update else - add 
                    //add message_id, agent_id, contact_id, from_email, from_name, date_sent, date_created,
                    //read_status,subject
                    $messageIdArr = explode('/', $message->ItemId->Id);
                    $arr['agent_id'] = $agentId;
                    $arr['message_id'] = $messageIdArr[count($messageIdArr)-1];
                    $arr['from_name'] = @$message->From->Mailbox->Name;
                    $arr['date_sent'] = @$message->DateTimeSent;
                    $arr['date_created'] = @$message->DateTimeCreated;
                    $arr['read_status'] = ($message->IsRead)?'READ':'UNREAD';
                    $arr['subject'] = @$message->Subject;
                    
                    $values[] = $arr;
                }
                //add values
                $con = new Connect();
                
                if($con->addEmails($values) || !$con->error)
                    echo 'Emails added/updated to DB : '.$emails->ResponseMessages->FindItemResponseMessage->RootFolder->TotalItemsInView.'<br>';
            }
        }catch(Exception $e){
            echo $e->getMessage();
        }
    }
    
    /**
     * fetch all active agents from DB
     * @return type
     */
    public function getAllAgent(){
        $con = new Connect();
        $result = $con->getAgents();
        return $result;
    }
    
    
    public function sendEmailsFromDB(){
        //get agent wih outbound emails
        $con = new Connect();
        //try{
        require_once "Mail.php";
        if($outbounds = $con->getOutboundWithAgents()){
            foreach($outbounds as $outbound){
                $from = '<'.$outbound['exchange_email'].'>';
                $to = '<'.$outbound['to_email'].'>';
                $subject = @$outbound['subject'];
                $body = @$outbound['email'];

                $host = $this->server_outbound;
                $username = $outbound['exchange_email'];
                $password = $outbound['exchange_password'];

                $headers = array ('From' => $from,
                  'To' => $to,
                  'Subject' => $subject);
                $smtp = Mail::factory('smtp',
                  array ('host' => $host,
                    'auth' => true,
                    'username' => $username,
                    'password' => $password));

                $mail = $smtp->send($to, $headers, $body);

                if (PEAR::isError($mail)) {
                  echo("<p>" . $mail->getMessage() . "</p>");
                 } else {
                  echo("<p>Message successfully sent from ".$outbound['exchange_email']." to ".$outbound['to_email']."</p>");
                 }
            }
        }else{
            echo 'no agents found';
            return false;
        }
//        }catch(Exception $e){
//            echo $e->getMessage();
//        }
    }
    
    
    	public function sendEmail()
	{
		
		$ews = $this->autoDiscover();
		$msg = new EWSType_MessageType();
		
		$toAddresses = array();
		$toAddresses[0] = new EWSType_EmailAddressType();
		$toAddresses[0]->EmailAddress = 'kanikanavla@gmail.com';
		$toAddresses[0]->Name = 'John Harris';
		
		// Multiple recipients
		//$toAddresses[1] = new EWSType_EmailAddressType();
		//$toAddresses[1]->EmailAddress = 'sara.smith@domain.com';
		//$toAddresses[1]->Name = 'Sara Smith';

		$msg->ToRecipients = $toAddresses;
		
		$fromAddress = new EWSType_EmailAddressType();
		$fromAddress->EmailAddress = 'apptest@averyoil.com';
		$fromAddress->Name = 'apptest@averyoil.com';

		$msg->From = new EWSType_SingleRecipientType();
		$msg->From->Mailbox = $fromAddress;
		
		$msg->Subject = 'Test email message';
		
		$msg->Body = new EWSType_BodyType();
		$msg->Body->BodyType = 'HTML';
		$msg->Body->_ = '<p style="font-size: 18px; font-weight: bold;">Test email message from php ews library.</p>';
		
		$msgRequest = new EWSType_CreateItemType();
		$msgRequest->Items = new EWSType_NonEmptyArrayOfAllItemsType();
		$msgRequest->Items->Message = $msg;
		$msgRequest->MessageDisposition = 'SendAndSaveCopy';
		$msgRequest->MessageDispositionSpecified = true;
				echo '<pre>'.print_r($msgRequest,true).'</pre>';
		$response = $ews->CreateItem($msgRequest);
		var_dump($response);
		
		
	}
        
        public function autoDiscover(){
            $username = 'apptest@averyoil.com';
		$password = 'Vndx1234';
                
            $ews = EWSAutodiscover::getEWS($username, $password);
            if(!$ews){
                $server = $this->server_outbound;//'outlook.com';
                $ews = new ExchangeWebServices($server, $username, $password);
            }
             return $ews;
        }
}