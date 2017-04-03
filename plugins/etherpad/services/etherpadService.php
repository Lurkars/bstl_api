<?php

class EtherpadController
{
   //Constructor
   public function __construct()
   {
       include_once dirname(__FILE__).'/../../../businesslogic/MongoDBDocumentManager.php';
       include_once dirname(__FILE__).'/../businesslogic/EtherpadManager.php';
       $this->documentManager = new MongoDBDocumentManager();
       $this->etherpadManager = new EtherpadManager();
   }

   /**
    * get current authorized userid
    */
   public function getEtherpadId($request, $response, $args)
   {
       $userId = $request->getAttribute('userId');

       $documentId = $args['id'];
       $fieldKey = $args['fieldKey'];

       if (!$this->documentManager->grantAccess($userId, $documentId, 'view')) {
           if (isset($userId)) {
               return $response->withStatus(403);
           } else {
               return $response->withStatus(401);
           }
       }

       $etherpadId = array('etherpadId' => $this->etherpadManager->getEtherpadId($documentId, $fieldKey));

       return $response->write(json_encode($etherpadId));
   }

    /**
     * get current authorized userid
     */
    public function getEtherpadHTML($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');

        $documentId = $args['id'];
        $fieldKey = $args['fieldKey'];

        if (!$this->documentManager->grantAccess($userId, $documentId, 'view')) {
            if (isset($userId)) {
                return $response->withStatus(403);
            } else {
                return $response->withStatus(401);
            }
        }

        $padId = $this->etherpadManager->getEtherpadId($documentId, $fieldKey);

        $etherpadHTML = array('etherpadHTML' => $this->etherpadManager->getEtherpadHTML($padId));

        return $response->write(json_encode($etherpadHTML));
    }



    /**
     * get current authorized userid
     */
    public function createSession($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');

        $documentId = $args['id'];

        if (!$this->documentManager->grantAccess($userId, $documentId, 'edit')) {
            if (isset($userId)) {
                return $response->withStatus(403);
            } else {
                return $response->withStatus(401);
            }
        }

        $username = $userId;
        $user = $this->documentManager->getDocument($userId, $userId);
        if (isset($user['data']) && isset($user['data']['name'])) {
           $username = $user['data']['name'];
        }

        $etherpadSession = array('etherpadSession' => $this->etherpadManager->createSession($userId, $username, $documentId));

        return $response->write(json_encode($etherpadSession));
    }

}

$app->get('/etherpad/{id}/{fieldKey}', '\EtherpadController:getEtherpadId');

$app->get('/etherpad/{id}/{fieldKey}/html', '\EtherpadController:getEtherpadHTML');

$app->get('/etherpad/{id}/session', '\EtherpadController:createSession');
