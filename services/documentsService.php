<?php

class DocumentsController
{
    private $documentManager;
   //Constructor
   public function __construct()
   {
       include_once dirname(__FILE__).'/../businesslogic/MongoDBDocumentManager.php';
       $this->documentManager = new MongoDBDocumentManager();
   }

    /**
     * find documents by filter.
     */
    public function findDocuments($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');
        $requestData = json_decode($request->getBody(), true);

        if (isset($requestData['filter']) && !empty($requestData['filter'])) {
            $filter = array('$and' => array($requestData['filter'], $this->documentManager->accessQuery($userId, 'view')));
        } else {
            $filter = $this->documentManager->accessQuery($userId, 'view');
        }

        $pagination = isset($requestData['pagination']) ? $requestData['pagination'] : array();
        $fields = isset($requestData['fields']) ? $requestData['fields'] : array();

        $result = $this->documentManager->find($userId, $filter, $pagination, $fields);

        return $response->write(json_encode($result));
    }

    /**
     * find documents by filter.
     */
    public function findDocument($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');
        $requestData = json_decode($request->getBody(), true);

        if (isset($requestData['filter'])) {
            $filter = array('$and' => array($requestData['filter'], $this->documentManager->accessQuery($userId, 'view')));
        } else {
            $filter = $this->documentManager->accessQuery($userId, 'view');
        }

        $fields = isset($requestData['fields']) ? $requestData['fields'] : array();

        $result = $this->documentManager->findOne($userId, $filter, $fields);

        return $response->write(json_encode($result));
    }

    /**
     * count documents by filter.
     */
    public function countDocuments($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');
        $requestData = json_decode($request->getBody(), true);

        if (isset($requestData['filter']) && !empty($requestData['filter'])) {
            $filter = array('$and' => array($requestData['filter'], $this->documentManager->accessQuery($userId, 'view')));
        } else {
            $filter = $this->documentManager->accessQuery($userId, 'view');
        }

        $result = $this->documentManager->count($userId, $filter);

        return $response->write(json_encode($result));
    }
}

// get documents: /documents
$app->post('/documents', '\DocumentsController:findDocuments');

// get documents: /documents/one
$app->post('/documents/one', '\DocumentsController:findDocument');

// get documents: /documents/count
$app->post('/documents/count', '\DocumentsController:countDocuments');
