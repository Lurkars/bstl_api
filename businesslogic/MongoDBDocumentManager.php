<?php

class MongoDBDocumentManager
{
    private $collection;
    private $gridFsBucket;

    public function __construct()
    {
        include_once dirname(__FILE__).'/../config/MongoDB.php';
        $this->collection = new MongoDB\Collection(new MongoDB\Driver\Manager(CONFIG_MONGODB_URI), CONFIG_MONGODB_DB, CONFIG_MONGODB_COLLECTION, array(
          'typeMap' => array(
            'root' => 'array',
            'document' => 'array',
          ),
        ));
        $this->gridFsBucket = (new MongoDB\Client())->CONFIG_MONGODB_COLLECTION->selectGridFSBucket();

        if ($this->collection) {
          // FIXME
          // $this->collection->dropIndexes();
          $this->collection->createIndex(array('id' => 'text', 'name' => 'text', 'data.name' => 'text', '$**' => 'text'), array('weights' => array('id' => 5, 'name' => 7, 'data.name' => 10)));
        }
    }

    /**
     * find document.
     */
    public function findOne($userId, $filter = array(), $fields = array(),  $clean = true)
    {
        if ($this->collection) {
            $options = array();
            $options['projection'] = $fields;
            $document = $this->collection->findOne($filter, $options);
        }

        return $clean ? $this->cleanUp($userId, $document) : $document;
    }

    /**
     * find documents.
     */
    public function find($userId, $filter = array(), $pagination = array(), $fields = array(), $clean = true)
    {
        $documents = array();
        if ($this->collection) {
            $options = array();
            $options['skip'] = isset($pagination['offset']) ? $pagination['offset'] : 0;
            $options['limit'] = isset($pagination['limit']) ? $pagination['limit'] : 20;
            $options['sort'] = isset($pagination['order'])  ? $pagination['order'] : array('modified' => -1);
            if (isset($pagination['order']) && isset($pagination['order']['text-score']) && isset($pagination['order']['text-score']['$meta'])) {
              $options['projection'] = array_merge($fields, array('text-score' => array('$meta' => 'textScore')));
            } else {
              $options['projection'] = $fields;
            }
            $cursor = $this->collection->find($filter, $options);
            $pagination['total'] = $this->collection->count($filter);
            foreach ($cursor as $document) {
                $document = $clean ? $this->cleanUp($userId, $document) : $document;
                // remove meta
                if ($clean && count($fields) > 0 && (!array_key_exists('meta', $fields) || !$fields['meta'])) {
                    unset($document['meta']);
                }

                // remove structure
                if ($clean && count($fields) > 0 && (!array_key_exists('structure', $fields) || !$fields['structure'])) {
                    unset($document['structure']);
                }

                array_push($documents, $document);
            }
        }

        return array('documents' => $documents, 'pagination' => $pagination);
    }

    /**
     * count documents.
     */
    public function count($userId, $filter = array())
    {
        if ($this->collection) {
            return $this->collection->count($filter);
        }

        return 0;
    }

    /**
     * Get document from database.
     */
    public function getDocument($userId, $id, $clean = true)
    {
        if ($this->collection) {
            $document = $this->collection->findOne(array('id' => $id));
        }

        return $clean ? $this->cleanUp($userId, $document) : $document;
    }

    /**
     * Get file from database.
     */
    public function getDocumentFileStream($userId, $id, $fileFieldKey)
    {
        if ($this->collection) {
            $fileMappingQuery = array('$and' => array(array('author' => array('$exists' => false)), array('documentId' => $id)));
            $fileMapping = $this->findOne($userId, $fileMappingQuery, array(), array(), false);

            if (isset($fileMapping) && isset($fileMapping['files']) &&  isset($fileMapping['files'][$fileFieldKey])) {
                return $this->gridFsBucket->openDownloadStream($fileMapping['files'][$fileFieldKey]);
            }
        }
    }

    /**
     * Get file from database.
     */
    public function getDocumentFile($userId, $id, $fileFieldKey)
    {
        if ($this->collection) {
            $fileMappingQuery = array('$and' => array(array('author' => array('$exists' => false)), array('documentId' => $id)));
            $fileMapping = $this->findOne($userId, $fileMappingQuery, array(), array(), false);

            if (isset($fileMapping) && isset($fileMapping['files']) &&  isset($fileMapping['files'][$fileFieldKey])) {
                return $this->gridFsBucket->findOne(array('_id' => $fileMapping['files'][$fileFieldKey]));
            }
        }
    }

