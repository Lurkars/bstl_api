<?php

class DebugController
{
    private $documentManager;
   //Constructor
   public function __construct()
   {
       include_once dirname(__FILE__).'/../businesslogic/MongoDBDocumentManager.php';
       $this->documentManager = new MongoDBDocumentManager();
   }

    /**
     *
     */
    public function test($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');
        if ($userId !== 'system') {
            return $response->withStatus(403);
        }

        $document = $this->documentManager->getDocument($userId, 'usertest');
        $result = $this->documentManager->aggregateUsers($userId, $document);

        return $response->write(json_encode($result));
    }


}

$app->get('/debug', '\DebugController:test');
