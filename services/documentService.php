<?php

class DocumentController
{
    private $documentManager;
   //Constructor
   public function __construct()
   {
       include_once dirname(__FILE__).'/../businesslogic/MongoDBDocumentManager.php';
       $this->documentManager = new MongoDBDocumentManager();
   }

    /**
     * get document by id.
     */
    public function getDocument($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');
        $documentId = $args['id'];
        if (!$this->documentManager->grantAccess($userId, $documentId, 'view')) {
            if (isset($userId)) {
                return $response->withStatus(403);
            } else {
                return $response->withStatus(401);
            }
        }
        $document = $this->documentManager->getDocument($userId, $documentId);
        $queryParams = $request->getQueryParams();

        // create clone
        if (isset($queryParams['clone'])) {
            if (isset($document['data']) && isset($document['data']['name'])) {
                $document['data']['name'] = $document['data']['name'].' [clone]';
            }
        }

        // create template
        if (isset($queryParams['template'])) {

            $document['interfaces'] = array();

            array_push($document['interfaces'], $documentId);
            $document['interfaces'] = array_unique($document['interfaces']);


            if (!isset($document['meta']['interfaces'])) {
                $document['meta']['interfaces'] = array();
            }

            array_push($document['meta']['interfaces'], $documentId);
            $document['meta']['interfaces'] = array_unique($document['meta']['interfaces']);

            unset($document['structure']);
            if (!isset($queryParams['clone'])) {
                unset($document['data']);
            }

            if (isset($document['templatePermissions'])) {
                $document['permissions'] = $document['templatePermissions'];
            }

            $document['type'] = $document['id'];

            unset($document['template']);
            unset($document['templatePermissions']);
        }

        if (isset($queryParams['clone']) || isset($queryParams['template'])) {
            unset($document['id']);
            unset($document['created']);
            unset($document['updated']);
            unset($document['modified']);
            $document['author'] = $userId;
        }

        return $response->write(json_encode($document));
    }

    /**
     * get document by id, filter field.
     */
    public function getDocumentField($request, $response, $args)
    {
        if ($request->getAttribute('path') === null) {
            return $this->getDocument($request, $response, $args);
        }

        $userId = $request->getAttribute('userId');
        $documentId = $args['id'];
        if (!$this->documentManager->grantAccess($userId, $documentId, 'view')) {
            if (isset($userId)) {
                return $response->withStatus(403);
            } else {
                return $response->withStatus(401);
            }
        }

        $document = $this->documentManager->getDocument($userId, $documentId);

        $path = explode('/', $request->getAttribute('path'));

        $field = &$document;
        foreach ($path as $key) {
            if (isset($field[$key])) {
                $field = &$field[$key];
            } else {
                $field = null;
            }
        }

        return $response->write(json_encode($field));
    }

    /**
     * get file by documentId and file.
     */
    public function getDocumentFile($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');
        $documentId = $args['id'];
        $fileFieldKey = $args['file'];
        if (!$this->documentManager->grantAccess($userId, $documentId, 'view')) {
            if (isset($userId)) {
                return $response->withStatus(403);
            } else {
                return $response->withStatus(401);
            }
        }

        $fileResource = $this->documentManager->getDocumentFileStream($userId, $documentId, $fileFieldKey);
        $fileDocument =  $this->documentManager->getDocumentFile($userId, $documentId, $fileFieldKey);;

        if (empty($fileDocument)) {
            return $response->withStatus(404);
        }

        $stream = new \GuzzleHttp\Psr7\CachingStream(\GuzzleHttp\Psr7\stream_for($fileResource));

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        // return $response->withStatus(200);
        // return $response->withBody($stream)->withHeader('Content-Disposition', 'attachment; filename="'.$fileDocument->filename.'";"');
        return $response->withBody($stream)->withHeader('Content-Type', finfo_buffer($finfo, stream_get_meta_data($fileResource)['uri']))->withHeader('Content-Disposition', 'attachment; filename="'.$fileDocument->filename.'";"');

    }

    /**
     * push (new) document into database.
     */
    public function updateDocument($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');
        $postdata = $request->getUploadedFiles();

        if (isset($postdata['--data--'])) {
            // read document
            $document = json_decode(file_get_contents($postdata['--data--']->file), true);

            if (isset($document['id']) && !$this->documentManager->grantAccess($userId, $document['id'], 'own')) {
                return $response->withStatus(403);
            }

            // check parent privileges
            if (isset($document['parentview']) && !$this->documentManager->grantAccess($userId, $document['parentview'], 'view')) {
                unset($document['parentview']);
            }

            if (isset($document['parentedit']) && !$this->documentManager->grantAccess($userId, $document['parentedit'], 'edit')) {
                unset($document['parentedit']);
            }

            if (isset($document['parentstructure']) && !$this->documentManager->grantAccess($userId, $document['parentstructure'], 'structure')) {
                unset($document['parentstructure']);
            }

            if (isset($document['parentremove']) && !$this->documentManager->grantAccess($userId, $document['parentremove'], 'remove')) {
                unset($document['parentremove']);
            }

            if (isset($document['parentown']) && !$this->documentManager->grantAccess($userId, $document['parentown'], 'own')) {
                unset($document['parentown']);
            }

            // modified
            $document['modified'] = time() * 1000;

            $document = $this->documentManager->updateDocument($userId, $document);
            if (!isset($document)) {
                return $response->withStatus(415);
            }

            $document = $this->documentManager->handleFiles($userId, $document, $postdata);

            if (isset($document['parent']) || isset($document['viewParent'])) {
              $parent = $this->documentManager->getDocument($userId, isset($document['parent']) ? $document['parent'] : $document['viewParent']);
              if (isset($parent)) {
                $parent['modified'] = time() * 1000;
                $parent = $this->documentManager->updateDocument($userId, $parent);
              }
            }


        } else {
            return $response->withStatus(422);
        }

        return $response->write(json_encode($document));
    }