    /**
     * Remove document.
     */
    public function removeDocument($userId, $id)
    {
        if ($this->collection) {
            $this->collection->deleteOne(array('id' => $id));
        }

        return;
    }

    /**
     * Save or update document.
     */
    public function updateDocument($userId, $document, $clean = true)
    {
        if ($this->collection) {
            $document = $this->prepare($userId, $document);
            if (!isset($document)) {
                return;
            }

            $oldDocument = $this->collection->findOne(array('id' => $document['id']));

            $this->collection->replaceOne(array('id' => $document['id']), $document, array('upsert' => true));

            // recursive aggregate
            if (isset($document['users']) && (!isset($oldDocument['users']) || $document['users'] != $oldDocument['users'])) {
                $documents = $this->collection->find(array('users' => array('$in' => array($document['id']))));
                foreach ($documents as $updateDocument) {
                    $this->updateDocument($userId, $updateDocument);
                }
            }
        }

        return $clean ? $this->cleanUp($userId, $document) : $document;
    }

    /**
     * Handle file uploads with GridFS.
     */
    public function handleFiles($userId, $document, $postdata)
    {
        $fileMappingQuery = array('$and' => array(array('author' => array('$exists' => false)), array('documentId' => $document['id'])));
        $fileMapping = $this->findOne($userId, $fileMappingQuery, array(), array(), false);
        $saveMapping = false;

        if (!isset($fileMapping)) {
            $fileMapping = array('documentId' => $document['id']);
            $fileMapping['files'] = array();
        } else {
            $saveMapping = true;
        }

        unset($fileMapping['meta']);

        // save new files
        foreach ($postdata as $key => $file) {
            if ($key !== '--data--' && $file->getError() === UPLOAD_ERR_OK) {
                $fileMapping['files'][$key] = $this->gridFsBucket->uploadFromStream($file->getClientFilename(), fopen($file->file, 'r'));
                $saveMapping = true;
            }
        }

        // clean old files
        foreach ($fileMapping['files'] as $fileKey => $fileValue) {
            $dataFieldKeys = explode('-', preg_replace('/-\d+$/', '', $fileKey));

            $field = &$document['data'];
            $cleanFile = true;
            foreach ($dataFieldKeys as $index => $key) {
                if (isset($field[$key])) {
                    $field = &$field[$key];
                    if ($index === (count($dataFieldKeys) - 1) && isset($field['files']) && in_array($fileKey, array_keys($field['files']))) {
                        $cleanFile = false;
                    }
                }
            }

            if ($cleanFile) {
                $this->gridFsBucket->delete($fileValue);
                unset($fileMapping['files'][$fileKey]);
                $saveMapping = true;
            }
        }

        if ($saveMapping) {
            $this->collection->replaceOne($fileMappingQuery, $fileMapping, array('upsert' => true));
        }

        return $document;
    }

    /**
     * Check view access for user.
     */
    public function grantAccess($userId, $id, $accessType)
    {
        if ($this->collection) {
            $query = array('$and' => array(array('id' => $id), $this->accessQuery($userId, $accessType)));

            return $this->collection->findOne($query) !== null;
        }

        return false;
    }

    /**
     *
     */
    public function accessQuery($userId, $accessType)
    {
        if ($userId === 'system') {
            return array('$or' => array(array('id' => array('$in' => array())), array('id' => array('$nin' => array()))));
        }
        if (!isset($userId)) {
            return array('permissions.'.$accessType.'.p' => true);
        } else {
            return array('$or' => array(
                        // public
                        array('permissions.'.$accessType.'.p' => true),
                        // other
                        array('permissions.'.$accessType.'.o' => true),
                        // user
                        array('$and' => array(array('permissions.'.$accessType.'.u' => true), array('aggregate_users' => array('$in' => array($userId))))),
                        // author
                        array('$and' => array(array('permissions.'.$accessType.'.a' => true), array('author' => $userId))),
                       // id
                        array('$and' => array(array('permissions.'.$accessType.'.id' => true), array('id' => $userId))), ));
        }
    }

    /**
     * aggregate users.
     */
    public function aggregateUsers($userId, $document, $users = array())
    {
        if ($this->collection) {
            if (isset($document) && isset($document['users'])) {
                foreach ($document['users'] as $documentId) {
                    if (!in_array($documentId, $users)) {
                        array_push($users, $documentId);
                        $user = $this->getDocument($userId, $documentId, false);
                        $users = $this->aggregateUsers($userId, $user, $users);
                    }
                }
            }
        }

        return $users;
    }

    /*
     * Prepare document before saving
     */
    protected function prepare($userId, $document)
    {
        if (!isset($document['id'])) {
            $document = $this->createDocument($userId, $document);
        }

        $oldDocument = $this->getDocument($userId, $document['id'], false);

        // updated
        $document['updated'] = time() * 1000;
        // clean mongodb id
        unset($document['_id']);
        // clean meta
        unset($document['meta']);

        // aggregate users
        $document['aggregate_users'] = $this->aggregateUsers($userId, $document);

        // handle authentication
        if (isset($document['interfaces']) && is_array($document['interfaces']) && in_array('authentication',  $document['interfaces']) && isset($document['data'])) {

            if (isset($document['data']['loginname'])) {
              // lowercase loginname
              $document['data']['loginname'] = strtolower($document['data']['loginname']);
            } else {
              // no login name set
              return;
            }

            /*
            * TODO correct to leak this information?
            */
            // cancel duplicate loginnames
            if ($this->findOne($userId, array('id' => array('$ne' => $document['id']), 'data.loginname' => $document['data']['loginname'], 'interfaces' => array('$in' => array('authentication')))) !== null) {
                return;
            }
            // create hashed authentication password
            if (isset($document['data']['password'])) {
                $document['data']['password'] = password_hash($document['data']['password'], PASSWORD_BCRYPT);
            } else {
                // restore old password
                if (isset($oldDocument['data']['password'])) {
                    $document['data']['password'] = $oldDocument['data']['password'];
                }
            }
        }

        // handle Files

        return json_decode(json_encode($document), true);
    }

    /*
     * Create document
     */
    protected function createDocument($userId, $document)
    {
        // id
        $document['id'] = $this->generateDocumentId($document);
        // created
        $document['created'] = time() * 1000;
        // author
        $document['author'] = $userId;

        return $document;
    }

    /*
     * Generate id for document
     */
    protected function generateDocumentId($document)
    {
        $baseId = '';
        if (isset($document['type'])) {
            $baseId = preg_replace('/[^-_.a-zA-Z0-9]/', '', preg_replace('/\s+/', '_', strtolower($document['type']))).'-';
        }

        if (isset($document['data']) && isset($document['data']['name'])) {
            $baseId = $baseId.preg_replace('/[^-_.a-zA-Z0-9]/', '', preg_replace('/\s+/', '_', strtolower($document['data']['name'])));
        } else {
            $baseId = $baseId.uniqid();
        }

        $id = $baseId;
        $index = 0;
        while ($this->collection->findOne(array('id' => $id))) {
            $id = $baseId.'-'.++$index;
        }

        return $id;
    }

    /**
     * clean document before return.
     */
    protected function cleanUp($userId, $document, $interfaces = array())
    {
        if ($this->collection && isset($document)) {
            if (!isset($document['id'])) {
                return array('id' => $document['_id']);
            }

            unset($document['_id']);
            //unset($document['aggregate_users']);

            if (!isset($document['meta'])) {
                $document['meta'] = array();
            }

            // set author
            if (isset($document['author']) && $this->grantAccess($userId, $document['author'], 'view')) {
                $document['meta']['author'] = $document['author'];
            }

            if (!isset($document['structure'])) {
                $document['structure'] = array();
            }

            // apply meta structure
            $document['meta']['structure'] = array($document['id'] => $document['structure']);

            if (isset($document['interfaces']) && is_array($document['interfaces'])) {
                $document['meta']['interfaces'] = array();
                foreach ($document['interfaces'] as $interface) {
                    if (!in_array($interface, $interfaces) && !in_array($interface, $document['meta']['interfaces'])) {
                        array_push($document['meta']['interfaces'], $interface);

                        // recursive structure
                        $type = $this->cleanUp($userId, $this->collection->findOne(array('id' => $interface)), $document['meta']['interfaces']);
                        if (isset($type['meta'])) {
                            if (isset($type['meta']['structure'])) {
                                $document['meta']['structure'] = array_merge($type['meta']['structure'], $document['meta']['structure']);
                            }
                            if (isset($type['meta']['interfaces'])) {
                                $document['meta']['interfaces'] = array_unique(array_merge($type['meta']['interfaces'], $document['meta']['interfaces']));
                            }
                        }
                    }
                }

                // clear authentication data
                if (in_array('authentication',  $document['interfaces']) && isset($document['data'])) {
                    if (isset($document['data']['password'])) {
                      unset($document['data']['password']);
                    }
                    if ($userId != $document['id'] && isset($document['data']['loginname'])) {
                        unset($document['data']['loginname']);
                    }
                }
            }
        }

        return $document;
    }
}