    /**
     * update document permissions.
     */
    public function updateDocumentPermissions($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');
        $documentId = $args['id'];

        if (!$this->documentManager->grantAccess($userId, $documentId, 'own')) {
            if (isset($userId)) {
                return $response->withStatus(403);
            } else {
                return $response->withStatus(401);
            }
        }

        $permissionModel = json_decode($request->getBody(), true);
        $document = $this->documentManager->getDocument($userId, $documentId);

        $document['permissions'] = $permissionModel['permissions'];
        $document['users'] = $permissionModel['users'];

        // modified
        // $document['modified'] = time() * 1000;

        $document = $this->documentManager->updateDocument($userId, $document);

        if (!isset($document)) {
            return $response->withStatus(422);
        }

        return $response->write(json_encode($document));
    }

    /**
     * update document structure.
     */
    public function updateDocumentStructure($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');
        $documentId = $args['id'];

        if (!$this->documentManager->grantAccess($userId, $documentId, 'structure')) {
            if (isset($userId)) {
                return $response->withStatus(403);
            } else {
                return $response->withStatus(401);
            }
        }
        $structure = json_decode($request->getBody(), true);

        $document = $this->documentManager->getDocument($userId, $documentId);

        $document['structure'] = $structure;

        // modified
        // $document['modified'] = time() * 1000;

        $document = $this->documentManager->updateDocument($userId, $document);

        if (!isset($document)) {
            return $response->withStatus(422);
        }

        return $response->write(json_encode($document));
    }

    /**
     * update document data.
     */
    public function updateDocumentData($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');
        $documentId = $args['id'];
        $postdata = $request->getUploadedFiles();

        if (isset($postdata['--data--'])) {
            // read document
            $data = json_decode(file_get_contents($postdata['--data--']->file), true);
            if (!$this->documentManager->grantAccess($userId, $documentId, 'edit')) {
                return $response->withStatus(403);
            }

            $document = $this->documentManager->getDocument($userId, $documentId);
            $document['data'] = $data;
            $document = $this->documentManager->handleFiles($userId, $document, $postdata);

            // modified
            $document['modified'] = time() * 1000;

            $document = $this->documentManager->updateDocument($userId, $document);
            if (!isset($document)) {
                return $response->withStatus(415);
            }
        } else {
            return $response->withStatus(422);
        }

        return $response->write(json_encode($document));
    }

    /**
     * update document data.
     */
    public function updateDocumentContribute($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');
        $documentId = $args['id'];

        if (!$this->documentManager->grantAccess($userId, $documentId, 'contribute')) {
            if (isset($userId)) {
                return $response->withStatus(403);
            } else {
                return $response->withStatus(401);
            }
        }
        $data = json_decode($request->getBody(), true);

        $document = $this->documentManager->getDocument($userId, $documentId);

        // TODO: error on authentication

        if (!isset($document['contribute'])) {
          $document['contribute'] = array();
        }

        if (!isset($document['contribute'][$userId])) {
          $document['contribute'][$userId] = array();
        }

        $document['contribute'][$userId] = array_merge($document['contribute'][$userId], $data);

        // modified
        // $document['modified'] = time() * 1000;

        $document = $this->documentManager->updateDocument($userId, $document);

        if (!isset($document)) {
            return $response->withStatus(422);
        }

        return $response->write(json_encode($document));
    }

    /**
     * delete document.
     */
    public function removeDocument($request, $response, $args)
    {
        $userId = $request->getAttribute('userId');
        $documentId = $args['id'];
        if (!$this->documentManager->grantAccess($userId, $documentId, 'remove')) {
            return $response->withStatus(403);
        }
        $result = $this->documentManager->removeDocument($userId, $documentId);

        return $response->write(json_encode($result));
    }
}

// get document: /document/{id}
$app->get('/document/{id}', '\DocumentController:getDocument');

// get document field: /document/{id}/file/{file}
$app->get('/document/{id}/file/{file}', '\DocumentController:getDocumentFile');

// get document field: /document/{id}/{field-path}*
$app->get('/document/{id}/[{path:.+}]', '\DocumentController:getDocumentField');

// update document: /document
$app->post('/document', '\DocumentController:updateDocument')->add($requireAuthorization);

// update document permissions: /document/{id}/permissions
$app->post('/document/{id}/permissions', '\DocumentController:updateDocumentPermissions')->add($requireAuthorization);

// update document structure: /document/{id}/structure
$app->post('/document/{id}/structure', '\DocumentController:updateDocumentStructure')->add($requireAuthorization);

// update document data: /document/{id}/data
$app->post('/document/{id}/data', '\DocumentController:updateDocumentData')->add($requireAuthorization);

// update document contribute: /document/{id}/contribute
$app->post('/document/{id}/contribute', '\DocumentController:updateDocumentContribute')->add($requireAuthorization);

// delete document: /document/{id}
$app->delete('/document/{id}', '\DocumentController:removeDocument')->add($requireAuthorization);
